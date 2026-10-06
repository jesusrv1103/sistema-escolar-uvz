<?php

use App\Enums\RolUsuario;
use App\Livewire\Academico\PlanEstudioAdmin;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Periodo;
use App\Models\PlanEstudio;
use App\Models\Programa;
use App\Models\User;
use Livewire\Livewire;

uses()->group('ui', 'ui-shell');

/*
|--------------------------------------------------------------------------
| WAVE UI/UX 2.0-12 — pruebas de la capa de presentación
|--------------------------------------------------------------------------
|
| Solo render/UX: app shell, estados vacíos, modal y feedback de envío.
| No tocan reglas de negocio ni sustituyen las pruebas funcionales.
|
*/

test('la pagina de planes de estudio responde y trae el app shell', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $this->actingAs($admin)
        ->get(route('admin.planes-estudio'))
        ->assertOk()
        ->assertSee('id="app-sidebar"', false)
        ->assertSee('Planes de estudio', false)
        ->assertSee('Nuevo plan', false);
});

test('el sidebar solo muestra secciones autorizadas para el rol', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();

    $html = $this->actingAs($admisiones)->get(route('alumnos.lista'))->assertOk()->getContent();

    expect($html)
        ->toContain('Alumnos')
        ->toContain('Inscripciones')
        ->not->toContain('Tipos de documento');
});

test('el listado muestra estado vacio con accion cuando no hay planes', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    Livewire::actingAs($admin)->test(PlanEstudioAdmin::class)
        ->assertSee('No hay planes de estudio')
        ->assertSee('Crear plan', false);
});

test('el listado muestra la tabla con badges cuando hay planes', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    Programa::factory()->create(['activo' => true]);
    PlanEstudio::factory()->create(['activo' => true]);

    Livewire::actingAs($admin)->test(PlanEstudioAdmin::class)
        ->assertSee('ui-table', false)
        ->assertSee('Activo');
});

test('el modal de crear plan permanece oculto hasta que se abre', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $cerrado = Livewire::actingAs($admin)->test(PlanEstudioAdmin::class);
    $htmlCerrado = $cerrado->html();
    expect($htmlCerrado)->toContain('id="modal-plan"')
        ->and(explode('id="modal-plan"', $htmlCerrado)[1])->toStartWith(' hidden');

    $htmlAbierto = $cerrado->call('abrirModalCrear')->html();
    expect(explode('id="modal-plan"', $htmlAbierto)[1])->not->toStartWith(' hidden');
});

test('el boton de guardar se deshabilita mientras Livewire procesa', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    Livewire::actingAs($admin)->test(PlanEstudioAdmin::class)
        ->call('abrirModalCrear')
        ->assertSee('wire:loading.attr="disabled"', false);
});

test('limpiar filtros restablece la busqueda y las listas', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    Livewire::actingAs($admin)->test(PlanEstudioAdmin::class)
        ->set('busqueda', 'DER')
        ->set('filtroActivo', '1')
        ->call('limpiarFiltros')
        ->assertSet('busqueda', '')
        ->assertSet('filtroActivo', '');
});

test('el listado de grupos responde y muestra el turno en texto plano', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $materia = Materia::factory()->create();
    $periodo = Periodo::factory()->create();
    Grupo::factory()->creado()->paraMateria($materia)->paraPeriodo($periodo)
        ->create(['turno' => 'MATUTINO']);

    $this->actingAs($admin)
        ->get(route('admin.grupos'))
        ->assertOk()
        ->assertSee('Matutino', false)
        ->assertSee($materia->clave, false);
});
