<?php

use App\Enums\RolUsuario;
use App\Livewire\Academico\MateriaAdmin;
use App\Livewire\Academico\MateriasIndex;
use App\Models\Materia;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\Programa;
use App\Models\User;
use Livewire\Livewire;

uses()->group('academico', 'materias', 'navegacion');

/*
|--------------------------------------------------------------------------
| Navegabilidad de Materias (WAVE UI/UX 2.0-12 — fix de acceso)
|--------------------------------------------------------------------------
|
| El módulo estaba sólo en Plan → Versión → Materias, encadenado en URLs que
| el usuario no podía descubrir. Estas pruebas cubren las DOS entradas nuevas
| (sidebar y Planes → Versiones y materias), la protección server-side del
| listado global y que el CRUD existente sigue intacto.
|
| Sólo presentación/navegación: no se otorgan permisos ni cambian políticas.
|
*/

/**
 * HTML del sidebar de la página, para afirmar lo que el usuario ve (o no ve)
 * sin que el resto del contenido ensucie el resultado.
 */
function htmlSidebarMateriasNav(string $html): string
{
    $inicio = strpos($html, 'id="app-sidebar"');
    if ($inicio === false) {
        return '';
    }
    $fin = strpos($html, '</aside>', (int) $inicio);

    return substr($html, (int) $inicio, $fin - (int) $inicio);
}

test('ADMIN ve Materias en el sidebar con su enlace al listado global', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $sidebar = htmlSidebarMateriasNav(
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent()
    );

    expect($sidebar)
        ->toContain('Materias')
        ->toContain(route('admin.materias.index'))
        ->toContain('Planes de estudio');
});

test('sin la capacidad de materias el sidebar no muestra Materias', function () {
    $controlEscolar = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    $sidebar = htmlSidebarMateriasNav(
        $this->actingAs($controlEscolar)->get(route('alumnos.lista'))->assertOk()->getContent()
    );

    expect($sidebar)
        ->not->toContain('Materias')
        ->not->toContain('/admin/materias');
});

test('el listado global de materias esta protegido server-side', function () {
    $this->get(route('admin.materias.index'))->assertRedirect();

    $controlEscolar = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $this->actingAs($controlEscolar)->get(route('admin.materias.index'))->assertForbidden();
    Livewire::actingAs($controlEscolar)->test(MateriasIndex::class)->assertForbidden();

    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $this->actingAs($admin)->get(route('admin.materias.index'))
        ->assertOk()
        ->assertSee('Materias', false)
        ->assertSee('Programa', false);
});

test('el listado global filtra por programa, plan y version', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $programaA = Programa::factory()->create();
    $planA = PlanEstudio::factory()->paraPrograma($programaA)->create();
    $versionA = PlanVersion::factory()->propuesta()->forPlanEstudio($planA)->create();
    Materia::factory()->paraPlanVersion($versionA)->create(['clave' => 'NAV-A1']);

    $programaB = Programa::factory()->create();
    $planB = PlanEstudio::factory()->paraPrograma($programaB)->create();
    $versionB = PlanVersion::factory()->propuesta()->forPlanEstudio($planB)->create();
    Materia::factory()->paraPlanVersion($versionB)->create(['clave' => 'NAV-B1']);

    // Por plan
    Livewire::actingAs($admin)->test(MateriasIndex::class)
        ->set('filtroPlan', $planB->id)
        ->assertSee('NAV-B1')
        ->assertDontSee('NAV-A1')
        ->set('filtroPlan', '')
        // Por versión
        ->set('filtroVersion', $versionA->id)
        ->assertSee('NAV-A1')
        ->assertDontSee('NAV-B1')
        ->set('filtroVersion', '')
        // Por programa
        ->set('filtroPrograma', $programaB->id)
        ->assertSee('NAV-B1')
        ->assertDontSee('NAV-A1');
});

test('el listado global busca por clave o por nombre', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $version = PlanVersion::factory()->propuesta()->create();
    Materia::factory()->paraPlanVersion($version)->create([
        'clave' => 'BUS-CLAVE-01',
        'nombre' => 'Materia Búsqueda Especial',
    ]);
    Materia::factory()->paraPlanVersion($version)->create([
        'clave' => 'OTR-CLAVE-02',
        'nombre' => 'Otra materia',
    ]);

    Livewire::actingAs($admin)->test(MateriasIndex::class)
        ->set('busqueda', 'BUS-CLAVE')
        ->assertSee('BUS-CLAVE-01')
        ->assertDontSee('OTR-CLAVE-02')
        ->set('busqueda', 'Búsqueda Especial')
        ->assertSee('BUS-CLAVE-01')
        ->assertDontSee('OTR-CLAVE-02')
        ->call('limpiarFiltros')
        ->assertSee('OTR-CLAVE-02');
});

test('los planes de estudio enlazan a Versiones y materias y de ahi a Materias', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();

    // (B) Planes de estudio → Versiones y materias
    $planes = $this->actingAs($admin)->get(route('admin.planes-estudio'))->assertOk()->getContent();
    expect($planes)
        ->toContain('Versiones y materias')
        ->toContain(route('admin.versiones', $plan))
        // editar/desactivar/eliminar siguen siendo acciones aparte
        ->toContain('Editar plan ' . $plan->clave);

    // Versiones → Materias
    $versiones = $this->actingAs($admin)->get(route('admin.versiones', $plan))->assertOk()->getContent();
    expect($versiones)
        ->toContain('Materias')
        ->toContain(route('admin.materias', ['plan' => $plan->id, 'version' => $version->id]));
});

test('el listado global lleva a gestionar la materia dentro de su version', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $materia = Materia::factory()->paraPlanVersion($version)->create(['clave' => 'ENTR-01']);

    Livewire::actingAs($admin)->test(MateriasIndex::class)
        ->assertSee('ENTR-01')
        ->assertSee(route('admin.materias', ['plan' => $plan->id, 'version' => $version->id]), false)
        ->assertSee('#materia-' . $materia->id, false);
});

test('el CRUD de materias sigue funcionando tras la nueva entrada', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalCrear')
        ->set('clave', 'CRD-01')
        ->set('nombre', 'Materia de control')
        ->set('creditos', 4)
        ->set('periodoOrden', 1)
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Materia::where('clave', 'CRD-01')->where('plan_version_id', $version->id)->exists())->toBeTrue();

    // La nueva entrada la muestra y la ruta anidada de gestión sigue abierta.
    Livewire::actingAs($admin)->test(MateriasIndex::class)->assertSee('CRD-01');
    $this->actingAs($admin)
        ->get(route('admin.materias', ['plan' => $plan->id, 'version' => $version->id]))
        ->assertOk()
        ->assertSee('CRD-01');
});
