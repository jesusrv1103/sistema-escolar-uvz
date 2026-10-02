<?php

use App\Enums\EstatusConvenio;
use App\Enums\RolUsuario;
use App\Enums\TipoConvenio;
use App\Models\Convenio;
use App\Models\Inscripcion;
use App\Models\User;
use App\Services\ConvenioService;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Fase 7 — U4, U5, U6: cálculo del descuento de convenio (RB-17)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| Regla UVZ: el convenio aplica únicamente sobre el concepto INSCRIPCION;
| el descuento se congela en `monto_descuento` al aprobar, con redondeo
| monetario a 2 decimales y nunca por encima del monto de inscripción.
*/

/** Convenio sintético del catálogo (vigencia abierta salvo indicación). */
function convenioCalculo(TipoConvenio $tipo, string $valor): Convenio
{
    return Convenio::create([
        'clave' => 'CAL'.Str::random(6),
        'nombre' => 'Convenio sintetico de calculo',
        'tipo' => $tipo->value,
        'valor' => $valor,
        'fecha_inicio' => null,
        'fecha_fin' => null,
        'activo' => true,
    ]);
}

/** Inscripción sintética con monto de inscripción configurable. */
function inscripcionCalculo(string $montoInscripcion): Inscripcion
{
    return Inscripcion::factory()->create([
        'monto_inscripcion' => $montoInscripcion,
        'monto_descuento' => '0.00',
    ]);
}

test('U4: convenio PORCENTAJE calcula el descuento sobre monto_inscripcion con 2 decimales', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $servicio = app(ConvenioService::class);

    // 10% de 1500.00 = 150.00
    $this->actingAs($admisiones);
    $inscripcion = inscripcionCalculo('1500.00');
    $servicio->aplicar($inscripcion, convenioCalculo(TipoConvenio::PORCENTAJE, '10.00'), $admisiones);

    expect($inscripcion->refresh()->monto_descuento)->toBe('0.00');   // U6: aún sin aprobar

    $this->actingAs($administracion);
    $servicio->aprobar($inscripcion, $administracion);

    expect($inscripcion->refresh()->monto_descuento)->toBe('150.00');

    // Redondeo monetario: 33.33% de 999.99 = 333.2966… → 333.30
    $this->actingAs($admisiones);
    $otra = inscripcionCalculo('999.99');
    $servicio->aplicar($otra, convenioCalculo(TipoConvenio::PORCENTAJE, '33.33'), $admisiones);

    $this->actingAs($administracion);
    $servicio->aprobar($otra, $administracion);

    expect($otra->refresh()->monto_descuento)->toBe('333.30');
});

test('U5: convenio MONTO fijo jamas supera monto_inscripcion', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $servicio = app(ConvenioService::class);

    // Convenio de 2000 sobre inscripción de 1500 → descuento limitado a 1500
    // (el monto_total nunca queda negativo).
    $this->actingAs($admisiones);
    $inscripcion = inscripcionCalculo('1500.00');
    $servicio->aplicar($inscripcion, convenioCalculo(TipoConvenio::MONTO, '2000.00'), $admisiones);

    $this->actingAs($administracion);
    $servicio->aprobar($inscripcion, $administracion);

    expect((float) $inscripcion->refresh()->monto_descuento)->toBe(1500.00)
        ->and((float) $inscripcion->monto_descuento)->toBeLessThanOrEqual((float) $inscripcion->monto_inscripcion);

    // Convenio de 500 sobre inscripción de 1500 → descuento exacto de 500.
    $this->actingAs($admisiones);
    $otra = inscripcionCalculo('1500.00');
    $servicio->aplicar($otra, convenioCalculo(TipoConvenio::MONTO, '500.00'), $admisiones);

    $this->actingAs($administracion);
    $servicio->aprobar($otra, $administracion);

    expect($otra->refresh()->monto_descuento)->toBe('500.00');
});

test('U6: PENDIENTE_APROBACION no genera descuento', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $servicio = app(ConvenioService::class);

    $this->actingAs($admisiones);
    $inscripcion = inscripcionCalculo('1500.00');
    $servicio->aplicar($inscripcion, convenioCalculo(TipoConvenio::PORCENTAJE, '50.00'), $admisiones);

    $inscripcion->refresh();

    expect($inscripcion->convenio_estatus)->toBe(EstatusConvenio::PENDIENTE_APROBACION)
        ->and($inscripcion->monto_descuento)->toBe('0.00');   // descuento 0 hasta la aprobación
});
