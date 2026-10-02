<?php

use App\Enums\EstatusConvenio;
use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Enums\TipoConvenio;
use App\Exceptions\ConvenioInvalidoException;
use App\Livewire\Inscripciones\ModalAplicarConvenio;
use App\Livewire\Inscripciones\WizardInscripcion;
use App\Models\Auditoria;
use App\Models\Convenio;
use App\Models\Inscripcion;
use App\Models\User;
use App\Services\ConvenioService;
use App\Services\InscripcionService;

/*
|--------------------------------------------------------------------------
| Fase 7 — Convenios: F23, F24 y reglas four-eyes (RB-13, RB-17, §8.2)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| ADMISIONES aplica → PENDIENTE_APROBACION; ADMINISTRACION aprueba/rechaza;
| ADMIN pasa por Gate::before. Autorización siempre server-side.
*/

/** Convenio sintético del catálogo con sobrescritura opcional. */
function convenioF7(array $sobrescribir = []): Convenio
{
    static $contador = 0;
    $contador++;

    return Convenio::create($sobrescribir + [
        'clave' => 'F7'.$contador,
        'nombre' => 'Convenio de fase 7 '.$contador,
        'tipo' => TipoConvenio::PORCENTAJE->value,
        'valor' => '10.00',
        'fecha_inicio' => null,
        'fecha_fin' => null,
        'activo' => true,
    ]);
}

/** Inscripción sintética lista para resolver convenio (datos completos). */
function inscripcionF7(array $sobrescribir = []): Inscripcion
{
    return Inscripcion::factory()->enEstatus(InscripcionEstatus::DATA_COMPLETE)->create($sobrescribir);
}

test('F23: convenio pendiente bloquea generar cargos y PENDING_PAYMENT', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $convenios = app(ConvenioService::class);

    $this->actingAs($admisiones);
    $inscripcion = inscripcionF7();
    $convenios->aplicar($inscripcion, convenioF7(), $admisiones);

    expect($inscripcion->refresh()->convenio_estatus)->toBe(EstatusConvenio::PENDIENTE_APROBACION);

    // Servicio: bloqueo explícito (sin cargos ni transición).
    expect(fn () => app(InscripcionService::class)->generarCargos($inscripcion))
        ->toThrow(ConvenioInvalidoException::class, 'pendiente de aprobación');

    expect($inscripcion->conceptosPago()->count())->toBe(0)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::DATA_COMPLETE);

    // UI: el wizard reporta el bloqueo (server-side, sin avanzar).
    Livewire::test(WizardInscripcion::class, ['inscripcion' => $inscripcion])
        ->call('generarCargos')
        ->assertHasErrors('cargos');

    expect($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::DATA_COMPLETE);

    // Resuelto el convenio (aprobado), la generación procede con descuento.
    $this->actingAs($administracion);
    $convenios->aprobar($inscripcion, $administracion);

    $this->actingAs($admisiones);
    Livewire::test(WizardInscripcion::class, ['inscripcion' => $inscripcion])
        ->call('generarCargos')
        ->assertHasNoErrors();

    expect($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_PAYMENT)
        ->and($inscripcion->conceptosPago()->count())->toBe(5)
        ->and($inscripcion->conceptosPago()->firstOrFail()->descuento)->toBe('150.00');
});

test('F24: ADMISIONES no puede aprobar ni rechazar el convenio', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();

    $this->actingAs($admisiones);
    $inscripcion = inscripcionF7();
    app(ConvenioService::class)->aplicar($inscripcion, convenioF7(), $admisiones);

    // Four-eyes: el rol que aplica NO aprueba (403 server-side).
    Livewire::test(ModalAplicarConvenio::class, ['inscripcion' => $inscripcion])
        ->call('aprobar')
        ->assertForbidden();

    Livewire::test(ModalAplicarConvenio::class, ['inscripcion' => $inscripcion])
        ->set('motivo', 'Motivo sintetico de rechazo')
        ->call('rechazar')
        ->assertForbidden();

    expect($inscripcion->refresh()->convenio_estatus)->toBe(EstatusConvenio::PENDIENTE_APROBACION)
        ->and($inscripcion->monto_descuento)->toBe('0.00');

    // ADMINISTRACION sí aprueba (matriz §8.2).
    $this->actingAs($administracion);
    Livewire::test(ModalAplicarConvenio::class, ['inscripcion' => $inscripcion])
        ->call('aprobar');

    expect($inscripcion->refresh()->convenio_estatus)->toBe(EstatusConvenio::APROBADO);
});

test('ADMINISTRACION no aplica convenios (el papel de ADMISIONES no se asume)', function () {
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $convenio = convenioF7();

    $this->actingAs($administracion);
    $inscripcion = inscripcionF7();

    Livewire::test(ModalAplicarConvenio::class, ['inscripcion' => $inscripcion])
        ->call('aplicar', $convenio->id)
        ->assertForbidden();

    expect($inscripcion->refresh()->convenio_id)->toBeNull()
        ->and($inscripcion->convenio_estatus)->toBeNull();
});

