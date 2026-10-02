<?php

use App\Enums\EstatusConceptoPago;
use App\Enums\EstatusPago;
use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Exceptions\PagoInvalidoException;
use App\Livewire\Pagos\ModalValidarPago;
use App\Models\ConceptoPago;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\User;
use App\Services\InscripcionService;
use App\Services\PagoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

/*
|--------------------------------------------------------------------------
| Fase 8 — Validación de pagos: RB-5/RB-6, FC-5 (four-eyes)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| F11 = validar pago de INSCRIPCION (transacción completa); F25 = validar
| MENSUALIDAD sin tocar `inscripciones.estatus`; reglas de validación,
| autorización server-side y ROLLBACK TOTAL ante fallos tardíos.
| Sin mocks: servicios, estados y FK reales de MySQL.
*/

/** Escenario: inscripción con cargos (1 INSCRIPCION + 4 MENSUALIDADES) en PENDING_PAYMENT. */
function escenarioValidacionF8(): array
{
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();

    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::DATA_COMPLETE)->create();
    app(InscripcionService::class)->generarCargos($inscripcion, $admisiones);

    return [
        'admisiones' => $admisiones,
        'administracion' => $administracion,
        'inscripcion' => $inscripcion->refresh(),
        'concepto' => $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail(),
    ];
}

/** Registra un intento válido (requiere actingAs con `pago.registrar`). */
function intentoF8(ConceptoPago $concepto, User $usuario, array $datos = []): Pago
{
    return app(PagoService::class)->registrar($concepto, $datos + [
        'monto' => $concepto->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
    ], $usuario);
}

test('F11: validar pago de INSCRIPCION ejecuta la operacion completa en una transaccion', function () {
    $escenario = escenarioValidacionF8();
    ['admisiones' => $admisiones, 'administracion' => $administracion, 'inscripcion' => $inscripcion, 'concepto' => $concepto] = $escenario;

    // Captura four-eyes: ADMISIONES registra → pago PENDIENTE + PAYMENT_REVIEW.
    $this->actingAs($admisiones);
    $pago = intentoF8($concepto, $admisiones);

    expect($pago->estatus)->toBe(EstatusPago::PENDIENTE)
        ->and($pago->folio)->toBeNull()
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::PAYMENT_REVIEW)
        ->and($pago->auditorias()->where('accion', 'pago_registrado')->exists())->toBeTrue()
        ->and($pago->auditorias()->where('accion', 'pago_registrado')->firstOrFail()->user_id)->toBe($admisiones->id);

    // Validación: ADMINISTRACION emite folio y liquida TODO en una transacción.
    $this->actingAs($administracion);
    $validado = app(PagoService::class)->validar($pago, $administracion);

    expect($validado->estatus)->toBe(EstatusPago::VALIDADO)
        ->and($validado->folio)->toMatch('/^PAG-\d{8}-\d{8}$/')
        ->and($validado->validado_por)->toBe($administracion->id)
        ->and($validado->validado_at)->not->toBeNull()
        ->and($concepto->refresh()->estatus)->toBe(EstatusConceptoPago::PAGADO)
        ->and($concepto->pagado_at)->not->toBeNull()
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_DOCUMENTS)
        ->and($inscripcion->pagos_validados_at)->not->toBeNull();

    // Auditorías: pago_validado + las DOS transiciones de la máquina de estados.
    expect($validado->auditorias()->where('accion', 'pago_validado')->exists())->toBeTrue()
        ->and($inscripcion->auditorias()->where('accion', 'estado_cambiado')->where('estado_nuevo', 'PAYMENT_REVIEW')->exists())->toBeTrue()
        ->and($inscripcion->auditorias()->where('accion', 'estado_cambiado')->where('estado_nuevo', 'PAYMENT_VALIDATED')->exists())->toBeTrue()
        ->and($inscripcion->auditorias()->where('accion', 'estado_cambiado')->where('estado_nuevo', 'PENDING_DOCUMENTS')->exists())->toBeTrue()
        ->and($inscripcion->auditorias()->where('accion', 'estado_cambiado')->where('estado_nuevo', 'PAYMENT_VALIDATED')->firstOrFail()->user_id)->toBe($administracion->id);
});

