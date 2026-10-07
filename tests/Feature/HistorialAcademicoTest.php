<?php

use App\Models\Alumno;
use App\Models\Calificacion;
use App\Models\Equivalencia;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\InscripcionMateria;
use App\Models\Materia;
use App\Models\MatriculaPeriodo;
use App\Models\Periodo;
use App\Models\PlanVersion;
use App\Models\Programa;
use App\Models\PlanEstudio;
use App\Models\User;
use App\Services\HistorialAcademicoService;
use App\Enums\EstatusEquivalencia;
use App\Enums\EstatusInscripcionMateria;
use App\Enums\RolUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class)->group('historial-academico');

beforeEach(function () {
    foreach ([
        \Database\Factories\ProgramaFactory::class,
        \Database\Factories\PlanEstudioFactory::class,
        \Database\Factories\PlanVersionFactory::class,
        \Database\Factories\MateriaFactory::class,
        \Database\Factories\GrupoFactory::class,
        \Database\Factories\GrupoHorarioFactory::class,
        \Database\Factories\EvaluacionFactory::class,
        \Database\Factories\BecaFactory::class,
        \Database\Factories\ConvenioFactory::class,
        \Database\Factories\DocenteFactory::class,
        \Database\Factories\AulaFactory::class,
        \Database\Factories\TipoDocumentoFactory::class,
    ] as $factory) {
        if (method_exists($factory, 'resetCounter')) {
            $factory::resetCounter();
        } else if (property_exists($factory, 'i')) {
            $factory::$i = 0;
        }
    }
});

test('historial académico vacío para alumno sin inscripciones', function () {
    $alumno = Alumno::factory()->create();
    $service = app(HistorialAcademicoService::class);
    
    $historial = $service->obtenerHistorial($alumno);
    
    expect($historial)->toBeEmpty();
});

test('promedio es null para alumno sin calificaciones', function () {
    $alumno = Alumno::factory()->create();
    $service = app(HistorialAcademicoService::class);
    
    $promedio = $service->calcularPromedio($alumno);
    
    expect($promedio)->toBeNull();
});

test('promedio ponderado por créditos con materias aprobadas y reprobadas', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia1 = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'creditos' => 4]);
    $materia2 = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'creditos' => 6]);
    $grupo1 = Grupo::factory()->paraMateria($materia1)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
    $grupo2 = Grupo::factory()->paraMateria($materia2)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
    
    $matricula = MatriculaPeriodo::factory()->activa()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create([
            'plan_version_id' => $planVersion->id,
        ]);
    $inscripcion1 = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo1)->cursando()->create();
    $inscripcion2 = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo2)->cursando()->create();
    
    $eval1 = Evaluacion::factory()->create(['grupo_id' => $grupo1->id, 'ponderacion' => 100]);
    $eval2 = Evaluacion::factory()->create(['grupo_id' => $grupo2->id, 'ponderacion' => 100]);
    
    // Materia 1: 4 créditos, calificación 80
    Calificacion::factory()->create([
        'inscripcion_materia_id' => $inscripcion1->id,
        'evaluacion_id' => $eval1->id,
        'valor' => 80.00,
    ]);
    $inscripcion1->update(['calificacion_final' => 80.00, 'estatus' => EstatusInscripcionMateria::APROBADA]);
    
    // Materia 2: 6 créditos, calificación 60
    Calificacion::factory()->create([
        'inscripcion_materia_id' => $inscripcion2->id,
        'evaluacion_id' => $eval2->id,
        'valor' => 60.00,
    ]);
    $inscripcion2->update(['calificacion_final' => 60.00, 'estatus' => EstatusInscripcionMateria::APROBADA]);
    
    $service = app(HistorialAcademicoService::class);
    $promedio = $service->calcularPromedio($alumno);
    
    // (80 * 4 + 60 * 6) / (4 + 6) = (320 + 360) / 10 = 68.0
    expect($promedio)->toBe(68.0);
});

