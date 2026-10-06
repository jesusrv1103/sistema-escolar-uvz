<?php

use App\Enums\RolUsuario;
use App\Enums\TipoFormato;
use App\Livewire\Academico\GenerarFormato;
use App\Models\Acta;
use App\Models\Alumno;
use App\Models\DocumentoGenerado;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\MatriculaPeriodo;
use App\Models\Periodo;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class)->group('formatos', 'descargas');

/*
|--------------------------------------------------------------------------
| Descarga de documentos generados (WAVE UI/UX 2.0-12 — GATE FINAL)
|--------------------------------------------------------------------------
|
| `DescargaDocumentoGeneradoController` leía `$documento->path`, un atributo
| que no existe: `documentos_generados` guarda solo trazabilidad (tipo, folio,
| emitido_at, snapshot) y el PDF vive en el disco `private` con un nombre que
| incluye timestamp, por lo que la ruta no era reconstruible y la descarga
| respondía 500 (TypeError de Flysystem).
|
| Clasificación: causa C — el archivo existe pero su ruta no se persistía.
| Corregido sin migración: los componentes que emiten el formato vinculan la
| ruta devuelta por `FormatoService::generar()` a su registro de trazabilidad
| (`snapshot_datos['archivo']`) y la vista restaura la acción de descarga.
|
*/

/**
 * Escenario: alumno con matrícula activa y usuario ADMIN.
 *
 * @return array{0: User, 1: Alumno, 2: MatriculaPeriodo}
 */
function escenarioDescargaFormato(): array
{
    $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $matricula = MatriculaPeriodo::factory()
        ->activa()
        ->paraAlumno($alumno)
        ->paraPeriodo($periodo)
        ->paraPrograma($programa)
        ->create();

    return [$usuario, $alumno, $matricula];
}

test('generar un formato vincula el PDF emitido a su registro de trazabilidad', function () {
    Storage::fake('private');

    [$usuario, $alumno] = escenarioDescargaFormato();

    $componente = Livewire::actingAs($usuario)
        ->test(GenerarFormato::class)
        ->set('tipoFormato', TipoFormato::CONSTANCIA_INSCRIPCION->value)
        ->call('seleccionarAlumno', $alumno->getKey())
        ->call('generar')
        ->assertHasNoErrors();

    $documento = DocumentoGenerado::where('alumno_id', $alumno->getKey())->firstOrFail();

    $componente->assertSet('ultimoDocumentoId', $documento->getKey());

    expect($documento->rutaArchivo())->not->toBeNull()
        ->and($documento->snapshot_datos['archivo'] ?? null)->toBe($documento->rutaArchivo())
        ->and(Storage::disk('private')->exists($documento->rutaArchivo()))->toBeTrue();
});

test('la vista de formatos ofrece la descarga del PDF recién generado', function () {
    Storage::fake('private');

    [$usuario, $alumno] = escenarioDescargaFormato();

    Livewire::actingAs($usuario)
        ->test(GenerarFormato::class)
        ->set('tipoFormato', TipoFormato::CONSTANCIA_INSCRIPCION->value)
        ->call('seleccionarAlumno', $alumno->getKey())
        ->call('generar')
        ->assertHasNoErrors();

    $documento = DocumentoGenerado::where('alumno_id', $alumno->getKey())->firstOrFail();

    Livewire::actingAs($usuario)
        ->test(GenerarFormato::class, [
            'tipoFormato' => TipoFormato::CONSTANCIA_INSCRIPCION->value,
            'ultimoDocumentoId' => $documento->getKey(),
        ])
        ->assertSee(route('documentos.generados.descargar', $documento->getKey()))
        ->assertSee('Descargar PDF');
});

test('descarga el PDF de un documento generado registrado', function () {
    Storage::fake('private');

    [$usuario, $alumno] = escenarioDescargaFormato();

    Livewire::actingAs($usuario)
        ->test(GenerarFormato::class)
        ->set('tipoFormato', TipoFormato::CONSTANCIA_INSCRIPCION->value)
        ->call('seleccionarAlumno', $alumno->getKey())
        ->call('generar')
        ->assertHasNoErrors();

    $documento = DocumentoGenerado::where('alumno_id', $alumno->getKey())->firstOrFail();

    $respuesta = $this->actingAs($usuario)
        ->get(route('documentos.generados.descargar', $documento->getKey()));

    $respuesta->assertOk();
    expect((string) $respuesta->headers->get('content-type'))->toContain('pdf')
        ->and($respuesta->headers->get('content-disposition'))->toContain('.pdf');
});

