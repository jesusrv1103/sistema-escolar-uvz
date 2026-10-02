<?php

use App\Enums\EstatusPago;
use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Exceptions\PagoInvalidoException;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\User;
use App\Services\InscripcionService;
use App\Services\PagoService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Fase 8 — U9/U10: folio de pago (RB-10)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| Formato exacto `PAG-YYYYMMDD-########`: fecha de VALIDACIÓN con el
| timezone de la app (America/Mexico_City) + ID del pago con padding a 8
| dígitos. Sin tabla consecutivos, sin MAX(), sin NumeroControlService.
*/

/** Escenario: inscripción con cargos y concepto INSCRIPCION PENDIENTE. */
function escenarioFolioF8(): array
{
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();

    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::DATA_COMPLETE)->create();
    app(InscripcionService::class)->generarCargos($inscripcion, $admisiones);

    $concepto = $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail();

    return compact('admisiones', 'administracion', 'inscripcion', 'concepto');
}

test('U9: el folio tiene el formato exacto PAG-YYYYMMDD-######## con la fecha de validacion', function () {
    $escenario = escenarioFolioF8();
    $concepto = $escenario['concepto'];

    $this->travelTo(Carbon::parse('2026-10-01 10:30:00'));   // timezone de la aplicación

    $this->actingAs($escenario['admisiones']);
    $pago = app(PagoService::class)->registrar($concepto, [
        'monto' => $concepto->monto_total,
        'fecha_pago' => '2026-09-30',   // NO es la fecha que define el folio
        'metodo' => 'EFECTIVO',
    ], $escenario['admisiones']);

    expect($pago->folio)->toBeNull();   // el folio solo nace al validar

    $this->actingAs($escenario['administracion']);
    $validado = app(PagoService::class)->validar($pago, $escenario['administracion']);

    $esperado = sprintf('PAG-20261001-%s', str_pad((string) $validado->id, 8, '0', STR_PAD_LEFT));

    expect($validado->folio)->toBe($esperado)
        ->and($validado->folio)->toMatch('/^PAG-\d{8}-\d{8}$/')
        ->and(mb_strlen($validado->folio))->toBeLessThanOrEqual(30)
        ->and($validado->validado_at->format('Ymd'))->toBe('20261001')
        // El ID garantiza la unicidad lógica: dos pagos distintos ⇒ dos folios.
        ->and($validado->folio)->not->toBe(sprintf('PAG-20261001-%s', str_pad((string) ($validado->id + 1), 8, '0', STR_PAD_LEFT)));
});

test('U10: dos pagos validados tienen folios distintos', function () {
    $escenario = escenarioFolioF8();
    $inscripcion = $escenario['inscripcion'];
    $concepto = $escenario['concepto'];
    $mensualidad = $inscripcion->conceptosPago()->where('tipo', 'MENSUALIDAD')->orderBy('numero')->firstOrFail();

    $this->actingAs($escenario['admisiones']);
    $servicio = app(PagoService::class);
    $pago1 = $servicio->registrar($concepto, [
        'monto' => $concepto->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
    ], $escenario['admisiones']);
    $pago2 = $servicio->registrar($mensualidad, [
        'monto' => $mensualidad->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
    ], $escenario['admisiones']);

    $this->actingAs($escenario['administracion']);
    $validado1 = $servicio->validar($pago1, $escenario['administracion']);
    $validado2 = $servicio->validar($pago2, $escenario['administracion']);

    expect($validado1->folio)->not->toBe($validado2->folio)
        ->and($validado1->folio)->toMatch('/^PAG-\d{8}-\d{8}$/')
        ->and($validado2->folio)->toMatch('/^PAG-\d{8}-\d{8}$/');
});

test('U10: un rechazo jamas genera folio', function () {
    $escenario = escenarioFolioF8();
    $concepto = $escenario['concepto'];

    $this->actingAs($escenario['admisiones']);
    $pago = app(PagoService::class)->registrar($concepto, [
        'monto' => $concepto->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
    ], $escenario['admisiones']);

    $this->actingAs($escenario['administracion']);
    $rechazado = app(PagoService::class)->rechazar($pago, 'Motivo sintetico de rechazo F8', $escenario['administracion']);

    expect($rechazado->estatus)->toBe(EstatusPago::RECHAZADO)
        ->and($rechazado->folio)->toBeNull()
        ->and($rechazado->validado_por)->toBeNull()
        ->and($rechazado->validado_at)->toBeNull()
        ->and(Pago::query()->whereNotNull('folio')->count())->toBe(0);
});

test('U10: el folio es inmutable una vez asignado', function () {
    $escenario = escenarioFolioF8();
    $concepto = $escenario['concepto'];

    $this->actingAs($escenario['admisiones']);
    $pago = app(PagoService::class)->registrar($concepto, [
        'monto' => $concepto->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
    ], $escenario['admisiones']);

    $this->actingAs($escenario['administracion']);
    $validado = app(PagoService::class)->validar($pago, $escenario['administracion']);
    $folioOriginal = $validado->folio;

    // Ni re-validar ni rechazar alteran el folio ya emitido.
    expect(fn () => app(PagoService::class)->validar($validado, $escenario['administracion']))
        ->toThrow(PagoInvalidoException::class);
    expect(fn () => app(PagoService::class)->rechazar($validado, 'Intento de rechazo post validacion', $escenario['administracion']))
        ->toThrow(PagoInvalidoException::class);

    expect($validado->refresh()->folio)->toBe($folioOriginal)
        ->and($validado->estatus)->toBe(EstatusPago::VALIDADO);
});

test('U10: el UNIQUE de folio protegido por la base de datos rechaza duplicados', function () {
    $escenario = escenarioFolioF8();
    $concepto = $escenario['concepto'];
    $usuario = $escenario['administracion'];

    $datos = [
        'concepto_pago_id' => $concepto->id,
        'monto' => '1500.00',
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
        'estatus' => 'VALIDADO',
        'folio' => 'PAG-20261001-00000001',
        'registrado_por' => $usuario->id,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('pagos')->insert($datos);

    // Defensa final (R12): la segunda inserción con el MISMO folio falla.
    expect(fn () => DB::table('pagos')->insert($datos))
        ->toThrow(QueryException::class);

    expect(Pago::query()->where('folio', 'PAG-20261001-00000001')->count())->toBe(1);
});
