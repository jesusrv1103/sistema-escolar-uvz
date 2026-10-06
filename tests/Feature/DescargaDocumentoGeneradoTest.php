<?php

use App\Enums\RolUsuario;
use App\Enums\TipoFormato;
use App\Livewire\Academico\GenerarFormato;
use App\Models\Alumno;
use App\Models\DocumentoGenerado;
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
