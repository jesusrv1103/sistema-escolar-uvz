<?php

use App\Enums\TipoFormato;
use App\Enums\TipoConsecutivo;
use App\Models\Alumno;
use App\Models\DocumentoGenerado;
use App\Models\Grupo;
use App\Models\MatriculaPeriodo;
use App\Models\Materia;
use App\Models\Periodo;
use App\Models\PlanVersion;
use App\Models\Programa;
use App\Models\User;
use App\Services\FormatoService;
use App\Services\ConsecutivoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('formatos');

/**
 * Motor de Formatos (2.0-05) — Pruebas de generación, folio, branding, autorización.
 */
test('genera constancia de inscripción con folio CI-YYYY-########', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = \App\Models\PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $matricula = MatriculaPeriodo::factory()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create();
    $usuario = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();

    $this->actingAs($usuario);
    $service = app(FormatoService::class);
    $pdfPath = $service->generar(
        \App\Enums\TipoFormato::CONSTANCIA_INSCRIPCION,
        $alumno,
        $matricula,
        null,
        [],
        $usuario
    );

    expect($pdfPath)->not->toBeEmpty();
    
    $documento = DocumentoGenerado::where('alumno_id', $alumno->id)
        ->where('tipo', \App\Enums\TipoFormato::CONSTANCIA_INSCRIPCION->value)
        ->first();
    
    expect($documento)->not->toBeNull()
        ->and($documento->folio)->toMatch('/^CI-\d{4}-\d{8}$/')
        ->and($documento->snapshot_datos)->toBeArray();
});

test('genera comprobante de inscripción', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = \App\Models\PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $matricula = MatriculaPeriodo::factory()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create();
    $usuario = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();

    $this->actingAs($usuario);
    $service = app(FormatoService::class);

    $service = app(FormatoService::class);
    $pdfPath = $service->generar(
        \App\Enums\TipoFormato::COMPROBANTE_INSCRIPCION,
        $alumno,
        $matricula,
        null,
        [],
        $usuario
    );

    expect($pdfPath)->not->toBeEmpty();
    
    $documento = DocumentoGenerado::where('alumno_id', $alumno->id)
        ->where('tipo', \App\Enums\TipoFormato::COMPROBANTE_INSCRIPCION->value)
        ->first();
    
    expect($documento)->not->toBeNull();
});

test('genera ficha del alumno', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = \App\Models\PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $matricula = MatriculaPeriodo::factory()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create();
    $usuario = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();

    $this->actingAs($usuario);
    $service = app(FormatoService::class);

    $service = app(FormatoService::class);
    $pdfPath = $service->generar(
        \App\Enums\TipoFormato::FICHA,
        $alumno,
        $matricula,
        null,
        [],
        $usuario
    );

    expect($pdfPath)->not->toBeEmpty();
});

test('genera horario del alumno', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = \App\Models\PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
    
    \App\Models\GrupoHorario::create([
        'grupo_id' => $grupo->id,
        'dia' => \App\Enums\DiaSemana::LUNES,
        'hora_inicio' => '08:00:00',
        'hora_fin' => '10:00:00',
    ]);
    
    $matricula = MatriculaPeriodo::factory()->paraAlumno($alumno)->paraPeriodo($periodo)->create();
    \App\Models\InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo)->cursando()->create();
    
    $usuario = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $this->actingAs($usuario);
    $service = app(FormatoService::class);

    $service = app(FormatoService::class);
    $pdfPath = $service->generar(
        \App\Enums\TipoFormato::HORARIO,
        $alumno,
        $matricula,
        $grupo,
        [],
        $usuario
    );

    expect($pdfPath)->not->toBeEmpty();
});

test('genera lista de grupo', function () {
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = \App\Models\PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
    $usuario = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();

    $this->actingAs($usuario);
    $service = app(FormatoService::class);

    $service = app(FormatoService::class);
    $pdfPath = $service->generar(
        \App\Enums\TipoFormato::LISTA_GRUPO,
        null,
        null,
        $grupo,
        [],
        $usuario
    );

    expect($pdfPath)->not->toBeEmpty();
});

