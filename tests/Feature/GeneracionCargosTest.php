<?php

use App\Enums\EstatusConceptoPago;
use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Enums\TipoConvenio;
use App\Exceptions\CargosInvalidosException;
use App\Livewire\Inscripciones\ModalAplicarConvenio;
use App\Livewire\Inscripciones\WizardInscripcion;
use App\Models\Convenio;
use App\Models\Inscripcion;
use App\Models\Periodo;
use App\Models\User;
use App\Services\ConvenioService;
use App\Services\InscripcionService;
use Illuminate\Database\QueryException;

/*
|--------------------------------------------------------------------------
| Fase 7 — Generación de cargos: RB-3 / FC-4 (1 INSCRIPCION + 4 MENSUALIDADES)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| Decisión UVZ: cuatrimestre → exactamente 4 mensualidades derivadas del
| mes de `periodos.fecha_inicio`, vencimiento el día configurado en
| `config/escolar.php`; el descuento de convenio aplica SOLO a INSCRIPCION.
*/

/** Inscripción sintética lista para generar cargos. */
function inscripcionCargos(array $sobrescribir = []): Inscripcion
{
    return Inscripcion::factory()->enEstatus(InscripcionEstatus::DATA_COMPLETE)->create($sobrescribir);
}

test('sin convenio genera exactamente 5 conceptos: 1 INSCRIPCION + 4 MENSUALIDADES', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($usuario);
    $inscripcion = inscripcionCargos();

    app(InscripcionService::class)->generarCargos($inscripcion, $usuario);

    $inscripcion->refresh();
    $conceptos = $inscripcion->conceptosPago()->orderBy('tipo')->orderBy('numero')->get();

    expect($conceptos)->toHaveCount(5)
        ->and($conceptos->where('tipo', 'INSCRIPCION'))->toHaveCount(1)
        ->and($conceptos->where('tipo', 'MENSUALIDAD'))->toHaveCount(4)
        ->and($conceptos->where('tipo', 'MENSUALIDAD')->pluck('numero')->sort()->values()->all())->toBe([1, 2, 3, 4]);

    // Sin descuentos: convenio no aplicado.
    foreach ($conceptos as $concepto) {
        expect((float) $concepto->descuento)->toBe(0.0)
            ->and($concepto->estatus)->toBe(EstatusConceptoPago::PENDIENTE)
            ->and($concepto->pagado_at)->toBeNull();
    }

    // Transición final + auditoría (misma transacción).
    expect($inscripcion->estatus)->toBe(InscripcionEstatus::PENDING_PAYMENT)
        ->and($inscripcion->auditorias()->where('accion', 'cargos_generados')->firstOrFail()->user_id)->toBe($usuario->id)
        ->and($inscripcion->auditorias()->where('accion', 'estado_cambiado')->where('estado_nuevo', 'PENDING_PAYMENT')->exists())->toBeTrue();
});

test('los vencimientos se derivan del periodo con el dia configurado', function () {
    config()->set('escolar.dia_vencimiento_mensualidad', 5);

    $usuario = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($usuario);
    $inscripcion = inscripcionCargos();   // periodo sintético: 2026-08-01

    app(InscripcionService::class)->generarCargos($inscripcion, $usuario);

    $mensualidades = $inscripcion->conceptosPago()
        ->where('tipo', 'MENSUALIDAD')->orderBy('numero')->get();

    expect($mensualidades->pluck('fecha_vencimiento')->map(fn ($fecha) => $fecha->format('d/m/Y'))->all())
        ->toBe(['05/08/2026', '05/09/2026', '05/10/2026', '05/11/2026']);

    // El concepto INSCRIPCION vence al iniciar el periodo (regla mínima
    // documentada: la arquitectura no fija otra fecha institucional).
    expect($inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail()
        ->fecha_vencimiento->format('d/m/Y'))->toBe('01/08/2026');
});

test('si el dia configurado no existe en el mes se usa el ultimo dia valido', function () {
    config()->set('escolar.dia_vencimiento_mensualidad', 31);

    $usuario = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($usuario);

    $periodo = Periodo::create([
        'clave' => 'FEB-2026',
        'nombre' => 'Periodo febrero sintetico',
        'fecha_inicio' => '2026-02-10',
        'fecha_fin' => '2026-06-30',
        'activo' => true,
    ]);
    $inscripcion = inscripcionCargos(['periodo_id' => $periodo->id]);

    app(InscripcionService::class)->generarCargos($inscripcion, $usuario);

    $fechas = $inscripcion->conceptosPago()
        ->where('tipo', 'MENSUALIDAD')->orderBy('numero')->get()
        ->pluck('fecha_vencimiento')->map(fn ($fecha) => $fecha->format('d/m/Y'))->all();

    // Feb (28 días) ← clamp; Mar (31); Abr (30); May (31).
    expect($fechas)->toBe(['28/02/2026', '31/03/2026', '30/04/2026', '31/05/2026']);
});