test('F25: validar una MENSUALIDAD conserva EXACTAMENTE el estado de la inscripcion', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();

    // Inscripción ya finalizada (ENROLLED) con una mensualidad pendiente.
    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::ENROLLED)->create();
    $mensualidad = ConceptoPago::factory()->create([
        'inscripcion_id' => $inscripcion->id,
        'tipo' => 'MENSUALIDAD',
        'concepto' => 'Mensualidad 1 - agosto 2026 (sintética)',
    ]);

    $this->actingAs($usuario);
    $pago = intentoF8($mensualidad, $usuario);

    expect($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::ENROLLED);   // registro tampoco cambia nada

    $estadoAntes = $inscripcion->refresh()->estatus;

    $this->actingAs($administracion);
    $validado = app(PagoService::class)->validar($pago, $administracion);

    expect($validado->estatus)->toBe(EstatusPago::VALIDADO)
        ->and($validado->folio)->toMatch('/^PAG-\d{8}-\d{8}$/')
        ->and($mensualidad->refresh()->estatus)->toBe(EstatusConceptoPago::PAGADO)
        ->and($mensualidad->pagado_at)->not->toBeNull()
        ->and($validado->auditorias()->where('accion', 'pago_validado')->exists())->toBeTrue()
        // REGLA F25: ni el registro ni la validación alteran la inscripción.
        ->and($inscripcion->refresh()->estatus)->toBe($estadoAntes)
        ->and($inscripcion->estatus)->toBe(InscripcionEstatus::ENROLLED)
        ->and($inscripcion->auditorias()->where('accion', 'estado_cambiado')->count())->toBe(0);
});

test('la validacion exige monto exacto igual al monto_total del concepto', function () {
    $escenario = escenarioValidacionF8();
    $concepto = $escenario['concepto'];

    $this->actingAs($escenario['admisiones']);
    $pago = intentoF8($concepto, $escenario['admisiones']);

    // Alteración directa del monto (la captura lo impide; aquí se prueba la defensa del servicio).
    $pago->forceFill(['monto' => '1000.00'])->save();

    $this->actingAs($escenario['administracion']);
    expect(fn () => app(PagoService::class)->validar($pago, $escenario['administracion']))
        ->toThrow(PagoInvalidoException::class, 'no coincide');

    expect($pago->refresh()->estatus)->toBe(EstatusPago::PENDIENTE)
        ->and($pago->folio)->toBeNull()
        ->and($concepto->refresh()->estatus)->toBe(EstatusConceptoPago::PENDIENTE);
});

test('solo ADMINISTRACION (y ADMIN) validan pagos server-side', function () {
    $escenario = escenarioValidacionF8();
    $concepto = $escenario['concepto'];
    $controlEscolar = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $this->actingAs($escenario['admisiones']);
    $pago = intentoF8($concepto, $escenario['admisiones']);

    // ADMISIONES captura pero NUNCA valida (RB-6).
    $this->actingAs($escenario['admisiones']);
    expect(fn () => app(PagoService::class)->validar($pago, $escenario['admisiones']))
        ->toThrow(AuthorizationException::class);

    // CONTROL_ESCOLAR no valida (matriz §8.2, F9).
    $this->actingAs($controlEscolar);
    expect(fn () => app(PagoService::class)->validar($pago, $controlEscolar))
        ->toThrow(AuthorizationException::class);

    expect($pago->refresh()->estatus)->toBe(EstatusPago::PENDIENTE)
        ->and($pago->folio)->toBeNull();

    // ADMIN sí (Gate::before, D1).
    $this->actingAs($admin);
    $validado = app(PagoService::class)->validar($pago, $admin);

    expect($validado->estatus)->toBe(EstatusPago::VALIDADO)
        ->and($validado->validado_por)->toBe($admin->id);
});

test('el componente de validacion devuelve 403 a ADMISIONES y a CONTROL_ESCOLAR', function () {
    $escenario = escenarioValidacionF8();
    $inscripcion = $escenario['inscripcion'];
    $controlEscolar = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    $this->actingAs($escenario['admisiones']);
    $pago = intentoF8($escenario['concepto'], $escenario['admisiones']);

    Livewire::test(ModalValidarPago::class, ['inscripcion' => $inscripcion])
        ->call('validar', $pago->id)
        ->assertForbidden();

    $this->actingAs($controlEscolar);
    Livewire::test(ModalValidarPago::class, ['inscripcion' => $inscripcion])
        ->call('validar', $pago->id)
        ->assertForbidden();

    expect($pago->refresh()->estatus)->toBe(EstatusPago::PENDIENTE)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::PAYMENT_REVIEW);
});

