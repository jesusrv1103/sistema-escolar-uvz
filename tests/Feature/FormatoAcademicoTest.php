<?php

use App\Models\Acta;
use App\Models\Alumno;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Periodo;
use App\Models\Programa;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\MatriculaPeriodo;
use App\Models\InscripcionMateria;
use App\Models\Evaluacion;
use App\Models\Calificacion;
use App\Models\GrupoHorario;
use App\Models\Aula;
use App\Models\Docente;
use App\Models\User;
use App\Services\FormatoService;
use App\Enums\TipoFormato;
use App\Enums\TipoConsecutivo;
use App\Enums\RolUsuario;
use App\Enums\EstatusInscripcionMateria;
use App\Enums\EstatusGrupo;
use App\Enums\TurnoGrupo;
use App\Enums\DiaSemana;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('formatos-academicos');

test('genera kardex para alumno con historial', function () {
    $alumno = Alumno::factory()->create(['numero_control' => '20240001']);
    $periodo = Periodo::factory()->create(['activo' => true, 'clave' => '2024-1']);
    $programa = Programa::factory()->create(['activo' => true, 'clave' => 'LIC-DER']);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'creditos' => 4]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
    
    $matricula = MatriculaPeriodo::factory()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create();
    $inscripcion = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo)->cursando()->create();
    
    $eval = Evaluacion::factory()->create(['grupo_id' => $grupo->id, 'ponderacion' => 100]);
    \App\Models\Calificacion::factory()->create([
        'inscripcion_materia_id' => $inscripcion->id,
        'evaluacion_id' => $eval->id,
        'valor' => 85.00,
    ]);
    $inscripcion->update(['calificacion_final' => 85.00, 'estatus' => \App\Enums\EstatusInscripcionMateria::APROBADA]);
    
    $service = app(\App\Services\FormatoService::class);
    $pdfPath = $service->generar(
        TipoFormato::from('KARDEX'),
        $alumno,
        null,
        null,
        [],
        User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()
    );
    
    expect($pdfPath)->not->toBeEmpty();
    expect(\App\Models\DocumentoGenerado::where('tipo', 'KARDEX')->exists())->toBeTrue();
});

test('genera boleta de calificaciones', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true]);
    
    $matricula = MatriculaPeriodo::factory()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create();
    $inscripcion = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo)->cursando()->create();
    
    $service = app(\App\Services\FormatoService::class);
    $pdfPath = $service->generar(
        TipoFormato::from('BOLETA'),
        $alumno,
        null,
        null,
        [],
        User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()
    );
    
    expect($pdfPath)->not->toBeEmpty();
});

test('genera historial académico PDF', function () {
    $alumno = Alumno::factory()->create();
    
    $service = app(\App\Services\FormatoService::class);
    $pdfPath = $service->generar(
        TipoFormato::from('HISTORIAL'),
        $alumno,
        null,
        null,
        [],
        User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()
    );
    
    expect($pdfPath)->not->toBeEmpty();
});

test('genera acta de calificaciones reutiliza folio ACT', function () {
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true]);
    
    $acta = Acta::factory()->create([
        'grupo_id' => $grupo->id,
        'periodo_id' => $periodo->id,
        'estatus' => 'CERRADA',
        'folio' => 'ACT-2024-00000001',
    ]);
    
    $service = app(\App\Services\FormatoService::class);
    $pdfPath = $service->generar(
        TipoFormato::from('ACTA'),
        null,
        null,
        $grupo,
        ['acta_id' => $acta->id],
        User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()
    );
    
    expect($pdfPath)->not->toBeEmpty();
    
    $documento = \App\Models\DocumentoGenerado::where('tipo', 'ACTA')->first();
    expect($documento->folio)->toBe('ACT-2024-00000001');
});

test('genera tira de materias', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true]);
    
    $matricula = MatriculaPeriodo::factory()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create();
    $inscripcion = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo)->cursando()->create();
    
    $service = app(\App\Services\FormatoService::class);
    $pdfPath = $service->generar(
        TipoFormato::from('TIRA'),
        $alumno,
        null,
        null,
        [],
        User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()
    );
    
    expect($pdfPath)->not->toBeEmpty();
});

test('genera reporte de avance académico', function () {
    $alumno = Alumno::factory()->create();
    
    $service = app(\App\Services\FormatoService::class);
    $pdfPath = $service->generar(
        TipoFormato::from('AVANCE'),
        $alumno,
        null,
        null,
        [],
        User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()
    );
    
    expect($pdfPath)->not->toBeEmpty();
});