test('el concepto de mensualidad es legible con el mes del periodo', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($usuario);
    $inscripcion = inscripcionCargos();

    app(InscripcionService::class)->generarCargos($inscripcion, $usuario);

    $primera = $inscripcion->conceptosPago()->where('tipo', 'MENSUALIDAD')->orderBy('numero')->firstOrFail();

    expect($primera->concepto)->toBe('Mensualidad 1 - agosto 2026')
        ->and((float) $primera->monto)->toBe(1200.00)
        ->and($primera->monto_total)->toBe($primera->monto);
});

test('convenio aprobado descuenta SOLO el concepto INSCRIPCION', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $convenios = app(ConvenioService::class);

    $this->actingAs($admisiones);
    $inscripcion = inscripcionCargos(['monto_inscripcion' => '1500.00', 'monto_mensualidad' => '1200.00']);
    $convenio = Convenio::create([
        'clave' => 'DESC10',
        'nombre' => 'Beca sintetica 10%',
        'tipo' => TipoConvenio::PORCENTAJE->value,
        'valor' => '10.00',
        'fecha_inicio' => null,
        'fecha_fin' => null,
        'activo' => true,
    ]);
    $convenios->aplicar($inscripcion, $convenio, $admisiones);

    $this->actingAs($administracion);
    $convenios->aprobar($inscripcion, $administracion);

    $this->actingAs($admisiones);
    app(InscripcionService::class)->generarCargos($inscripcion, $admisiones);

    $inscripcionCobro = $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail();
    $mensualidades = $inscripcion->conceptosPago()->where('tipo', 'MENSUALIDAD')->get();

    expect((float) $inscripcionCobro->monto)->toBe(1500.00)
        ->and((float) $inscripcionCobro->descuento)->toBe(150.00)          // U4 integrado
        ->and((float) $inscripcionCobro->monto_total)->toBe(1350.00)
        ->and($mensualidades)->toHaveCount(4);

    // Las mensualidades NUNCA reciben descuento de convenio (decisión UVZ).
    foreach ($mensualidades as $mensualidad) {
        expect((float) $mensualidad->descuento)->toBe(0.0)
            ->and((float) $mensualidad->monto_total)->toBe(1200.00);
    }
});

test('convenio rechazado genera cargos sin descuento', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $convenios = app(ConvenioService::class);

    $this->actingAs($admisiones);
    $inscripcion = inscripcionCargos(['monto_inscripcion' => '1500.00']);
    $convenio = Convenio::create([
        'clave' => 'RECH50',
        'nombre' => 'Promocion sintetica rechazada',
        'tipo' => TipoConvenio::PORCENTAJE->value,
        'valor' => '50.00',
        'fecha_inicio' => null,
        'fecha_fin' => null,
        'activo' => true,
    ]);
    $convenios->aplicar($inscripcion, $convenio, $admisiones);

    $this->actingAs($administracion);
    $convenios->rechazar($inscripcion, 'Motivo sintetico de rechazo', $administracion);

    $this->actingAs($admisiones);
    app(InscripcionService::class)->generarCargos($inscripcion, $admisiones);

    $inscripcionCobro = $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail();

    expect($inscripcionCobro->descuento)->toBe('0.00')
        ->and($inscripcionCobro->monto_total)->toBe('1500.00')
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_PAYMENT);
});

test('una inscripcion en $0 genera el concepto INSCRIPCION en PAGADO y las mensualidades PENDIENTES', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($usuario);
    $inscripcion = inscripcionCargos(['monto_inscripcion' => '0.00']);

    app(InscripcionService::class)->generarCargos($inscripcion, $usuario);

    $cobro = $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail();
    $mensualidades = $inscripcion->conceptosPago()->where('tipo', 'MENSUALIDAD')->get();

    expect((float) $cobro->monto_total)->toBe(0.0)
        ->and($cobro->estatus)->toBe(EstatusConceptoPago::PAGADO)
        ->and($cobro->pagado_at)->not->toBeNull()
        ->and($mensualidades->pluck('estatus')->map(fn ($estatus) => $estatus->value)->unique()->all())->toBe([EstatusConceptoPago::PENDIENTE->value])
        ->and($mensualidades->pluck('pagado_at')->filter())->toHaveCount(0)
        // No se adelanta el flujo: Fase 8 continuará (PAYMENT_REVIEW, etc.).
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_PAYMENT);
});

test('la segunda generacion no duplica cargos ni cambia el estado', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($usuario);
    $inscripcion = inscripcionCargos();

    app(InscripcionService::class)->generarCargos($inscripcion, $usuario);

    expect(fn () => app(InscripcionService::class)->generarCargos($inscripcion, $usuario))
        ->toThrow(CargosInvalidosException::class);

    expect($inscripcion->conceptosPago()->count())->toBe(5)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_PAYMENT);
});