test('un pago ya VALIDADO no puede validarse otra vez', function () {
    $escenario = escenarioValidacionF8();
    $this->actingAs($escenario['admisiones']);
    $pago = intentoF8($escenario['concepto'], $escenario['admisiones']);

    $this->actingAs($escenario['administracion']);
    app(PagoService::class)->validar($pago, $escenario['administracion']);
    $folio = $pago->refresh()->folio;

    expect(fn () => app(PagoService::class)->validar($pago, $escenario['administracion']))
        ->toThrow(PagoInvalidoException::class, 'Validado');

    expect($pago->refresh()->folio)->toBe($folio)   // inmutable
        ->and(Pago::query()->count())->toBe(1);
});

test('un pago RECHAZADO no puede validarse', function () {
    $escenario = escenarioValidacionF8();
    $this->actingAs($escenario['admisiones']);
    $pago = intentoF8($escenario['concepto'], $escenario['admisiones']);

    $this->actingAs($escenario['administracion']);
    app(PagoService::class)->rechazar($pago, 'Motivo sintetico para no validar', $escenario['administracion']);

    expect(fn () => app(PagoService::class)->validar($pago, $escenario['administracion']))
        ->toThrow(PagoInvalidoException::class, 'Rechazado');

    expect($pago->refresh()->estatus)->toBe(EstatusPago::RECHAZADO)
        ->and($pago->folio)->toBeNull()
        ->and($escenario['concepto']->refresh()->estatus)->toBe(EstatusConceptoPago::PENDIENTE);
});

test('ROLLBACK TOTAL si falla la transicion: no queda folio ni concepto pagado ni auditoria', function () {
    $escenario = escenarioValidacionF8();
    $inscripcion = $escenario['inscripcion'];
    $concepto = $escenario['concepto'];

    $this->actingAs($escenario['admisiones']);
    $pago = intentoF8($concepto, $escenario['admisiones']);

    // Estado real alterado: la inscripción YA NO está en PAYMENT_REVIEW.
    $inscripcion->forceFill(['estatus' => InscripcionEstatus::PENDING_DOCUMENTS->value])->save();

    $this->actingAs($escenario['administracion']);
    expect(fn () => app(PagoService::class)->validar($pago, $escenario['administracion']))
        ->toThrow(PagoInvalidoException::class, 'Pago en revisión');

    // ROLLBACK TOTAL: se intentó escribir folio + concepto + auditoría antes
    // del fallo y TODO se deshizo (sin folio huérfano ni avance parcial).
    expect($pago->refresh()->estatus)->toBe(EstatusPago::PENDIENTE)
        ->and($pago->folio)->toBeNull()
        ->and($pago->validado_at)->toBeNull()
        ->and($concepto->refresh()->estatus)->toBe(EstatusConceptoPago::PENDIENTE)
        ->and($concepto->pagado_at)->toBeNull()
        ->and($pago->auditorias()->where('accion', 'pago_validado')->count())->toBe(0)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_DOCUMENTS);
});

test('ROLLBACK TOTAL si falla la escritura del responsable/auditoria (FK real de MySQL)', function () {
    $escenario = escenarioValidacionF8();
    $inscripcion = $escenario['inscripcion'];
    $concepto = $escenario['concepto'];

    $this->actingAs($escenario['admisiones']);
    $pago = intentoF8($concepto, $escenario['admisiones']);

    // Usuario fantasma: la FK a `users` falla DENTRO de la transacción de
    // validación (responsable/auditoría), sin mocks.
    $fantasma = new User();
    $fantasma->forceFill(['id' => 99999999, 'name' => 'Fantasma', 'email' => 'fantasma-f8@uvz.local']);
    $fantasma->id = 99999999;

    $this->actingAs($escenario['administracion']);
    expect(fn () => app(PagoService::class)->validar($pago, $fantasma))
        ->toThrow(QueryException::class);

    expect($pago->refresh()->estatus)->toBe(EstatusPago::PENDIENTE)
        ->and($pago->folio)->toBeNull()                 // sin folio huérfano
        ->and($concepto->refresh()->estatus)->toBe(EstatusConceptoPago::PENDIENTE)
        ->and($pago->auditorias()->count())->toBe(1)    // solo la del registro
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::PAYMENT_REVIEW);
});