test('créditos aprobados solo cuenta materias APROBADA', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia1 = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'creditos' => 4]);
    $materia2 = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'creditos' => 6]);
    $materia3 = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'creditos' => 4]);
    $grupo1 = Grupo::factory()->paraMateria($materia1)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
    $grupo2 = Grupo::factory()->paraMateria($materia2)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
    $grupo3 = Grupo::factory()->paraMateria($materia3)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
    
    $matricula = MatriculaPeriodo::factory()->activa()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create([
            'plan_version_id' => $planVersion->id,
        ]);
    $inscripcion1 = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo1)->cursando()->create();
    $inscripcion2 = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo2)->cursando()->create();
    $inscripcion3 = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo3)->cursando()->create();
    
    // Materia 1: APROBADA 4 créditos
    $eval1 = Evaluacion::factory()->create(['grupo_id' => $grupo1->id, 'ponderacion' => 100]);
    Calificacion::factory()->create(['inscripcion_materia_id' => $inscripcion1->id, 'evaluacion_id' => $eval1->id, 'valor' => 80.00]);
    $inscripcion1->update(['calificacion_final' => 80.00, 'estatus' => EstatusInscripcionMateria::APROBADA]);
    
    // Materia 2: REPROBADA 6 créditos
    $eval2 = Evaluacion::factory()->create(['grupo_id' => $grupo2->id, 'ponderacion' => 100]);
    Calificacion::factory()->create(['inscripcion_materia_id' => $inscripcion2->id, 'evaluacion_id' => $eval2->id, 'valor' => 50.00]);
    $inscripcion2->update(['calificacion_final' => 50.00, 'estatus' => EstatusInscripcionMateria::REPROBADA]);
    
    // Materia 3: CURSANDO (sin calificación final)
    $eval3 = Evaluacion::factory()->create(['grupo_id' => $grupo3->id, 'ponderacion' => 100]);
    
    $service = app(HistorialAcademicoService::class);
    $creditosAprobados = $service->creditosAprobados(Alumno::find($alumno->id));
    
    expect($creditosAprobados)->toBe(4); // Solo la materia aprobada
});

test('avance académico calcula correctamente', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    
    // 3 materias de 4 créditos cada una = 12 créditos totales
    $materias = Materia::factory()->count(3)->paraPlanVersion($planVersion)->create(['activo' => true, 'creditos' => 4]);
    
    $grupos = $materias->map(fn($m) => Grupo::factory()->paraMateria($m)->paraPeriodo(Periodo::factory()->create(['activo' => true]))->abierto()->create(['activo' => true, 'cupo' => 40]));
    
    $matricula = MatriculaPeriodo::factory()->activa()->paraAlumno($alumno)->paraPeriodo(Periodo::factory()->create(['activo' => true]))->paraPrograma(Programa::factory()->create(['activo' => true]))->create();
    
    $service = app(HistorialAcademicoService::class);
    
    // Sin materias aprobadas, avance debe ser 0%
    expect($service->porcentajeAvance($alumno))->toBe(0.0);
});

test('la ficha de historial de un alumno responde y dibuja la tira de materias', function () {
    // Regresión: la tabla "Tira de materias" leía $m['es_equivalencia'],
    // clave que solo existe en el historial por periodo → 500 en /historial/{alumno}.
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'creditos' => 4]);
    Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
    MatriculaPeriodo::factory()->activa()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create([
        'plan_version_id' => $planVersion->id,
    ]);

    $this->actingAs($admin)
        ->get(route('historial.ver', $alumno))
        ->assertOk()
        ->assertSee('Tira de materias', false)
        ->assertSee($materia->clave, false);
});

test('el listado de historial abre la ficha del alumno seleccionado', function () {
    // Regresión pre-merge: `alumnoSeleccionado()` llamaba `$this->resetPage()`
    // sin `use WithPagination`, así que el clic en "Ver historial" devolvía
    // 500 (BadMethodCallException) y la ficha nunca se abría desde /historial.
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'creditos' => 4]);
    Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
    MatriculaPeriodo::factory()->activa()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create([
        'plan_version_id' => $planVersion->id,
    ]);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Academico\HistorialAcademico::class)
        ->call('alumnoSeleccionado', $alumno->id)
        ->assertSet('alumno.id', $alumno->id)
        ->assertSee('Tira de materias', false);

    // y el filtro conserva la paginación sin reventar
    Livewire::actingAs($admin)
        ->test(\App\Livewire\Academico\HistorialAcademico::class)
        ->set('busqueda', 'ZZZZ')
        ->call('limpiarFiltros')
        ->assertSet('busqueda', '');
});