test('un convenio inactivo o eliminado no puede aplicarse', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($admisiones);
    $servicio = app(ConvenioService::class);

    $inactivo = convenioF7(['activo' => false]);
    $inscripcion = inscripcionF7();

    expect(fn () => $servicio->aplicar($inscripcion, $inactivo, $admisiones))
        ->toThrow(ConvenioInvalidoException::class, 'inactivo');

    $eliminado = convenioF7();
    $eliminado->delete();

    expect(fn () => $servicio->aplicar($inscripcion, $eliminado, $admisiones))
        ->toThrow(ConvenioInvalidoException::class, 'eliminado');

    // También desde la UI: error junto al formulario, sin cambios en BD.
    Livewire::test(ModalAplicarConvenio::class, ['inscripcion' => $inscripcion])
        ->call('aplicar', $inactivo->id)
        ->assertHasErrors('convenio');

    expect($inscripcion->refresh()->convenio_id)->toBeNull();
});

test('un convenio fuera de vigencia no puede aplicarse', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($admisiones);
    $servicio = app(ConvenioService::class);

    $vencido = convenioF7(['fecha_inicio' => now()->subMonths(3)->toDateString(), 'fecha_fin' => now()->subDay()->toDateString()]);
    $futuro = convenioF7(['fecha_inicio' => now()->addDay()->toDateString(), 'fecha_fin' => now()->addMonth()->toDateString()]);
    $inscripcion = inscripcionF7();

    expect(fn () => $servicio->aplicar($inscripcion, $vencido, $admisiones))
        ->toThrow(ConvenioInvalidoException::class, 'ya venció');

    expect(fn () => $servicio->aplicar($inscripcion, $futuro, $admisiones))
        ->toThrow(ConvenioInvalidoException::class, 'aún no inicia');

    expect($inscripcion->refresh()->convenio_id)->toBeNull();
});

test('solo se admite un convenio por inscripcion', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($admisiones);
    $servicio = app(ConvenioService::class);

    $inscripcion = inscripcionF7();
    $servicio->aplicar($inscripcion, convenioF7(), $admisiones);

    expect(fn () => $servicio->aplicar($inscripcion, convenioF7(), $admisiones))
        ->toThrow(ConvenioInvalidoException::class, 'ya tiene un convenio');

    expect($inscripcion->refresh()->convenio_id)->not->toBeNull();
});

test('el convenio solo se aplica en etapa previa a la generacion de cargos', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($admisiones);
    $servicio = app(ConvenioService::class);

    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::PENDING_PAYMENT)->create();

    expect(fn () => $servicio->aplicar($inscripcion, convenioF7(), $admisiones))
        ->toThrow(ConvenioInvalidoException::class, 'antes de generar los cargos');

    // Con cargos previos tampoco se aplica (aunque el estatus lo permitiera).
    $conCargos = inscripcionF7();
    $conCargos->conceptosPago()->create([
        'tipo' => 'INSCRIPCION',
        'numero' => null,
        'concepto' => 'Inscripción',
        'monto' => '1500.00',
        'descuento' => '0.00',
        'monto_total' => '1500.00',
        'fecha_vencimiento' => now()->toDateString(),
        'estatus' => 'PENDIENTE',
    ]);

    expect(fn () => $servicio->aplicar($conCargos, convenioF7(), $admisiones))
        ->toThrow(ConvenioInvalidoException::class, 'ya tiene cargos');
});

test('la aprobacion guarda el descuento como snapshot inmutable', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $servicio = app(ConvenioService::class);

    $this->actingAs($admisiones);
    $convenio = convenioF7(['tipo' => TipoConvenio::PORCENTAJE->value, 'valor' => '10.00']);
    $inscripcion = inscripcionF7(['monto_inscripcion' => '1500.00']);
    $servicio->aplicar($inscripcion, $convenio, $admisiones);

    $this->actingAs($administracion);
    $servicio->aprobar($inscripcion, $administracion);

    expect($inscripcion->refresh()->monto_descuento)->toBe('150.00')
        ->and($inscripcion->convenio_aprobado_por)->toBe($administracion->id)
        ->and($inscripcion->convenio_aprobado_at)->not->toBeNull();

    // El catálogo cambia después: el descuento ya congelado no se mueve.
    $convenio->update(['valor' => '50.00']);
    expect($inscripcion->refresh()->monto_descuento)->toBe('150.00');
});