test('una inscripcion DATA_COMPLETE con cargos previos rechaza la generacion por duplicado', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($usuario);
    $inscripcion = inscripcionCargos();

    $inscripcion->conceptosPago()->create([
        'tipo' => 'INSCRIPCION',
        'numero' => null,
        'concepto' => 'Inscripción',
        'monto' => '1500.00',
        'descuento' => '0.00',
        'monto_total' => '1500.00',
        'fecha_vencimiento' => now()->toDateString(),
        'estatus' => 'PENDIENTE',
    ]);

    expect(fn () => app(InscripcionService::class)->generarCargos($inscripcion, $usuario))
        ->toThrow(CargosInvalidosException::class, 'no se crean duplicados');

    expect($inscripcion->conceptosPago()->count())->toBe(1)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::DATA_COMPLETE);
});

test('un fallo intermedio provoca ROLLBACK TOTAL (sin cargos, sin transicion ni auditoria falsa)', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($usuario);
    $inscripcion = inscripcionCargos();
    $auditoriasPrevias = $inscripcion->auditorias()->count();

    // Usuario inexistente: la inserción de auditoría viola la FK a `users`
    // DESPUÉS de crear los conceptos → fallo real en MySQL, sin mocks.
    $fantasma = new User();
    $fantasma->forceFill(['id' => 99999999, 'name' => 'Fantasma', 'email' => 'fantasma-f7@uvz.local']);
    $fantasma->id = 99999999;

    expect(fn () => app(InscripcionService::class)->generarCargos($inscripcion, $fantasma))
        ->toThrow(QueryException::class);

    // ROLLBACK TOTAL: ni media lista de mensualidades, ni avance, ni auditoría.
    expect($inscripcion->conceptosPago()->count())->toBe(0)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::DATA_COMPLETE)
        ->and($inscripcion->auditorias()->count())->toBe($auditoriasPrevias);
});

test('autorizacion server-side: solo ADMISIONES (y ADMIN) generan cargos', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());
    $inscripcion = inscripcionCargos();

    Livewire::test(WizardInscripcion::class, ['inscripcion' => $inscripcion])
        ->call('generarCargos')
        ->assertForbidden();

    expect($inscripcion->conceptosPago()->count())->toBe(0)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::DATA_COMPLETE);

    // ADMISIONES sí puede (matriz §8.2).
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    Livewire::test(WizardInscripcion::class, ['inscripcion' => $inscripcion])
        ->call('generarCargos')
        ->assertHasNoErrors();

    expect($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_PAYMENT)
        ->and($inscripcion->conceptosPago()->count())->toBe(5);
});

test('integracion: convenio aplicado y aprobado genera cargos con descuento congelado', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();

    // ADMISIONES aplica el convenio desde el modal.
    $this->actingAs($admisiones);
    $inscripcion = inscripcionCargos(['monto_inscripcion' => '1500.00']);
    $convenio = Convenio::create([
        'clave' => 'INT15',
        'nombre' => 'Convenio integracion 15%',
        'tipo' => TipoConvenio::PORCENTAJE->value,
        'valor' => '15.00',
        'fecha_inicio' => null,
        'fecha_fin' => null,
        'activo' => true,
    ]);

    Livewire::test(ModalAplicarConvenio::class, ['inscripcion' => $inscripcion])
        ->call('aplicar', $convenio->id)
        ->assertHasNoErrors();

    expect($inscripcion->refresh()->convenio_estatus)->toBe(App\Enums\EstatusConvenio::PENDIENTE_APROBACION);

    // ADMINISTRACION aprueba desde el modal (descuento congelado: 225.00).
    $this->actingAs($administracion);
    Livewire::test(ModalAplicarConvenio::class, ['inscripcion' => $inscripcion])
        ->call('aprobar');

    expect($inscripcion->refresh()->monto_descuento)->toBe('225.00');

    // ADMISIONES genera los cargos desde el wizard.
    $this->actingAs($admisiones);
    Livewire::test(WizardInscripcion::class, ['inscripcion' => $inscripcion])
        ->call('generarCargos')
        ->assertHasNoErrors();

    $cobro = $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail();
    expect((float) $cobro->descuento)->toBe(225.00)
        ->and((float) $cobro->monto_total)->toBe(1275.00)
        ->and($inscripcion->conceptosPago()->count())->toBe(5)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_PAYMENT);
});

test('integracion: sin convenio los cargos nacen sin descuento y sin bloqueos', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    $inscripcion = inscripcionCargos();

    Livewire::test(WizardInscripcion::class, ['inscripcion' => $inscripcion])
        ->call('generarCargos')
        ->assertHasNoErrors();

    $cobro = $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail();

    expect($cobro->descuento)->toBe('0.00')
        ->and($cobro->monto_total)->toBe('1500.00')
        ->and($inscripcion->conceptosPago()->count())->toBe(5)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_PAYMENT);
});