/*
|--------------------------------------------------------------------------
| Regresión pre-2.0-17 — BUG-03: `tiraMaterias()` sin `es_reprobada`
|--------------------------------------------------------------------------
|
| `pdf/formatos/tira.blade.php` lee `$t['es_reprobada']` y el service no la
| entregaba → "Undefined array key" (HTTP 500) al emitir la TIRA con una
| matrícula que sí tiene versión de plan. El test previo no lo veía porque su
| matrícula de fábrica no tenía `plan_version_id` y la tira salía vacía.
|
| El contrato queda fijado completo para cada fila del plan:
| aprobada, reprobada, cursando, baja, extraordinario, equivalencia y
| pendiente.
|
*/

test('BUG-03: la tira entrega es_reprobada y todos los estados del plan', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $plan = PlanEstudio::factory()->paraPrograma($programa)->create();
    $version = PlanVersion::factory()->forPlanEstudio($plan)->vigente()->create();

    // EXTRAORDINARIO ya es persistible: la migración incremental
    // `2026_10_06_000001_add_extraordinario_to_inscripciones_materias_table`
    // amplió el ENUM `inscripciones_materias.estatus` (registro original del
    // hallazgo: docs/auditoria-pdfs-pre-2.0-17.md §5).
    $claves = ['APR', 'REP', 'CUR', 'BAJ', 'EQU', 'PEN', 'EXT'];
    $materias = [];
    foreach ($claves as $clave) {
        $materias[$clave] = Materia::factory()->paraPlanVersion($version)
            ->create(['activo' => true, 'clave' => $clave, 'creditos' => 4]);
    }

    $matricula = MatriculaPeriodo::factory()->activa()
        ->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)
        ->create(['plan_version_id' => $version->id]);

    $grupos = [];
    foreach ($claves as $clave) {
        $grupos[$clave] = Grupo::factory()->paraMateria($materias[$clave])
            ->paraPeriodo($periodo)->abierto()
            ->create(['activo' => true, 'cupo' => 40]);
    }

    $inscribir = fn (string $clave, EstatusInscripcionMateria $estatus, ?float $calificacion) => InscripcionMateria::factory()
        ->paraMatricula($matricula)->paraGrupo($grupos[$clave])
        ->create([
            'estatus' => $estatus,
            'calificacion_final' => $calificacion,
        ]);

    $inscribir('APR', EstatusInscripcionMateria::APROBADA, 92.00);
    $inscribir('REP', EstatusInscripcionMateria::REPROBADA, 45.00);
    $inscribir('CUR', EstatusInscripcionMateria::CURSANDO, null);
    $inscribir('BAJ', EstatusInscripcionMateria::BAJA, null);
    $inscribir('EXT', EstatusInscripcionMateria::EXTRAORDINARIO, 55.00);

    Equivalencia::create([
        'alumno_id' => $alumno->id,
        'materia_id' => $materias['EQU']->id,
        'origen' => 'Universidad sintética (DEMO-PRESENTACION-2026)',
        'creditos' => 4,
        'estatus' => EstatusEquivalencia::APROBADA,
        'autorizada_por' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()->id,
        'fecha_dictamen' => now()->format('Y-m-d'),
    ]);

    $tira = app(HistorialAcademicoService::class)
        ->tiraMaterias(Alumno::findOrFail($alumno->id))
        ->keyBy('materia.clave');

    expect($tira->keys()->all())->toEqualCanonicalizing($claves);

    $contrato = [
        'materia', 'estatus', 'calificacion_final', 'es_aprobada', 'es_reprobada',
        'es_cursando', 'es_extraordinario', 'es_equivalencia', 'es_baja',
    ];
    foreach ($tira as $claveFila => $fila) {
        expect($fila)->toHaveKeys($contrato, "fila {$claveFila}");
    }

    expect($tira['APR'])->toMatchArray(['estatus' => 'APROBADA', 'es_aprobada' => true, 'es_reprobada' => false, 'es_cursando' => false])
        ->and((float) $tira['APR']['calificacion_final'])->toBe(92.00);

    expect($tira['REP'])->toMatchArray(['estatus' => 'REPROBADA', 'es_aprobada' => false, 'es_reprobada' => true])
        ->and((float) $tira['REP']['calificacion_final'])->toBe(45.00);

    expect($tira['CUR'])->toMatchArray(['estatus' => 'CURSANDO', 'es_aprobada' => false, 'es_reprobada' => false, 'es_cursando' => true])
        ->and($tira['CUR']['calificacion_final'])->toBeNull();

    expect($tira['BAJ'])->toMatchArray(['estatus' => 'BAJA', 'es_baja' => true, 'es_aprobada' => false, 'es_reprobada' => false]);

    expect($tira['EXT'])->toMatchArray(['estatus' => 'EXTRAORDINARIO', 'es_extraordinario' => true, 'es_aprobada' => false, 'es_reprobada' => false]);

    expect($tira['EQU'])->toMatchArray(['estatus' => 'EQUIVALENCIA', 'es_equivalencia' => true, 'es_aprobada' => false]);

    expect($tira['PEN'])->toMatchArray(['estatus' => 'PENDIENTE', 'es_aprobada' => false, 'es_reprobada' => false, 'es_cursando' => false]);
});

