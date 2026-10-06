<?php

use App\Enums\RolUsuario;
use App\Livewire\Academico\FormatoAcademico;
use App\Models\Alumno;
use App\Models\DocumentoGenerado;
use App\Models\MatriculaPeriodo;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\Periodo;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class)->group('formatos-academicos', 'permisos');

/*
|--------------------------------------------------------------------------
| Regresión pre-2.0-17 — BUG-01: Formatos Académicos 403
|--------------------------------------------------------------------------
|
| Dos capas decían cosas distintas:
|
|   * pantalla  → `formato_academico.generar`  (CONTROL_ESCOLAR y ADMINISTRACION)
|   * acción    → `documento_generado.crear`   (sólo ADMIN)
|
| CONTROL_ESCOLAR y ADMINISTRACION entraban a la pantalla y recibían 403 al
| pulsar «Generar PDF». La regla aprobada (§8.2 + policies de
| `DocumentoGenerado`) queda así:
|
|   ADMIN            → ve el botón y genera
|   CONTROL_ESCOLAR  → ve el botón y genera (protagonista académico)
|   ADMINISTRACION   → ve la pantalla, NO ve el botón (la policy `create`
|                      le niega la emisión y la descarga sigue en 403)
|   ADMISIONES, DOCENTE, ALUMNO → 403 en la pantalla
|
*/

/** Escenario mínimo con alumno matriculado a un plan vigente. */
function escenario_formatos_perfil(): array
{
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $plan = PlanEstudio::factory()->paraPrograma($programa)->create();
    $version = PlanVersion::factory()->forPlanEstudio($plan)->vigente()->create();
    $matricula = MatriculaPeriodo::factory()
        ->paraAlumno($alumno)
        ->paraPeriodo($periodo)
        ->paraPrograma($programa)
        ->create(['plan_version_id' => $version->id]);

    return compact('alumno', 'periodo', 'programa', 'plan', 'version', 'matricula');
}

test('ADMIN genera el formato desde la pantalla y lo descarga', function () {
    Storage::fake('private');
    $escenario = escenario_formatos_perfil();
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $this->actingAs($admin)->get('/formatos-academicos')->assertOk();

    Livewire::actingAs($admin)
        ->test(FormatoAcademico::class)
        ->call('generarFormato', 'kardex', $escenario['alumno']->getKey())
        ->assertHasNoErrors();

    $documento = DocumentoGenerado::where('tipo', 'KARDEX')->where('alumno_id', $escenario['alumno']->id)->firstOrFail();

    $respuesta = $this->actingAs($admin)
        ->get(route('documentos.generados.descargar', $documento->getKey()));

    expect((string) $respuesta->headers->get('content-type'))->toContain('pdf')
        ->and($respuesta->streamedContent())->toStartWith('%PDF-');
});

test('CONTROL_ESCOLAR genera el formato desde la pantalla y lo descarga (regresión BUG-01)', function () {
    Storage::fake('private');
    $escenario = escenario_formatos_perfil();
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    $this->actingAs($control)->get('/formatos-academicos')->assertOk();

    Livewire::actingAs($control)
        ->test(FormatoAcademico::class)
        ->call('generarFormato', 'kardex', $escenario['alumno']->getKey())
        ->assertHasNoErrors();

    $documento = DocumentoGenerado::where('tipo', 'KARDEX')->where('alumno_id', $escenario['alumno']->id)->firstOrFail();

    $respuesta = $this->actingAs($control)
        ->get(route('documentos.generados.descargar', $documento->getKey()));

    expect($respuesta->getStatusCode())->toBe(200)
        ->and((string) $respuesta->headers->get('content-type'))->toContain('pdf')
        ->and($respuesta->streamedContent())->toStartWith('%PDF-');
});

test('ADMINISTRACION entra a la pantalla pero no se le ofrece Generar PDF', function () {
    $escenario = escenario_formatos_perfil();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();

    // Matriz §8.2: la pantalla sigue siendo accesible para este rol.
    $this->actingAs($administracion)
        ->get('/formatos-academicos')
        ->assertOk()
        // La acción se oculta en lugar de prometer un 403 inevitable.
        ->assertDontSee('Generar PDF')
        ->assertSee('no emite PDFs institucionales');

    // Si alguien invocara la acción por su cuenta, el backend la rechaza.
    Livewire::actingAs($administracion)
        ->test(FormatoAcademico::class)
        ->call('generarFormato', 'kardex', $escenario['alumno']->getKey())
        ->assertForbidden();

    expect(DocumentoGenerado::where('tipo', 'KARDEX')->exists())->toBeFalse();
});

test('ADMISIONES no entra a Formatos Académicos', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();

    $this->actingAs($admisiones)->get('/formatos-academicos')->assertForbidden();
});

test('DOCENTE no entra a Formatos Académicos', function () {
    $docente = User::factory()->deRol(RolUsuario::DOCENTE)->create();

    $this->actingAs($docente)->get('/formatos-academicos')->assertForbidden();
});

test('ALUMNO no entra a Formatos Académicos', function () {
    $alumno = User::factory()->deRol(RolUsuario::ALUMNO)->create();

    $this->actingAs($alumno)->get('/formatos-academicos')->assertForbidden();
});

test('la capacidad de pantalla y la policy de emisión coinciden con la matriz', function () {
    // [puede entrar a la pantalla, puede invocar la generación].
    $esperado = [
        RolUsuario::ADMIN->value => [true, true],
        RolUsuario::CONTROL_ESCOLAR->value => [true, true],
        RolUsuario::ADMINISTRACION->value => [true, false],
        RolUsuario::ADMISIONES->value => [false, false],
        RolUsuario::DOCENTE->value => [false, false],
        RolUsuario::ALUMNO->value => [false, false],
    ];

    foreach ($esperado as $rol => [$entra, $genera]) {
        $usuario = User::factory()->deRol(RolUsuario::from($rol))->create();

        $pantalla = Gate::forUser($usuario)->allows('formato_academico.generar');
        $emision = Gate::forUser($usuario)->allows('create', DocumentoGenerado::class);

        expect($pantalla)->toBe($entra, "pantalla: {$rol}")
            // La generación exige las DOS reglas (pantalla + policy): ese es
            // exactamente el and que aplica `generarFormato()`.
            ->and($pantalla && $emision)->toBe($genera, "generación: {$rol}");

        // Quien puede entrar nunca debe ver un botón que la policy niegue.
        if ($pantalla) {
            expect($emision)->toBe($genera, "botón: {$rol}");
        }
    }
});
