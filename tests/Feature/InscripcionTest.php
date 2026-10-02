<?php

use App\Enums\EstatusConvenio;
use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Models\Alumno;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/*
|--------------------------------------------------------------------------
| Fase 5 — Inscripción: esquema, unicidad, casts, relaciones y autorización
|--------------------------------------------------------------------------
*/

test('UNIQUE alumno + periodo impide dos inscripciones del mismo periodo', function () {
    $alumno = Alumno::factory()->create();
    Inscripcion::factory()->create(['alumno_id' => $alumno->id]);

    expect(fn () => Inscripcion::factory()->create(['alumno_id' => $alumno->id]))
        ->toThrow(UniqueConstraintViolationException::class);

    // Otro alumno sí puede inscribirse en el mismo periodo.
    Inscripcion::factory()->create();
    expect(Inscripcion::count())->toBe(2);
});

test('estatus no es mas-asignable: solo la maquina de estados lo cambia', function () {
    $base = Inscripcion::factory()->create();   // brinda periodo y programa sintéticos

    $inscripcion = Inscripcion::create([
        'alumno_id' => Alumno::factory()->create()->id,
        'periodo_id' => $base->periodo_id,
        'programa_id' => $base->programa_id,
        'estatus' => InscripcionEstatus::ENROLLED->value,
        'monto_inscripcion' => '1500.00',
        'monto_mensualidad' => '1200.00',
        'creado_por' => User::factory()->create()->id,
    ]);

    expect($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::DRAFT)   // default de columna
        ->and(array_keys($inscripcion->getFillable()))
        ->not->toContain('estatus', 'finalizada_at', 'cancelacion_motivo', 'validada_por');
});

test('los casts aplican enums, decimales y fechas', function () {
    $inscripcion = Inscripcion::factory()
        ->state(['monto_inscripcion' => '1500.5', 'monto_descuento' => '199.9'])
        ->create();

    $inscripcion->forceFill(['convenio_estatus' => EstatusConvenio::PENDIENTE_APROBACION->value])->save();
    $inscripcion->refresh();

    expect($inscripcion->estatus)->toBeInstanceOf(InscripcionEstatus::class)
        ->and($inscripcion->estatus)->toBe(InscripcionEstatus::DRAFT)
        ->and($inscripcion->convenio_estatus)->toBeInstanceOf(EstatusConvenio::class)
        ->and($inscripcion->monto_inscripcion)->toBe('1500.50')       // decimal(10,2)
        ->and($inscripcion->monto_mensualidad)->toBe('1200.00')
        ->and($inscripcion->monto_descuento)->toBe('199.90')
        ->and($inscripcion->convenio_aplicado_at)->toBeNull()
        ->and($inscripcion->finalizada_at)->toBeNull()
        ->and($inscripcion->monto_descuento)->toBeNumeric();
});

test('las relaciones principales de la inscripcion quedan conectadas', function () {
    $inscripcion = Inscripcion::factory()->create();

    expect($inscripcion->alumno)->toBeInstanceOf(Alumno::class)
        ->and($inscripcion->alumno->id)->toBe($inscripcion->alumno_id)
        ->and($inscripcion->programa)->not->toBeNull()
        ->and($inscripcion->periodo)->not->toBeNull()
        ->and($inscripcion->creadoPor)->toBeInstanceOf(User::class)
        ->and($inscripcion->auditorias()->count())->toBe(0);
});

test('el soft delete de la inscripcion no toca alumno programa ni periodo', function () {
    $inscripcion = Inscripcion::factory()->create(['monto_inscripcion' => '900.00']);

    $alumnoId = $inscripcion->alumno_id;
    $programaId = $inscripcion->programa_id;
    $periodoId = $inscripcion->periodo_id;

    $inscripcion->delete();

    expect($inscripcion->refresh()->trashed())->toBeTrue()
        ->and(Inscripcion::count())->toBe(0)
        ->and(Inscripcion::withTrashed()->count())->toBe(1)
        ->and(Alumno::find($alumnoId))->not->toBeNull()
        ->and(App\Models\Programa::find($programaId))->not->toBeNull()
        ->and(App\Models\Periodo::find($periodoId))->not->toBeNull()
        ->and(App\Models\Programa::find($programaId)->deleted_at)->toBeNull()
        ->and(App\Models\Periodo::find($periodoId)->deleted_at)->toBeNull();
});

test('InscripcionPolicy resuelve sobre el modelo real segun la matriz', function () {
    $inscripcion = Inscripcion::factory()->create();

    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $controlEscolar = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    expect($admisiones->can('create', $inscripcion))->toBeTrue()
        ->and($admisiones->can('update', $inscripcion))->toBeTrue()
        ->and($administracion->can('create', $inscripcion))->toBeFalse()
        ->and($administracion->can('update', $inscripcion))->toBeFalse()
        ->and($controlEscolar->can('view', $inscripcion))->toBeTrue()
        ->and($controlEscolar->can('update', $inscripcion))->toBeFalse()
        ->and($admisiones->can('finalizar', $inscripcion))->toBeFalse()   // solo CONTROL_ESCOLAR (§8.2)
        ->and($controlEscolar->can('finalizar', $inscripcion))->toBeTrue();
});
