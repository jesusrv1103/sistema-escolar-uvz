<?php

use App\Enums\EstatusInscripcionMateria;
use App\Enums\RolUsuario;
use App\Models\Alumno;
use App\Models\Calificacion;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\InscripcionMateria;
use App\Models\MatriculaPeriodo;
use App\Models\Materia;
use App\Models\Periodo;
use App\Models\PlanVersion;
use App\Models\Programa;
use App\Models\User;
use App\Services\CalificacionService;
use App\Services\EvaluacionService;
use App\Services\InscripcionMateriaService;
use App\Services\MatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('evaluaciones', 'calificaciones');

test('docente crea evaluación en su grupo', function () {
    $docente = User::factory()->deRol(RolUsuario::DOCENTE)->create();
    $docente->docente()->create([
        'clave' => 'DOC-001',
        'nombre' => 'Juan',
        'apellidos' => 'Pérez',
        'curp' => 'JUAP800101HDFRZN09',
    ]);
    
    $grupo = Grupo::factory()->create(['docente_id' => $docente->docente->id, 'activo' => true]);
    
    $service = app(EvaluacionService::class);
    $evaluacion = $service->crear(
        grupo: $grupo,
        nombre: 'Primer Parcial',
        tipo: 'PARCIAL',
        ponderacion: 30,
        fecha: now()->addDays(7),
        usuario: $docente
    );

    expect($evaluacion)->toBeInstanceOf(\App\Models\Evaluacion::class)
        ->and($evaluacion->nombre)->toBe('Primer Parcial')
        ->and($evaluacion->ponderacion)->toBe(30);
});

