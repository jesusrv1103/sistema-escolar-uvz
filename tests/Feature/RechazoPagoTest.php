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

/*
|--------------------------------------------------------------------------
| Fase 8 — Rechazo de pagos: RB-5 (F12)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| F12 = rechazo de pago de INSCRIPCION: motivo obligatorio, sin folio,
| concepto PENDIENTE, inscripción de vuelta a PENDING_PAYMENT y auditoría.
| El intento RECHAZADO NUNCA se borra: queda como historial y permite un
| nuevo intento. La MENSUALIDAD rechazada jamás altera la inscripción.
*/

/** Escenario: inscripción con cargos y un intento de pago PENDIENTE sobre INSCRIPCION. */
function escenarioRechazoF8(): array
{
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();

    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::DATA_COMPLETE)->create();
    app(InscripcionService::class)->generarCargos($inscripcion, $admisiones);

    $concepto = $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail();

    // El helper llama al servicio con sesión de ADMISIONES (Gate server-side);
    // cada prueba cambia de rol después con actingAs().
    auth()->login($admisiones);
    $pago = app(PagoService::class)->registrar($concepto, [
        'monto' => $concepto->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'TRANSFERENCIA',
        'referencia' => 'REF-887766',
    ], $admisiones);
    auth()->logout();

    return [
        'admisiones' => $admisiones,
        'administracion' => $administracion,
        'inscripcion' => $inscripcion->refresh(),   // refleja PENDING_PAYMENT + PAYMENT_REVIEW
        'concepto' => $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail(),
        'pago' => $pago,
    ];
}

test('F12: rechazar pago de INSCRIPCION devuelve la inscripcion a PENDING_PAYMENT sin folio', function () {
    $escenario = escenarioRechazoF8();
    ['administracion' => $administracion, 'inscripcion' => $inscripcion, 'concepto' => $concepto, 'pago' => $pago] = $escenario;

    expect($inscripcion->estatus)->toBe(InscripcionEstatus::PAYMENT_REVIEW);   // tras el registro

    $this->actingAs($administracion);
    $motivo = 'Comprobante ilegible, captura de nuevo';
    $rechazado = app(PagoService::class)->rechazar($pago, $motivo, $administracion);

    expect($rechazado->estatus)->toBe(EstatusPago::RECHAZADO)
        ->and($rechazado->folio)->toBeNull()                       // jamás genera folio
        ->and($rechazado->motivo_rechazo)->toBe($motivo)
        ->and($rechazado->validado_por)->toBeNull()
        ->and($rechazado->validado_at)->toBeNull()
        ->and($concepto->refresh()->estatus)->toBe(EstatusConceptoPago::PENDIENTE)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_PAYMENT)
        ->and($inscripcion->pagos_validados_at)->toBeNull();

    // Auditorías: pago_rechazado + la transición de retroceso con motivo.
    expect($rechazado->auditorias()->where('accion', 'pago_rechazado')->firstOrFail()->user_id)->toBe($administracion->id)
        ->and($rechazado->auditorias()->where('accion', 'pago_rechazado')->firstOrFail()->datos['motivo'])->toBe($motivo)
        ->and($inscripcion->auditorias()->where('accion', 'estado_cambiado')->where('estado_nuevo', 'PENDING_PAYMENT')->where('estado_anterior', 'PAYMENT_REVIEW')->exists())->toBeTrue()
        ->and($inscripcion->auditorias()->where('accion', 'estado_cambiado')->where('estado_nuevo', 'PENDING_PAYMENT')->where('estado_anterior', 'PAYMENT_REVIEW')->firstOrFail()->datos['motivo'])->toBe($motivo);
});

