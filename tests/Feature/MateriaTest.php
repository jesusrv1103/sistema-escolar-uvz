<?php

use App\Models\Materia;
use App\Models\PlanVersion;
use App\Models\PlanEstudio;
use App\Models\User;
use App\Enums\EstatusPlanVersion;
use App\Enums\RolUsuario;
use App\Livewire\Academico\MateriaAdmin;
use Livewire\Livewire;

uses()->group('academico', 'materias');

/**
 * Materias — CRUD, validaciones, autorización, solo editable en PROPUESTA.
 */
test('ADMIN puede crear una materia en versión PROPUESTA', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalCrear')
        ->set('clave', 'MAT-101')
        ->set('nombre', 'Matemáticas I')
        ->set('creditos', 6)
        ->set('horasTeoricas', 48)
        ->set('horasPracticas', 16)
        ->set('periodoOrden', 1)
        ->set('bloque', 'TRONCO_COMUN')
        ->set('obligatoria', true)
        ->set('activo', true)
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Materia::where('clave', 'MAT-101')->where('plan_version_id', $version->id)->exists())->toBeTrue();
});

test('materia tiene clave única por versión', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalCrear')
        ->set('clave', 'MAT-101')
        ->set('nombre', 'Matemáticas I')
        ->set('creditos', 6)
        ->set('periodoOrden', 1)
        ->call('guardar')
        ->assertHasNoErrors();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalCrear')
        ->set('clave', 'MAT-101')
        ->set('nombre', 'Matemáticas II')
        ->set('creditos', 6)
        ->set('periodoOrden', 2)
        ->call('guardar')
        ->assertHasErrors(['clave']);
});

test('misma clave permitida en versión distinta', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $v1 = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $v2 = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $v1])
        ->call('abrirModalCrear')
        ->set('clave', 'MAT-101')
        ->set('nombre', 'Matemáticas I')
        ->set('creditos', 6)
        ->set('periodoOrden', 1)
        ->call('guardar')
        ->assertHasNoErrors();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $v2])
        ->call('abrirModalCrear')
        ->set('clave', 'MAT-101')
        ->set('nombre', 'Matemáticas I')
        ->set('creditos', 6)
        ->set('periodoOrden', 1)
        ->call('guardar')
        ->assertHasNoErrors();
});

test('ADMIN puede editar materia en versión PROPUESTA', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->propuesta()->create();
    $materia = Materia::factory()->for($version)->create(['nombre' => 'Original']);

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalEditar', $materia)
        ->set('clave', $materia->clave)
        ->set('nombre', 'Actualizada')
        ->set('creditos', 5)
        ->set('periodoOrden', 2)
        ->set('activo', true)
        ->call('guardar')
        ->assertHasNoErrors();

    expect($materia->fresh()->nombre)->toBe('Actualizada')
        ->and($materia->fresh()->creditos)->toBe(5)
        ->and($materia->fresh()->periodo_orden)->toBe(2);
});

test('no se puede editar materia en versión VIGENTE', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->vigente()->create();
    $materia = Materia::factory()->for($version)->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalEditar', $materia)
        ->assertHasErrors(['materia']);
});

test('no se puede editar materia en versión SUPERADA', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->superada()->create();
    $materia = Materia::factory()->for($version)->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalEditar', $materia)
        ->assertHasErrors(['materia']);
});

test('ADMIN puede activar/desactivar materia en PROPUESTA', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->propuesta()->create();
    $materia = Materia::factory()->for($version)->create(['activo' => true]);

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('alternarActivo', $materia)
        ->assertHasNoErrors();

    expect($materia->fresh()->activo)->toBeFalse();
});

test('no se puede alternar activo en VIGENTE', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->vigente()->create();
    $materia = Materia::factory()->for($version)->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('alternarActivo', $materia)
        ->assertHasErrors(['materia']);
});

test('solo se puede eliminar materia en PROPUESTA', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->vigente()->create();
    $materia = Materia::factory()->for($version)->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('eliminar', $materia)
        ->assertHasErrors(['materia']);
});

test('validación: creditos >= 1', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->propuesta()->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalCrear')
        ->set('clave', 'MAT-101')
        ->set('nombre', 'Test')
        ->set('creditos', 0)
        ->set('periodoOrden', 1)
        ->call('guardar')
        ->assertHasErrors(['creditos']);
});

test('validación: periodo_orden >= 1', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->propuesta()->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalCrear')
        ->set('clave', 'MAT-101')
        ->set('nombre', 'Test')
        ->set('creditos', 3)
        ->set('periodoOrden', 0)
        ->call('guardar')
        ->assertHasErrors(['periodoOrden']);
});

test('ADMISIONES no puede crear materia (403)', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $version = PlanVersion::factory()->propuesta()->create();

    Livewire::actingAs($admisiones)->test(MateriaAdmin::class, ['version' => $version])
        ->assertForbidden();
});

test('usuario inactivo no puede crear materia (403)', function () {
    $inactivo = User::factory()->deRol(RolUsuario::ADMIN)->inactivo()->create();
    $version = PlanVersion::factory()->propuesta()->create();

    Livewire::actingAs($inactivo)->test(MateriaAdmin::class, ['version' => $version])
        ->assertForbidden();
});

test('invitado es redirigido a login', function () {
    $this->get(route('admin.materias', ['plan' => 1, 'version' => 1]))
        ->assertRedirect(route('login'));
});

test('creditos >= 1 validación', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->propuesta()->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalCrear')
        ->set('clave', 'MAT-101')
        ->set('nombre', 'Test')
        ->set('creditos', 0)
        ->set('periodoOrden', 1)
        ->call('guardar')
        ->assertHasErrors(['creditos']);
});

test('clave formato: solo A-Z, 0-9, guión y guion bajo', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->propuesta()->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalCrear')
        ->set('clave', 'clave-invalida!')
        ->set('nombre', 'Test')
        ->set('creditos', 3)
        ->set('periodoOrden', 1)
        ->call('guardar')
        ->assertHasErrors(['clave']);
});