test('docente no puede crear evaluación en grupo ajeno', function () {
    $docente1 = User::factory()->deRol(RolUsuario::DOCENTE)->create();
    $docente1->docente()->create([
        'clave' => 'DOC-001',
        'nombre' => 'Juan',
        'apellidos' => 'Pérez',
        'curp' => 'JUAP800101HDFRZN09',
    ]);
    
    $docente2 = User::factory()->deRol(RolUsuario::DOCENTE)->create();
    $docente2->docente()->create([
        'clave' => 'DOC-002',
        'nombre' => 'María',
        'apellidos' => 'García',
        'curp' => 'GARM850202MDFRRN08',
    ]);
    
    $grupo = Grupo::factory()->create(['docente_id' => $docente2->docente->id, 'activo' => true]);
    
    $service = app(EvaluacionService::class);
    
    expect(fn() => $service->crear(
        grupo: $grupo,
        nombre: 'Primer Parcial',
        tipo: 'PARCIAL',
        ponderacion: 30,
        fecha: now()->addDays(7),
        usuario: $docente1
    ))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('ponderación total no supera 100%', function () {
    $docente = User::factory()->deRol(RolUsuario::DOCENTE)->create();
    $docente->docente()->create([
        'clave' => 'DOC-001',
        'nombre' => 'Juan',
        'apellidos' => 'Pérez',
        'curp' => 'JUAP800101HDFRZN09',
    ]);
    
    $grupo = Grupo::factory()->create(['docente_id' => $docente->docente->id, 'activo' => true]);
    
    $service = app(EvaluacionService::class);
    
    $service->crear(
        grupo: $grupo,
        nombre: 'Primer Parcial',
        tipo: 'PARCIAL',
        ponderacion: 60,
        fecha: now()->addDays(7),
        usuario: User::factory()->deRol(RolUsuario::ADMIN)->create()
    );
    
    // Segunda evaluación que supera el 100%
    expect(fn() => $service->crear(
        grupo: $grupo,
        nombre: 'Segundo Parcial',
        tipo: 'PARCIAL',
        ponderacion: 50, // 60 + 50 = 110 > 100
        fecha: now()->addDays(14),
        usuario: User::factory()->deRol(RolUsuario::ADMIN)->create()
    ))->toThrow(\App\Exceptions\EvaluacionException::class);
});

test('docente puede actualizar evaluación en borrador', function () {
    $docente = User::factory()->deRol(RolUsuario::DOCENTE)->create();
    $docente->docente()->create([
        'clave' => 'DOC-001',
        'nombre' => 'Juan',
        'apellidos' => 'Pérez',
        'curp' => 'JUAP800101HDFRZN09',
    ]);
    
    $grupo = Grupo::factory()->create(['docente_id' => $docente->docente->id, 'activo' => true]);
    
    $service = app(EvaluacionService::class);
    $evaluacion = $service->crear(
        grupo: $grupo,
        nombre: 'Primer Parcial',
        tipo: 'PARCIAL',
        ponderacion: 30,
        fecha: now()->addDays(7),
        usuario: User::factory()->deRol(RolUsuario::ADMIN)->create()
    );
    
    $service->actualizar(
        evaluacion: $evaluacion,
        nombre: 'Primer Parcial Modificado',
        ponderacion: 35,
        usuario: $docente
    );
    
    expect($evaluacion->fresh()->nombre)->toBe('Primer Parcial Modificado')
        ->and($evaluacion->fresh()->ponderacion)->toBe(35);
});

test('docente puede modificar evaluación sin máquina de estados', function () {
    $docente = User::factory()->deRol(RolUsuario::DOCENTE)->create();
    $docente->docente()->create([
        'clave' => 'DOC-001',
        'nombre' => 'Juan',
        'apellidos' => 'Pérez',
        'curp' => 'JUAP800101HDFRZN09',
    ]);
    
    $grupo = Grupo::factory()->create(['docente_id' => $docente->docente->id, 'activo' => true]);
    
    $service = app(EvaluacionService::class);
    $evaluacion = \App\Models\Evaluacion::factory()->create([
        'grupo_id' => $grupo->id,
    ]);
    
    // Sin máquina de estados, cualquier evaluación puede ser modificada
    $service->actualizar(
        evaluacion: $evaluacion,
        nombre: 'Modificado',
        usuario: $docente
    );
    
    expect($evaluacion->fresh()->nombre)->toBe('Modificado');
});

test('calificación capturada por docente del grupo', function () {
    $docente = User::factory()->deRol(RolUsuario::DOCENTE)->create();
    $docente->docente()->create([
        'clave' => 'DOC-001',
        'nombre' => 'Juan',
        'apellidos' => 'Pérez',
        'curp' => 'JUAP800101HDFRZN09',
    ]);
    
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['docente_id' => $docente->docente->id, 'activo' => true, 'cupo' => 40]);
    
    $alumno = Alumno::factory()->create();
    $matriculaService = app(MatriculaService::class);
    $matricula = $matriculaService->crearDesdeInscripcion(
        alumno: $alumno,
        periodo: $periodo,
        programa: $programa,
        planVersion: $planVersion,
        origen: \App\Enums\OrigenMatricula::PRIMERA_VEZ,
        usuario: User::factory()->deRol(RolUsuario::ADMIN)->create()
    );
    
    $inscripcionService = app(InscripcionMateriaService::class);
    $inscripcion = $inscripcionService->inscribir(
        matricula: $matricula,
        grupo: $grupo,
        usuario: User::factory()->deRol(RolUsuario::ADMIN)->create()
    );
    
    $evaluacion = \App\Models\Evaluacion::factory()->create([
        'grupo_id' => $grupo->id,
        'ponderacion' => 30,
    ]);
    
    $service = app(CalificacionService::class);
    $calificacion = $service->capturar(
        inscripcion: $inscripcion,
        evaluacion: $evaluacion,
        valor: 85.5,
        observacion: 'Buen trabajo',
        usuario: $docente
    );
    
    expect($calificacion)->toBeInstanceOf(\App\Models\Calificacion::class)
        ->and($calificacion->valor)->toBe('85.50');
});

test('calificación fuera de rango 0-100 es rechazada', function () {
    $inscripcion = \App\Models\InscripcionMateria::factory()->cursando()->create();
    $evaluacion = \App\Models\Evaluacion::factory()->create([]);
    
    $service = app(CalificacionService::class);
    expect(fn() => $service->capturar(
        inscripcion: $inscripcion,
        evaluacion: $evaluacion,
        valor: 105,
        usuario: User::factory()->deRol(RolUsuario::ADMIN)->create()
    ))->toThrow(\App\Exceptions\CalificacionException::class);
});

test('cálculo ponderado con redondeo HALF_UP', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
    
    $usuario = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $this->actingAs($usuario);
    
    $matriculaService = app(MatriculaService::class);
    $matricula = $matriculaService->crearDesdeInscripcion(
        alumno: $alumno,
        periodo: $periodo,
        programa: $programa,
        planVersion: $planVersion,
        origen: \App\Enums\OrigenMatricula::PRIMERA_VEZ,
        usuario: $usuario
    );
    
    $inscripcionService = app(InscripcionMateriaService::class);
    $inscripcion = $inscripcionService->inscribir(
        matricula: $matricula,
        grupo: $grupo,
        usuario: $usuario
    );
    
    $eval1 = \App\Models\Evaluacion::factory()->create(['grupo_id' => $grupo->id, 'ponderacion' => 100]);
    \App\Models\Calificacion::factory()->create([
        'inscripcion_materia_id' => $inscripcion->id,
        'evaluacion_id' => $eval1->id,
        'valor' => 85.555,
    ]);
    
    $service = app(CalificacionService::class);
    $inscripcion = $service->consolidar($inscripcion, $usuario);
    
    expect($inscripcion->calificacion_final)->toBe('85.56');
});