test('F12: el motivo es obligatorio, no puede ser solo espacios ni exceder 2000', function () {
    $escenario = escenarioRechazoF8();
    $pago = $escenario['pago'];
    $administracion = $escenario['administracion'];

    $this->actingAs($administracion);

    expect(fn () => app(PagoService::class)->rechazar($pago, '', $administracion))
        ->toThrow(PagoInvalidoException::class);
    expect(fn () => app(PagoService::class)->rechazar($pago, '     ', $administracion))
        ->toThrow(PagoInvalidoException::class);
    expect(fn () => app(PagoService::class)->rechazar($pago, str_repeat('x', 2001), $administracion))
        ->toThrow(PagoInvalidoException::class);

    // Nada se alteró con los intentos inválidos.
    expect($pago->refresh()->estatus)->toBe(EstatusPago::PENDIENTE)
        ->and($escenario['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PAYMENT_REVIEW);

    // El límite exacto (2000) sí se acepta.
    $rechazado = app(PagoService::class)->rechazar($pago, str_repeat('x', 2000), $administracion);
    expect($rechazado->estatus)->toBe(EstatusPago::RECHAZADO);
});

test('F12: el componente exige motivo no vacio para rechazar', function () {
    $escenario = escenarioRechazoF8();

    $this->actingAs($escenario['administracion']);
    Livewire::test(ModalValidarPago::class, ['inscripcion' => $escenario['inscripcion']])
        ->call('seleccionarRechazo', $escenario['pago']->id)
        ->set('motivo', '   ')
        ->call('rechazar')
        ->assertHasErrors('motivo');

    expect($escenario['pago']->refresh()->estatus)->toBe(EstatusPago::PENDIENTE)
        ->and($escenario['pago']->folio)->toBeNull();
});

test('F12: solo ADMINISTRACION (y ADMIN) rechazan; ADMISIONES recibe 403', function () {
    $escenario = escenarioRechazoF8();

    $this->actingAs($escenario['admisiones']);
    expect(fn () => app(PagoService::class)->rechazar($escenario['pago'], 'Motivo sintetico de rechazo', $escenario['admisiones']))
        ->toThrow(AuthorizationException::class);

    Livewire::test(ModalValidarPago::class, ['inscripcion' => $escenario['inscripcion']])
        ->call('seleccionarRechazo', $escenario['pago']->id)
        ->assertForbidden();

    expect($escenario['pago']->refresh()->estatus)->toBe(EstatusPago::PENDIENTE);

    // ADMIN sí (Gate::before).
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $this->actingAs($admin);
    $rechazado = app(PagoService::class)->rechazar($escenario['pago'], 'Motivo sintetico con ADMIN', $admin);
    expect($rechazado->estatus)->toBe(EstatusPago::RECHAZADO);
});

test('F12: tras el rechazo ADMISIONES registra un nuevo intento y el historial queda intacto', function () {
    $escenario = escenarioRechazoF8();
    ['admisiones' => $admisiones, 'administracion' => $administracion, 'concepto' => $concepto, 'pago' => $pago] = $escenario;

    $this->actingAs($administracion);
    $motivo = 'Datos bancarios incorrectos';
    app(PagoService::class)->rechazar($pago, $motivo, $administracion);

    // Nuevo intento permitido: el concepto sigue PENDIENTE y solo hay históricos RECHAZADO.
    $this->actingAs($admisiones);
    $nuevo = app(PagoService::class)->registrar($concepto, [
        'monto' => $concepto->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
    ], $admisiones);

    expect($nuevo->id)->not->toBe($pago->id)
        ->and($nuevo->estatus)->toBe(EstatusPago::PENDIENTE)
        ->and(Pago::query()->count())->toBe(2)
        ->and(Pago::query()->rechazado()->count())->toBe(1)
        ->and($pago->refresh()->estatus)->toBe(EstatusPago::RECHAZADO)   // histórico intacto
        ->and($pago->motivo_rechazo)->toBe($motivo)
        ->and($pago->folio)->toBeNull()
        ->and($escenario['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PAYMENT_REVIEW);
});

test('F12: los intentos rechazados se conservan historicos sin sobrescribirse', function () {
    $escenario = escenarioRechazoF8();
    ['admisiones' => $admisiones, 'administracion' => $administracion, 'concepto' => $concepto, 'pago' => $pago] = $escenario;

    $this->actingAs($administracion);
    app(PagoService::class)->rechazar($pago, 'Primer motivo sintetico', $administracion);

    // Segundo intento, también rechazado (dos históricos seguidos).
    $this->actingAs($admisiones);
    $segundo = app(PagoService::class)->registrar($concepto, [
        'monto' => $concepto->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
    ], $admisiones);

    $this->actingAs($administracion);
    app(PagoService::class)->rechazar($segundo, 'Segundo motivo sintetico', $administracion);

    expect(Pago::query()->count())->toBe(2)
        ->and(Pago::query()->rechazado()->count())->toBe(2)
        ->and(Pago::query()->validado()->count())->toBe(0)
        ->and($pago->refresh()->motivo_rechazo)->toBe('Primer motivo sintetico')
        ->and($segundo->refresh()->motivo_rechazo)->toBe('Segundo motivo sintetico')
        ->and($concepto->refresh()->estatus)->toBe(EstatusConceptoPago::PENDIENTE);
});

test('rechazar una MENSUALIDAD no cambia el estado de la inscripcion', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();

    // Inscripción avanzada (ENROLLED): la mensualidad no la mueve ni para atrás.
    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::ENROLLED)->create();
    $mensualidad = ConceptoPago::factory()->create(['inscripcion_id' => $inscripcion->id, 'tipo' => 'MENSUALIDAD']);

    $this->actingAs($admisiones);
    $pago = app(PagoService::class)->registrar($mensualidad, [
        'monto' => $mensualidad->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
    ], $admisiones);

    expect($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::ENROLLED);

    $this->actingAs($administracion);
    $rechazado = app(PagoService::class)->rechazar($pago, 'Mensualidad rechazada sintetica', $administracion);

    expect($rechazado->estatus)->toBe(EstatusPago::RECHAZADO)
        ->and($rechazado->folio)->toBeNull()
        ->and($mensualidad->refresh()->estatus)->toBe(EstatusConceptoPago::PENDIENTE)   // permite nuevo intento
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::ENROLLED)     // exactamente igual
        ->and($inscripcion->auditorias()->where('accion', 'estado_cambiado')->count())->toBe(0);

    // Y admite un nuevo intento posterior.
    $this->actingAs($admisiones);
    $nuevo = app(PagoService::class)->registrar($mensualidad, [
        'monto' => $mensualidad->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
    ], $admisiones);

    expect($nuevo->estatus)->toBe(EstatusPago::PENDIENTE)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::ENROLLED);
});