test('un documento sin ruta registrada responde 404 y no 500', function () {
    Storage::fake('private');

    [$usuario, $alumno] = escenarioDescargaFormato();

    // Emisión directa del servicio (registros anteriores a vincular la ruta):
    // sin ruta resuelta la descarga es un 404 controlado.
    app(\App\Services\FormatoService::class)->generar(
        TipoFormato::CONSTANCIA_INSCRIPCION,
        $alumno,
        null,
        null,
        [],
        $usuario,
    );

    $documento = DocumentoGenerado::where('alumno_id', $alumno->getKey())->firstOrFail();

    expect($documento->rutaArchivo())->toBeNull();

    $this->actingAs($usuario)
        ->get(route('documentos.generados.descargar', $documento->getKey()))
        ->assertNotFound();
});

test('genera el formato de un alumno sin matrícula activa (matrícula no disponible)', function () {
    Storage::fake('private');

    $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $alumno = Alumno::factory()->create(); // sin matrícula en el periodo activo

    Livewire::actingAs($usuario)
        ->test(GenerarFormato::class)
        ->set('tipoFormato', TipoFormato::CONSTANCIA_INSCRIPCION->value)
        ->call('seleccionarAlumno', $alumno->getKey())
        ->call('generar')
        ->assertHasNoErrors();

    expect(DocumentoGenerado::where('alumno_id', $alumno->getKey())->exists())->toBeTrue();
});

test('la descarga exige autorización (ADMINISTRACION → 403)', function () {
    Storage::fake('private');

    [$usuario, $alumno] = escenarioDescargaFormato();

    Livewire::actingAs($usuario)
        ->test(GenerarFormato::class)
        ->set('tipoFormato', TipoFormato::CONSTANCIA_INSCRIPCION->value)
        ->call('seleccionarAlumno', $alumno->getKey())
        ->call('generar');

    $documento = DocumentoGenerado::where('alumno_id', $alumno->getKey())->firstOrFail();

    $sinAcceso = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();

    $this->actingAs($sinAcceso)
        ->get(route('documentos.generados.descargar', $documento->getKey()))
        ->assertForbidden();
});