test('consolidación con ponderación 100% aprueba si >= 60', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
    
    $usuario = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $this->actingAs($usuario);
    
    $matriculaService = app(MatriculaService::class);
    $matricula = $matriculaService->crearDesdeInscripcion(
        alumno: $alumno,
        periodo: $periodo,
        programa: $programa,
        planVersion: $planVersion,
        origen: \App\Enums\OrigenMatricula::PRIMERA_VEZ,
        usuario: $usuario
    );
    
    $inscripcionService = app(InscripcionMateriaService::class);
    $inscripcion = $inscripcionService->inscribir(
        matricula: $matricula,
        grupo: $grupo,
        usuario: User::factory()->deRol(RolUsuario::ADMIN)->create()
    );
    
    $eval1 = \App\Models\Evaluacion::factory()->create(['grupo_id' => $grupo->id, 'ponderacion' => 50]);
    $eval2 = \App\Models\Evaluacion::factory()->create(['grupo_id' => $grupo->id, 'ponderacion' => 50]);
    
    \App\Models\Calificacion::factory()->create([
        'inscripcion_materia_id' => $inscripcion->id,
        'evaluacion_id' => $eval1->id,
        'valor' => 80.00,
    ]);
    \App\Models\Calificacion::factory()->create([
        'inscripcion_materia_id' => $inscripcion->id,
        'evaluacion_id' => $eval2->id,
        'valor' => 50.00,
    ]);
    
    // 80 * 0.5 + 50 * 0.5 = 65.00 >= 60 = APROBADA
    $service = app(CalificacionService::class);
    $inscripcion = $service->consolidar($inscripcion, $usuario);
    
    expect($inscripcion->estatus)->toBe(\App\Enums\EstatusInscripcionMateria::APROBADA)
        ->and($inscripcion->calificacion_final)->toBe('65.00');
});

test('consolidación con < 60 reprueba', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
    
    $usuario = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $this->actingAs($usuario);
    
    $matriculaService = app(MatriculaService::class);
    $matricula = $matriculaService->crearDesdeInscripcion(
        alumno: $alumno,
        periodo: $periodo,
        programa: $programa,
        planVersion: $planVersion,
        origen: \App\Enums\OrigenMatricula::PRIMERA_VEZ,
        usuario: $usuario
    );
    
    $inscripcionService = app(InscripcionMateriaService::class);
    $inscripcion = $inscripcionService->inscribir(
        matricula: $matricula,
        grupo: $grupo,
        usuario: $usuario
    );
    
    $eval1 = \App\Models\Evaluacion::factory()->create(['grupo_id' => $grupo->id, 'ponderacion' => 100]);
    
    \App\Models\Calificacion::factory()->create([
        'inscripcion_materia_id' => $inscripcion->id,
        'evaluacion_id' => $eval1->id,
        'valor' => 55.00,
    ]);
    
    $service = app(CalificacionService::class);
    $inscripcion = $service->consolidar($inscripcion, $usuario);
    
    expect($inscripcion->estatus)->toBe(\App\Enums\EstatusInscripcionMateria::REPROBADA)
        ->and($inscripcion->calificacion_final)->toBe('55.00');
});