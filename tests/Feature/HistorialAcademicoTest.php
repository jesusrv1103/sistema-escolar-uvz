<?php

use App\Models\Alumno;
use App\Models\Calificacion;
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
use App\Enums\EstatusInscripcionMateria;
use App\Enums\RolUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    
    $matricula = MatriculaPeriodo::factory()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create([
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
    
    $matricula = MatriculaPeriodo::factory()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create([
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
    
    $matricula = MatriculaPeriodo::factory()->paraAlumno($alumno)->paraPeriodo(Periodo::factory()->create(['activo' => true]))->paraPrograma(Programa::factory()->create(['activo' => true]))->create();
    
    $service = app(HistorialAcademicoService::class);
    
    // Sin materias aprobadas, avance debe ser 0%
    expect($service->porcentajeAvance($alumno))->toBe(0.0);
});
