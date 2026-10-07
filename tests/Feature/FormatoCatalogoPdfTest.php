<?php

use App\Enums\EstatusInscripcionMateria;
use App\Enums\RolUsuario;
use App\Enums\TipoFormato;
use App\Models\Acta;
use App\Models\Alumno;
use App\Models\DocumentoGenerado;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\InscripcionMateria;
use App\Models\Materia;
use App\Models\MatriculaPeriodo;
use App\Models\Periodo;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\PracticaProfesional;
use App\Models\Programa;
use App\Models\ServicioSocial;
use App\Models\User;
use App\Services\FormatoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class)->group('formatos', 'pdf');

/*
|--------------------------------------------------------------------------
| §16 — Salud de los PDF del catálogo
|--------------------------------------------------------------------------
|
| Auditoría pre-2.0-17: para CADA TipoFormato se confirma que la generación
| no lanza excepción, que el archivo existe, que no está vacío y que empieza
| por `%PDF-`. El dataset se deriva de `TipoFormato::cases()`, así que un
| formato nuevo obliga a cubrirlo aquí.
|
*/

test('§16: los 12 formatos del catálogo generan su PDF sin excepción', function () {
    Storage::fake('private');

    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $plan = PlanEstudio::factory()->paraPrograma($programa)->create();
    $version = PlanVersion::factory()->forPlanEstudio($plan)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($version)->create(['activo' => true, 'creditos' => 4]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()
        ->create(['activo' => true, 'cupo' => 40, 'turno' => 'MATUTINO']);
    GrupoHorario::factory()->paraGrupo($grupo)->create();

    $acta = Acta::factory()->create([
        'grupo_id' => $grupo->id,
        'periodo_id' => $periodo->id,
        'estatus' => 'CERRADA',
        'folio' => 'ACT-2026-00000042',
    ]);

    $alumno = Alumno::factory()->create();
    $matricula = MatriculaPeriodo::factory()->activa()
        ->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)
        ->create(['plan_version_id' => $version->id]);
    InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo)->cursando()
        ->create(['calificacion_final' => 88.00, 'estatus' => EstatusInscripcionMateria::APROBADA]);

    // La constancia de terminación exige egreso real (guarda de veracidad).
    \App\Models\Egreso::factory()->create(['alumno_id' => $alumno->id, 'plan_version_id' => $version->id]);
    ServicioSocial::factory()->validado()->create(['alumno_id' => $alumno->id]);
    PracticaProfesional::factory()->validado()->create(['alumno_id' => $alumno->id]);

    $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $service = app(FormatoService::class);

    // [formato, alumno, matricula, grupo, datos extra]
    $escenarios = [
        [TipoFormato::CONSTANCIA_INSCRIPCION, $alumno, $matricula, null, []],
        [TipoFormato::COMPROBANTE_INSCRIPCION, $alumno, $matricula, null, []],
        [TipoFormato::FICHA, $alumno, $matricula, null, []],
        [TipoFormato::HORARIO, $alumno, $matricula, $grupo, []],
        [TipoFormato::LISTA_GRUPO, null, null, $grupo, []],
        [TipoFormato::KARDEX, $alumno, $matricula, null, []],
        [TipoFormato::BOLETA, $alumno, $matricula, null, []],
        [TipoFormato::HISTORIAL, $alumno, $matricula, null, []],
        [TipoFormato::ACTA, null, null, $grupo, ['acta_id' => $acta->id]],
        [TipoFormato::TIRA, $alumno, $matricula, null, []],
        [TipoFormato::AVANCE, $alumno, $matricula, null, []],
        [TipoFormato::CONSTANCIA_TERMINACION, $alumno, $matricula, null, []],
    ];

    // El dataset debe cubrir exactamente el catálogo vigente.
    expect(array_column($escenarios, 0))->toEqualCanonicalizing(TipoFormato::cases());

    foreach ($escenarios as [$tipo, $alumnoParam, $matriculaParam, $grupoParam, $extra]) {
        $ruta = $service->generar($tipo, $alumnoParam, $matriculaParam, $grupoParam, $extra, $usuario);
        $contenido = Storage::disk('private')->get($ruta);

        $this->assertNotEmpty($ruta, "Ruta vacía para {$tipo->value}");
        $this->assertNotEmpty($contenido, "PDF vacío para {$tipo->value}");
        $this->assertGreaterThan(0, strlen($contenido), "Cero bytes para {$tipo->value}");
        $this->assertSame('%PDF-', substr($contenido, 0, 5), "Cabecera inválida para {$tipo->value}");
        $this->assertSame(
            1,
            DocumentoGenerado::where('tipo', $tipo->value)->count(),
            "Falta trazabilidad para {$tipo->value}"
        );
    }

    expect(DocumentoGenerado::count())->toBe(count($escenarios));
});

/*
|--------------------------------------------------------------------------
| Regresión saneamiento pre-2.0-17 — ability `view` de GrupoPolicy (D)
|--------------------------------------------------------------------------
|
| `FormatoService::autorizar()` validaba HORARIO y LISTA_GRUPO con
| `Gate::authorize('ver', $grupo)`. `ver` no existe en `GrupoPolicy` (la
| ability real es `view`), así que el Gate no encontraba callback: sólo
| superaba esa guardia ADMIN, que pasa por `Gate::before`. CONTROL_ESCOLAR y
| ADMINISTRACION — que sí pueden emitir formatos (`formato_academico.generar`)
| — recibían un 403 injustificado al emitir un formato de grupo.
|
*/

test('D: CONTROL_ESCOLAR emite HORARIO y LISTA_GRUPO (ability view, no ver)', function () {
    Storage::fake('private');

    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $plan = PlanEstudio::factory()->paraPrograma($programa)->create();
    $version = PlanVersion::factory()->forPlanEstudio($plan)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($version)->create(['activo' => true, 'creditos' => 4]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()
        ->create(['activo' => true, 'cupo' => 40, 'turno' => 'MATUTINO']);
    GrupoHorario::factory()->paraGrupo($grupo)->create();

    $alumno = Alumno::factory()->create();
    $matricula = MatriculaPeriodo::factory()->activa()
        ->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)
        ->create(['plan_version_id' => $version->id]);
    InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo)->cursando()->create();

    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $service = app(FormatoService::class);

    // La ability inexistente sigue sin resolver el Gate; la real (`view`) sí.
    expect(Gate::forUser($control)->allows('view', $grupo))->toBeTrue()
        ->and(Gate::forUser($control)->allows('ver', $grupo))->toBeFalse();

    foreach ([TipoFormato::HORARIO, TipoFormato::LISTA_GRUPO] as $tipo) {
        $ruta = $service->generar($tipo, $tipo === TipoFormato::HORARIO ? $alumno : null,
            $tipo === TipoFormato::HORARIO ? $matricula : null, $grupo, [], $control);
        $contenido = Storage::disk('private')->get($ruta);

        $this->assertSame('%PDF-', substr((string) $contenido, 0, 5), "PDF inválido para {$tipo->value}");
        $this->assertSame(1, DocumentoGenerado::where('tipo', $tipo->value)->count());
    }
});
