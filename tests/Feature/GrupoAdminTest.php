<?php

use App\Enums\RolUsuario;
use App\Livewire\Academico\GrupoAdmin;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Periodo;
use App\Models\User;
use Livewire\Livewire;

uses()->group('academico', 'grupos');

/*
|--------------------------------------------------------------------------
| Regresión WAVE UI/UX 2.0-12 — acciones de fila y formulario de Grupo
|--------------------------------------------------------------------------
|
| Dos defectos reproducidos antes del arreglo:
| 1. `GrupoAdmin` llamaba `esCreado()`, método inexistente en el modelo
|    (BadMethodCallException al editar o alternar un grupo).
| 2. `nombre` y `activo` no estaban en `$fillable`, así que el campo
|    "Nombre del grupo" y el botón Activar/Desactivar no persistían nada.
|
*/

function grupoSencillo(array $estado = []): Grupo
{
    return Grupo::factory()->creado()
        ->paraMateria(Materia::factory()->create())
        ->paraPeriodo(Periodo::factory()->create())
        ->create($estado + ['activo' => true]);
}

test('un grupo en estado Creado se puede alternar de activo a inactivo', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $grupo = grupoSencillo();

    expect($grupo->esCreado())->toBeTrue();

    Livewire::actingAs($admin)->test(GrupoAdmin::class)
        ->call('alternarActivo', $grupo->getKey())
        ->assertHasNoErrors();

    expect((bool) $grupo->fresh()->activo)->toBeFalse();
});

test('un grupo fuera de estado Creado no se puede alternar', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $grupo = grupoSencillo();
    $grupo->update(['estatus' => 'ABIERTO']);

    expect($grupo->fresh()->esCreado())->toBeFalse();

    Livewire::actingAs($admin)->test(GrupoAdmin::class)
        ->call('alternarActivo', $grupo->getKey())
        ->assertHasErrors(['grupo']);

    expect((bool) $grupo->fresh()->activo)->toBeTrue();
});

test('crear un grupo persiste el nombre y deja el activo por defecto', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $materia = Materia::factory()->create();
    $periodo = Periodo::factory()->create();

    Livewire::actingAs($admin)->test(GrupoAdmin::class)
        ->call('abrirModalCrear')
        ->set('clave', 'GRP-01')
        ->set('nombre', 'Nombre escrito por el usuario')
        ->set('cupo', 40)
        ->set('turno', 'MATUTINO')
        ->set('materiaId', $materia->getKey())
        ->set('periodoId', $periodo->getKey())
        ->set('estatus', 'CREADO')
        ->call('guardar')
        ->assertHasNoErrors();

    $grupo = Grupo::sole();

    expect($grupo->nombre)->toBe('Nombre escrito por el usuario')
        ->and((bool) $grupo->activo)->toBeTrue()
        ->and($grupo->esCreado())->toBeTrue();
});

test('crear un grupo sin nombre genera el nombre automaticamente', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $materia = Materia::factory()->create();
    $periodo = Periodo::factory()->create();

    Livewire::actingAs($admin)->test(GrupoAdmin::class)
        ->call('abrirModalCrear')
        ->set('clave', 'GRP-02')
        ->set('cupo', 30)
        ->set('turno', 'VESPERTINO')
        ->set('materiaId', $materia->getKey())
        ->set('periodoId', $periodo->getKey())
        ->set('estatus', 'CREADO')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Grupo::sole()->nombre)->toBe("{$materia->nombre} - Grupo GRP-02");
});

test('editar un grupo persiste el nombre modificado', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $grupo = grupoSencillo(['nombre' => 'Nombre original']);

    Livewire::actingAs($admin)->test(GrupoAdmin::class)
        ->call('abrirModalEditar', $grupo->getKey())
        ->set('nombre', 'Nombre renombrado')
        ->call('guardar')
        ->assertHasNoErrors();

    expect($grupo->fresh()->nombre)->toBe('Nombre renombrado');
});