/*
|--------------------------------------------------------------------------
| Saneamiento pre-2.0-17 — filtro de BAJA en obtenerHistorial() (B)
|--------------------------------------------------------------------------
|
| `obtenerHistorial()` filtraba con `->where('estatus', '!=', 'BAJA')`: una
| comparación contra un literal de texto sobre un atributo casteado a
| `EstatusInscripcionMateria` (BackedEnum). La comparación queda ahora
| explícita contra el caso del enum y la regla aprobada (RB-A5) se fija con
| prueba: BAJA no entra al kardex y no aporta ni promedio ni créditos.
|
*/

test('B: la BAJA no entra al historial y no aporta promedio ni créditos', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $plan = PlanEstudio::factory()->paraPrograma($programa)->create();
    $version = PlanVersion::factory()->forPlanEstudio($plan)->vigente()->create();

    $materiaAprobada = Materia::factory()->paraPlanVersion($version)->create(['activo' => true, 'clave' => 'APR', 'creditos' => 4]);
    $materiaBaja = Materia::factory()->paraPlanVersion($version)->create(['activo' => true, 'clave' => 'BAJ', 'creditos' => 6]);

    $grupoA = Grupo::factory()->paraMateria($materiaAprobada)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
    $grupoB = Grupo::factory()->paraMateria($materiaBaja)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);

    $matricula = MatriculaPeriodo::factory()->activa()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)
        ->create(['plan_version_id' => $version->id]);

    InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupoA)
        ->create(['estatus' => EstatusInscripcionMateria::APROBADA, 'calificacion_final' => 90.00]);
    // BAJA que además conserva calificación: si el filtro no operase, arrastraría
    // el promedio (90·4 + 80·6)/10 = 84 y sumaría 10 créditos.
    InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupoB)
        ->create(['estatus' => EstatusInscripcionMateria::BAJA, 'calificacion_final' => 80.00, 'fecha_baja' => now()]);

    $service = app(HistorialAcademicoService::class);

    expect($service->obtenerHistorial($alumno)->pluck('materia.clave')->all())
        ->toEqualCanonicalizing(['APR'])
        ->and($service->calcularPromedio($alumno))->toBe(90.0)
        ->and($service->creditosAprobados($alumno))->toBe(4)
        ->and($service->creditosCursados($alumno))->toBe(4);

    // La baja desaparece del kardex, pero la tira de materias sí la muestra
    // (es el estado real de esa materia en el plan).
    $tira = $service->tiraMaterias(Alumno::findOrFail($alumno->id))->keyBy('materia.clave');
    expect($tira['BAJ'])->toMatchArray(['estatus' => 'BAJA', 'es_baja' => true, 'es_aprobada' => false]);
});
