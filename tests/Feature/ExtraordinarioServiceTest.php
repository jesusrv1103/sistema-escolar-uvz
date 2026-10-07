<?php

use App\Enums\EstatusInscripcionMateria;
use App\Enums\RolUsuario;
use App\Exceptions\ExtraordinarioException;
use App\Models\Alumno;
use App\Models\Grupo;
use App\Models\InscripcionMateria;
use App\Models\Materia;
use App\Models\MatriculaPeriodo;
use App\Models\Periodo;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\Programa;
use App\Models\User;
use App\Services\ExtraordinarioService;
use App\Services\HistorialAcademicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('extraordinarios');

/*
|--------------------------------------------------------------------------
| Saneamiento pre-2.0-17 — EXTRAORDINARIO (A) y creditosAprobados (C)
|--------------------------------------------------------------------------
|
| El enum PHP `EstatusInscripcionMateria::EXTRAORDINARIO` y `ExtraordinarioService`
| existían desde 2.0-07, pero `inscripciones_materias.estatus` era un ENUM de
| MySQL sin ese valor: `iniciar()` no podía persistir el estado («Data truncated
| for column 'estatus'»). La migración incremental
| `2026_10_06_000001_add_extraordinario_to_inscripciones_materias_table` lo habilita.
|
| Estas pruebas fijan además la regla aprobada (D-N4 + RB-A5) que reconcilia
| `creditosAprobados()`:
|
|   * EXTRAORDINARIO en proceso → NO aporta créditos (sin resultado consolidado).
|   * Extraordinario aprobado   → `ExtraordinarioService` deja APROBADA con la
|                                 calificación sustituida → SÍ aporta, una sola vez.
|
*/

/** Escenario: alumno con una materia REPROBADA en un plan vigente. */
function escenario_extraordinario(): array
{
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $plan = PlanEstudio::factory()->paraPrograma($programa)->create();
    $version = PlanVersion::factory()->forPlanEstudio($plan)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($version)
        ->create(['activo' => true, 'creditos' => 4]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()
        ->create(['activo' => true, 'cupo' => 40]);
    $matricula = MatriculaPeriodo::factory()->activa()
        ->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)
        ->create(['plan_version_id' => $version->id]);
    $inscripcion = InscripcionMateria::factory()
        ->paraMatricula($matricula)->paraGrupo($grupo)
        ->create([
            'estatus' => EstatusInscripcionMateria::REPROBADA,
            'calificacion_final' => 50.00,
        ]);
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    return compact('alumno', 'materia', 'grupo', 'matricula', 'inscripcion', 'control');
}

test('A: iniciar() persiste EXTRAORDINARIO en la columna ENUM de MySQL', function () {
    $s = escenario_extraordinario();

    $resultado = app(ExtraordinarioService::class)->iniciar($s['inscripcion'], $s['control']);

    // Recarga desde la BD: si el ENUM no admitiese el valor, el UPDATE de la
    // transacción habría fallado con SQLSTATE 22001 y el estado no estaría aquí.
    $persistida = InscripcionMateria::findOrFail($s['inscripcion']->id);

    expect($persistida->estatus)->toBe(EstatusInscripcionMateria::EXTRAORDINARIO)
        ->and($persistida->estatus->value)->toBe('EXTRAORDINARIO')
        ->and($resultado->estatus)->toBe(EstatusInscripcionMateria::EXTRAORDINARIO)
        ->and($persistida->calificacion_final)->toBe('50.00'); // la ordinaria queda para trazabilidad
});

test('C: el EXTRAORDINARIO en proceso no aporta créditos aprobados', function () {
    $s = escenario_extraordinario();
    $service = app(ExtraordinarioService::class);
    $service->iniciar($s['inscripcion'], $s['control']);

    $historial = app(HistorialAcademicoService::class);

    expect($historial->creditosAprobados($s['alumno']))->toBe(0)
        ->and($historial->obtenerHistorial($s['alumno'])->pluck('estatus')->all())
        ->toContain('EXTRAORDINARIO');
});

test('C: el extraordinario aprobado consolida en APROBADA y aporta sus créditos', function () {
    $s = escenario_extraordinario();
    $service = app(ExtraordinarioService::class);
    $service->iniciar($s['inscripcion'], $s['control']);
    $service->capturarCalificacion($s['inscripcion'], 70.00, null, $s['control']);

    $consolidada = InscripcionMateria::findOrFail($s['inscripcion']->id);
    $historial = app(HistorialAcademicoService::class);

    // D-N4: la extraordinaria sustituye la final y el estatus queda resuelto.
    expect($consolidada->estatus)->toBe(EstatusInscripcionMateria::APROBADA)
        ->and((float) $consolidada->calificacion_final)->toBe(70.00)
        // RB-A5: aprobada → aporta sus 4 créditos, exactamente una vez.
        ->and($historial->creditosAprobados($s['alumno']))->toBe(4);
});

test('D-N4: sólo hay una oportunidad extraordinaria', function () {
    $s = escenario_extraordinario();
    $service = app(ExtraordinarioService::class);

    $service->iniciar($s['inscripcion'], $s['control']);
    $service->capturarCalificacion($s['inscripcion'], 40.00, null, $s['control']);

    // quedó REPROBADA (40 < 60) con el extraordinario ya consumido
    expect(InscripcionMateria::findOrFail($s['inscripcion']->id)->estatus)
        ->toBe(EstatusInscripcionMateria::REPROBADA);

    expect(fn () => $service->iniciar($s['inscripcion']->refresh(), $s['control']))
        ->toThrow(ExtraordinarioException::class);
});

test('D-N4: el extraordinario sólo se inicia desde REPROBADA', function () {
    $s = escenario_extraordinario();
    $s['inscripcion']->forceFill(['estatus' => EstatusInscripcionMateria::APROBADA->value])->save();

    expect(fn () => app(ExtraordinarioService::class)->iniciar($s['inscripcion'], $s['control']))
        ->toThrow(ExtraordinarioException::class);
});