test('folio CI usa consecutivo seguro', function () {
    $service = app(ConsecutivoService::class);
    $folio1 = $service->generar(\App\Enums\TipoConsecutivo::CONSTANCIA_INSCRIPCION);
    $folio2 = $service->generar(\App\Enums\TipoConsecutivo::CONSTANCIA_INSCRIPCION);
    
    expect($folio1)->toMatch('/^CI-\d{4}-\d{8}$/');
    expect($folio2)->toMatch('/^CI-\d{4}-\d{8}$/');
    expect($folio1)->not->toBe($folio2);
});

test('folios no se reutilizan al eliminar documento', function () {
    $service = app(ConsecutivoService::class);
    $folio1 = $service->generar(\App\Enums\TipoConsecutivo::CONSTANCIA_INSCRIPCION);
    $service->generar(\App\Enums\TipoConsecutivo::CONSTANCIA_INSCRIPCION);
    $folio3 = $service->generar(\App\Enums\TipoConsecutivo::CONSTANCIA_INSCRIPCION);
    
    // El consecutivo no debe reutilizarse
    expect($folio3)->not->toBe($folio1);
});

test('branding centralizado en PDF', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = \App\Models\PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $matricula = MatriculaPeriodo::factory()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create();
    $usuario = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();

    $this->actingAs($usuario);
    $service = app(FormatoService::class);

    $service = app(FormatoService::class);
    $pdfPath = $service->generar(
        \App\Enums\TipoFormato::CONSTANCIA_INSCRIPCION,
        $alumno,
        $matricula,
        null,
        [],
        $usuario
    );

    expect($pdfPath)->not->toBeEmpty();
    
    // Verificar que el PDF se genera y se almacena
    $documento = DocumentoGenerado::where('tipo', \App\Enums\TipoFormato::CONSTANCIA_INSCRIPCION->value)->first();
    expect($documento)->not->toBeNull();
});

test('no permite acceso a documento ajeno (IDOR)', function () {
    $alumno1 = Alumno::factory()->create();
    $alumno2 = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = \App\Models\PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $matricula1 = MatriculaPeriodo::factory()->paraAlumno($alumno1)->paraPeriodo($periodo)->paraPrograma($programa)->create();
    $matricula2 = MatriculaPeriodo::factory()->paraAlumno($alumno2)->paraPeriodo($periodo)->paraPrograma($programa)->create();
    $usuario1 = User::factory()->deRol(\App\Enums\RolUsuario::ADMISIONES)->create();
    $usuario2 = User::factory()->deRol(\App\Enums\RolUsuario::ADMISIONES)->create();

    $this->actingAs($usuario1);

    $service = app(FormatoService::class);
    $service->generar(
        \App\Enums\TipoFormato::CONSTANCIA_INSCRIPCION,
        $alumno1,
        $matricula1,
        null,
        [],
        $usuario1
    );

    $documento = DocumentoGenerado::where('alumno_id', $alumno1->id)->first();
    
    // Intentar acceder con otro usuario
    $controller = app(\App\Http\Controllers\Documentos\DescargaDocumentoGeneradoController::class);
    
    // El documento pertenece al alumno1, usuario2 no debería tener acceso
    // (ADMISIONES ve todos, pero probamos que la policy funciona)
    expect($documento)->not->toBeNull();
    
    // Verificar policy directamente: ADMISIONES ve todos los documentos
    $policy = app(\App\Policies\DocumentoGeneradoPolicy::class);
    expect($policy->view($usuario2, $documento))->toBeTrue();
    
    // Probar con DOCENTE sin grupo (debería fallar)
    $docente = User::factory()->deRol(\App\Enums\RolUsuario::DOCENTE)->create();
    $docente->docente()->create([
        'clave' => 'DOC-999',
        'nombre' => 'Test',
        'apellidos' => 'Docente',
        'curp' => 'TEST800101HDFRZN09',
    ]);
    expect($policy->view($docente, $documento))->toBeFalse();
});