test('la ruta resuelta nunca sale del directorio de documentos generados', function () {
    Storage::fake('private');

    $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $documento = DocumentoGenerado::create([
        'tipo' => TipoFormato::CONSTANCIA_INSCRIPCION->value,
        'emitido_por' => $usuario->getKey(),
        'emitido_at' => now(),
        'snapshot_datos' => ['archivo' => '../../.env'],
    ]);

    $this->actingAs($usuario)
        ->get(route('documentos.generados.descargar', $documento->getKey()))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Formatos académicos II (`/formatos-academicos`)
|--------------------------------------------------------------------------
|
| Este componente usa claves en minúsculas para sus tipos (`'kardex'`,
| `'boleta'`, …) y luego hacía `TipoFormato::from($tipo)`. El enum sólo admite
| los valores en mayúsculas (`'KARDEX'`), así que el `from()` lanzaba:
|
|     "kardex" is not a valid backing value for enum App\Enums\TipoFormato
|
| El error caía en el `catch` y se mostraba como aviso: no se generaba el PDF,
| no se creaba el registro de trazabilidad y no había nada que descargar. Sólo
| se veía usando la página real; los tests previos ejercitaban `GenerarFormato`
| (`/admin/formatos`), que sí usa los valores del enum.
|
*/

test('formatos académicos II genera el kardex y ofrece su descarga', function () {
    Storage::fake('private');

    [$usuario, $alumno] = escenarioDescargaFormato();

    $componente = Livewire::actingAs($usuario)
        ->test(\App\Livewire\Academico\FormatoAcademico::class)
        ->call('generarFormato', 'kardex', $alumno->getKey())
        ->assertHasNoErrors();

    $documento = DocumentoGenerado::where('alumno_id', $alumno->getKey())->firstOrFail();

    $componente->assertSet('ultimoDocumentoId', $documento->getKey())
        ->assertSee('Descargar PDF')
        ->assertSee(route('documentos.generados.descargar', $documento->getKey()));
});

/*
|--------------------------------------------------------------------------
| QA pre-2.0-17 — hallazgos N-1, N-2 y N-3
|--------------------------------------------------------------------------
|
| N-1  El ACTA es un documento del grupo: `FormatoService::generar()` lo
|      registra sin `alumno_id`, pero el componente buscaba la trazabilidad
|      filtrando por el alumno seleccionado → 0 coincidencias → 0 descarga
|      (el PDF se emitía y en pantalla no había nada que descargar).
|
| N-2  `documentos_generados.folio` es UNIQUE y el ACTA reutiliza
|      `actas.folio`: volver a emitir el mismo acta intentaba un segundo
|      INSERT con el mismo folio (SQLSTATE 23000 / 1062) y el error crudo
|      llegaba a la pantalla. Ahora la fila se reutiliza; las constancias
|      siguen recibiendo un folio nuevo del consecutivo por emisión.
|
| N-3  `generar()` ya validaba `grupoSeleccionado` y `matriculaSeleccionada`,
|      pero la vista no pintaba ninguno de los dos: pulsar "Generar PDF" no
|      producía explicación alguna.
|
*/

/**
 * Escenario: periodo + programa + plan + materia + grupo, para los formatos
 * que trabajan sobre un grupo (acta, horario y lista de grupo).
 *
 * @return array{0: Periodo, 1: Programa, 2: PlanVersion, 3: Materia, 4: Grupo}
 */
function escenarioGrupoFormato(): array
{
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()
        ->create(['activo' => true]);

    return [$periodo, $programa, $planVersion, $materia, $grupo];
}

test('N-1: el acta emitido desde formatos académicos ofrece su descarga', function () {
    Storage::fake('private');

    [$periodo, $programa, , , $grupo] = escenarioGrupoFormato();

    $alumno = Alumno::factory()->create();
    $acta = Acta::factory()->create([
        'grupo_id' => $grupo->id,
        'periodo_id' => $periodo->id,
        'estatus' => 'CERRADA',
        'folio' => 'ACT-2026-00000001',
    ]);
    $usuario = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    $componente = Livewire::actingAs($usuario)
        ->test(\App\Livewire\Academico\FormatoAcademico::class)
        ->call('generarFormato', 'acta', $alumno->getKey(), $acta->getKey())
        ->assertHasNoErrors();

    $documento = DocumentoGenerado::where('tipo', 'ACTA')->firstOrFail();

    // Documento de grupo: no se le inventa un alumno sólo para poder filtrarlo.
    expect($documento->alumno_id)->toBeNull()
        ->and($documento->folio)->toBe('ACT-2026-00000001');

    $componente->assertSet('ultimoDocumentoId', $documento->getKey())
        ->assertSee('Descargar PDF')
        ->assertSee(route('documentos.generados.descargar', $documento->getKey()));
});

test('N-2: regenerar el mismo acta reutiliza su fila de trazabilidad', function () {
    Storage::fake('private');

    [$periodo, , , , $grupo] = escenarioGrupoFormato();

    $acta = Acta::factory()->create([
        'grupo_id' => $grupo->id,
        'periodo_id' => $periodo->id,
        'estatus' => 'CERRADA',
        'folio' => 'ACT-2026-00000001',
    ]);
    $usuario = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $servicio = app(\App\Services\FormatoService::class);

    $primera = $servicio->generar(TipoFormato::ACTA, null, null, $grupo, ['acta_id' => $acta->id], $usuario);
    $segunda = $servicio->generar(TipoFormato::ACTA, null, null, $grupo, ['acta_id' => $acta->id], $usuario);

    expect($primera)->not->toBeEmpty()
        ->and($segunda)->not->toBeEmpty()
        ->and(DocumentoGenerado::where('tipo', 'ACTA')->count())->toBe(1)
        ->and(DocumentoGenerado::where('folio', 'ACT-2026-00000001')->count())->toBe(1);
});

test('N-2: las constancias conservan un folio y una fila por emisión', function () {
    Storage::fake('private');

    [$usuario, $alumno] = escenarioDescargaFormato();

    Livewire::actingAs($usuario)
        ->test(GenerarFormato::class)
        ->set('tipoFormato', TipoFormato::CONSTANCIA_INSCRIPCION->value)
        ->call('seleccionarAlumno', $alumno->getKey())
        ->call('generar')
        ->assertHasNoErrors()
        ->call('generar')
        ->assertHasNoErrors();

    $folios = DocumentoGenerado::where('tipo', 'CONSTANCIA_INSCRIPCION')->pluck('folio');

    expect($folios)->toHaveCount(2)
        ->and($folios->filter()->unique())->toHaveCount(2);
});

test('N-3: la pantalla explica por qué no se generó el horario', function () {
    Storage::fake('private');

    [, $programa, $planVersion, , $grupo] = escenarioGrupoFormato();

    $alumno = Alumno::factory()->create();
    MatriculaPeriodo::factory()->activa()
        ->paraAlumno($alumno)
        ->paraPeriodo($grupo->periodo)
        ->paraPrograma($programa)
        ->create(['plan_version_id' => $planVersion->id]);
    $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

    Livewire::actingAs($usuario)
        ->test(GenerarFormato::class)
        ->set('tipoFormato', TipoFormato::HORARIO->value)
        ->call('seleccionarAlumno', $alumno->getKey())
        ->call('seleccionarGrupo', $grupo->getKey())
        ->call('generar')
        ->assertHasErrors(['grupoSeleccionado'])
        // El error ya existía; lo nuevo es que la vista lo muestra.
        ->assertSee('El alumno no está inscrito en este grupo.');

    expect(DocumentoGenerado::where('tipo', 'HORARIO')->count())->toBe(0);
});