test('acta PDF reutiliza folio ACT existente sin generar nuevo', function () {
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true]);
    
    $acta = Acta::factory()->create([
        'grupo_id' => $grupo->id,
        'periodo_id' => $periodo->id,
        'estatus' => 'CERRADA',
        'folio' => 'ACT-2024-00000001',
    ]);
    
    $service = app(\App\Services\FormatoService::class);
    $pdfPath = $service->generar(
        TipoFormato::from('ACTA'),
        null,
        null,
        $grupo,
        ['acta_id' => $acta->id],
        User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()
    );
    
    $documento = \App\Models\DocumentoGenerado::where('tipo', 'ACTA')->first();
    expect($documento->folio)->toBe('ACT-2024-00000001');
    
    // Verificar que no se generó un nuevo folio (no debe ser ACT-2024-00000002)
    expect($documento->folio)->not->toBe('ACT-2024-00000002');
});

test('formato sin folio no intenta generar consecutivo', function () {
    $alumno = Alumno::factory()->create();
    
    $service = app(\App\Services\FormatoService::class);
    $pdfPath = $service->generar(
        TipoFormato::from('KARDEX'),
        $alumno,
        null,
        null,
        [],
        User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()
    );
    
    $documento = \App\Models\DocumentoGenerado::where('tipo', 'KARDEX')->first();
    expect($documento->folio)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Regresión pre-2.0-17 — BUG-02: `Grupo::$turno` era string
|--------------------------------------------------------------------------
|
| `FormatoService::prepararDatos()` hace `$grupo->turno?->label()` para
| HORARIO, LISTA_GRUPO y ACTA, pero la columna llegaba como texto plano
| (sin cast) → "Call to a member function label() on string" (HTTP 500).
|
| El suite nunca lo vio porque `GrupoFactory` deja un `TurnoGrupo` en la
| atribución del modelo recién creado (aún sin guardar como texto). El fallo
| sólo aparece con la fila LEÍDA de la BD, que es lo que hace el producto en
| el navegador: por eso las tres pruebas siguientes recargan el grupo.
|
*/
test('BUG-02: el turno se lee de la BD como TurnoGrupo con su etiqueta', function () {
    $grupo = Grupo::factory()->create(['turno' => 'VESPERTINO']);
    $leido = Grupo::query()->findOrFail($grupo->id);

    // El valor almacenado sigue siendo el texto original (sin migración).
    expect($leido->getAttributes()['turno'])->toBe('VESPERTINO')
        ->and($leido->turno)->toBeInstanceOf(TurnoGrupo::class)
        ->and($leido->turno->value)->toBe('VESPERTINO')
        ->and($leido->turno->label())->toBe('Vespertino');
});

test('BUG-02: HORARIO, LISTA_GRUPO y ACTA generan con el grupo recargado de la BD', function () {
    \Illuminate\Support\Facades\Storage::fake('private');

    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()
        ->create(['activo' => true, 'turno' => 'VESPERTINO']);
    GrupoHorario::factory()->paraGrupo($grupo)->create();

    $acta = Acta::factory()->create([
        'grupo_id' => $grupo->id,
        'periodo_id' => $periodo->id,
        'estatus' => 'CERRADA',
        'folio' => 'ACT-2026-00000001',
    ]);

    $alumno = Alumno::factory()->create();
    $matricula = MatriculaPeriodo::factory()->activa()
        ->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)
        ->create(['plan_version_id' => $planVersion->id]);
    InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo)->cursando()->create();

    // Así lo consume el producto: el turno llega como texto desde MySQL.
    $grupoLeido = Grupo::query()->findOrFail($grupo->id);

    $service = app(FormatoService::class);
    // HORARIO y LISTA_GRUPO sólo se emiten desde `/admin/formatos` (ADMIN);
    // el ACTA se emite también desde Formatos Académicos (CONTROL_ESCOLAR).
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    $rutas = [
        $service->generar(TipoFormato::HORARIO, $alumno, $matricula, $grupoLeido, [], $admin),
        $service->generar(TipoFormato::LISTA_GRUPO, null, null, $grupoLeido, [], $admin),
        $service->generar(TipoFormato::ACTA, null, null, $grupoLeido, ['acta_id' => $acta->id], $control),
    ];

    foreach ($rutas as $ruta) {
        expect(\Illuminate\Support\Facades\Storage::disk('private')->get($ruta))->toStartWith('%PDF-');
    }
});

test('BUG-02: el turno etiquetado llega a la preparación de datos de los formatos con grupo', function () {
    $grupo = Grupo::factory()->create(['turno' => 'NOCTURNO']);
    $leido = Grupo::query()->findOrFail($grupo->id);

    expect($leido->turno?->label())->toBe('Nocturno')
        // La plantilla de lista de grupo ya no necesita `tryFrom` defensivo:
        // el service entrega la etiqueta como hace con `estatus`.
        ->and(\App\Enums\TurnoGrupo::tryFrom($leido->getAttributes()['turno'])?->label())->toBe('Nocturno');
});