test('el rechazo exige motivo y deja descuento en 0', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $servicio = app(ConvenioService::class);

    $this->actingAs($admisiones);
    $inscripcion = inscripcionF7();
    $servicio->aplicar($inscripcion, convenioF7(['valor' => '50.00']), $admisiones);

    // Servicio: motivo obligatorio de 5 a 2000 caracteres.
    $this->actingAs($administracion);
    expect(fn () => $servicio->rechazar($inscripcion, 'cort', $administracion))
        ->toThrow(ConvenioInvalidoException::class, 'motivo');

    // UI: validación server-side del campo.
    Livewire::test(ModalAplicarConvenio::class, ['inscripcion' => $inscripcion])
        ->call('rechazar')
        ->assertHasErrors('motivo');

    Livewire::test(ModalAplicarConvenio::class, ['inscripcion' => $inscripcion])
        ->set('motivo', 'abc')
        ->call('rechazar')
        ->assertHasErrors('motivo');

    expect($inscripcion->refresh()->convenio_estatus)->toBe(EstatusConvenio::PENDIENTE_APROBACION);

    // Rechazo válido: descuento 0, motivo registrado, sin aprobador.
    Livewire::test(ModalAplicarConvenio::class, ['inscripcion' => $inscripcion])
        ->set('motivo', '  Motivo sintetico de rechazo  ')
        ->call('rechazar')
        ->assertHasNoErrors();

    $inscripcion->refresh();
    expect($inscripcion->convenio_estatus)->toBe(EstatusConvenio::RECHAZADO)
        ->and($inscripcion->monto_descuento)->toBe('0.00')
        ->and($inscripcion->convenio_motivo)->toBe('Motivo sintetico de rechazo')
        ->and($inscripcion->convenio_aprobado_por)->toBeNull()
        ->and($inscripcion->convenio_aprobado_at)->toBeNull();
});

test('toda operacion critica de convenio deja auditoria con su responsable', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $servicio = app(ConvenioService::class);

    // Aplicar → convenio_aplicado (ADMISIONES).
    $this->actingAs($admisiones);
    $aplicada = inscripcionF7();
    $servicio->aplicar($aplicada, convenioF7(), $admisiones);

    $registro = $aplicada->auditorias()->where('accion', 'convenio_aplicado')->firstOrFail();
    expect($registro->user_id)->toBe($admisiones->id)
        ->and($registro->datos['convenio_estatus'])->toBe('PENDIENTE_APROBACION');

    // Aprobar → convenio_aprobado (ADMINISTRACION) con el descuento congelado.
    $this->actingAs($administracion);
    $servicio->aprobar($aplicada, $administracion);

    $registro = $aplicada->auditorias()->where('accion', 'convenio_aprobado')->firstOrFail();
    expect($registro->user_id)->toBe($administracion->id)
        ->and($registro->datos['monto_descuento'])->toBe('150.00');

    // Rechazar → convenio_rechazado con motivo.
    $this->actingAs($admisiones);
    $rechazada = inscripcionF7();
    $servicio->aplicar($rechazada, convenioF7(), $admisiones);

    $this->actingAs($administracion);
    $servicio->rechazar($rechazada, 'Motivo sintetico de rechazo', $administracion);

    $registro = $rechazada->auditorias()->where('accion', 'convenio_rechazado')->firstOrFail();
    expect($registro->user_id)->toBe($administracion->id)
        ->and($registro->datos['motivo'])->toBe('Motivo sintetico de rechazo')
        ->and(Auditoria::count())->toBe(4);   // aplicar, aprobar, aplicar, rechazar
});

test('el modal lista unicamente convenios activos, no eliminados y vigentes', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    $inscripcion = inscripcionF7();

    convenioF7(['clave' => 'VIGF7', 'nombre' => 'Convenio Vigente F7']);
    convenioF7(['clave' => 'INAF7', 'nombre' => 'Convenio Inactivo F7', 'activo' => false]);
    convenioF7(['clave' => 'VENF7', 'nombre' => 'Convenio Vencido F7', 'fecha_fin' => now()->subDay()->toDateString()]);
    convenioF7(['clave' => 'ELIF7', 'nombre' => 'Convenio Eliminado F7'])->delete();

    Livewire::test(ModalAplicarConvenio::class, ['inscripcion' => $inscripcion])
        ->call('abrir')
        ->assertSee('Convenio Vigente F7')
        ->assertDontSee('Convenio Inactivo F7')
        ->assertDontSee('Convenio Vencido F7')
        ->assertDontSee('Convenio Eliminado F7');
});

test('el modal no expone informacion medica', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($usuario);

    $inscripcion = inscripcionF7();
    $inscripcion->alumno->informacionMedica()->create([
        'alergias' => 'ALERGIA-SECRETA-F7',
        'condiciones' => 'CONDICION-SECRETA-F7',
        'actualizado_por' => $usuario->id,
    ]);

    Livewire::test(ModalAplicarConvenio::class, ['inscripcion' => $inscripcion])
        ->call('abrir')
        ->assertDontSee('ALERGIA-SECRETA-F7')
        ->assertDontSee('CONDICION-SECRETA-F7');
});
