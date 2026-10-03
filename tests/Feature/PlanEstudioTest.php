<?php

use App\Enums\RolUsuario;
use App\Livewire\Academico\PlanEstudioAdmin;
use App\Models\PlanEstudio;
use App\Models\Programa;
use App\Models\User;
use Livewire\Livewire;

uses()->group('academico', 'planes_estudio');

/**
 * Planes de estudio — CRUD, validaciones, autorización.
 */
test('ADMIN puede crear un plan de estudio', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $programa = Programa::factory()->create(['activo' => true]);

    Livewire::actingAs($admin)->test(PlanEstudioAdmin::class)
        ->call('abrirModalCrear')
        ->set('clave', 'DER-2024')
        ->set('nombre', 'Derecho 2024')
        ->set('resolucionRvoe', 'RVOE-12345')
        ->set('fechaResolucion', '2024-01-15')
        ->set('activo', true)
        ->set('programaId', $programa->id)
        ->call('guardar')
        ->assertHasNoErrors();

    expect(PlanEstudio::where('clave', 'DER-2024')->where('programa_id', $programa->id)->exists())->toBeTrue();
});

test('ADMIN no puede crear plan con clave duplicada en el mismo programa', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $programa = Programa::factory()->create(['activo' => true]);
    PlanEstudio::factory()->create(['programa_id' => $programa->id, 'clave' => 'DER-2024']);

    Livewire::actingAs($admin)->test(PlanEstudioAdmin::class)
        ->call('abrirModalCrear')
        ->set('clave', 'DER-2024')
        ->set('nombre', 'Derecho 2024 bis')
        ->set('activo', true)
        ->set('programaId', $programa->id)
        ->call('guardar')
        ->assertHasErrors(['clave']);

    expect(PlanEstudio::where('clave', 'DER-2024')->where('programa_id', $programa->id)->count())->toBe(1);
});

test('ADMIN puede crear planes con misma clave en programas distintos', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $programa1 = Programa::factory()->create(['activo' => true, 'clave' => 'DER']);
    $programa2 = Programa::factory()->create(['activo' => true, 'clave' => 'SAI']);

    Livewire::actingAs($admin)->test(PlanEstudioAdmin::class)
        ->call('abrirModalCrear')
        ->set('clave', 'PLAN-001')
        ->set('nombre', 'Plan Derecho')
        ->set('activo', true)
        ->set('programaId', $programa1->id)
        ->call('guardar')
        ->assertHasNoErrors();

    Livewire::actingAs($admin)->test(PlanEstudioAdmin::class)
        ->call('abrirModalCrear')
        ->set('clave', 'PLAN-001')
        ->set('nombre', 'Plan Seguridad')
        ->set('activo', true)
        ->set('programaId', $programa2->id)
        ->call('guardar')
        ->assertHasNoErrors();

    expect(PlanEstudio::where('clave', 'PLAN-001')->count())->toBe(2);
});

test('ADMIN puede editar un plan de estudio', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();

    Livewire::actingAs($admin)->test(PlanEstudioAdmin::class)
        ->call('abrirModalEditar', $plan)
        ->set('clave', $plan->clave)
        ->set('nombre', 'Nombre Actualizado')
        ->set('activo', false)
        ->set('programaId', $plan->programa_id)
        ->call('guardar')
        ->assertHasNoErrors();

    expect($plan->fresh()->nombre)->toBe('Nombre Actualizado')
        ->and($plan->fresh()->activo)->toBeFalse();
});

test('ADMIN no puede editar clave de un plan', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create(['clave' => 'ORIGINAL']);

    Livewire::actingAs($admin)->test(PlanEstudioAdmin::class)
        ->call('abrirModalEditar', $plan)
        ->set('clave', 'CAMBIADA')
        ->set('nombre', $plan->nombre)
        ->set('activo', $plan->activo)
        ->set('programaId', $plan->programa_id)
        ->call('guardar')
        ->assertHasErrors(['clave']);
});

test('ADMIN puede activar/desactivar plan (soft delete)', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create(['activo' => true]);

    Livewire::actingAs($admin)->test(PlanEstudioAdmin::class)
        ->call('alternarActivo', $plan)
        ->assertHasNoErrors();

    expect($plan->fresh()->activo)->toBeFalse();

    Livewire::actingAs($admin)->test(PlanEstudioAdmin::class)
        ->call('alternarActivo', $plan)
        ->assertHasNoErrors();

    expect($plan->fresh()->activo)->toBeTrue();
});

test('ADMIN puede eliminar plan (soft delete)', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();

    Livewire::actingAs($admin)->test(PlanEstudioAdmin::class)
        ->call('eliminar', $plan)
        ->assertHasNoErrors();

    expect(PlanEstudio::withTrashed()->find($plan->id))->not->toBeNull()
        ->and(PlanEstudio::find($plan->id))->toBeNull();
});

test('ADMISIONES no puede acceder a planes de estudio (403)', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();

    Livewire::actingAs($admisiones)->test(PlanEstudioAdmin::class)
        ->assertForbidden();
});

test('ADMINISTRACION no puede acceder a planes de estudio (403)', function () {
    $adminUser = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();

    Livewire::actingAs($adminUser)->test(PlanEstudioAdmin::class)
        ->assertForbidden();
});

test('CONTROL_ESCOLAR no puede acceder a planes de estudio (403)', function () {
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    Livewire::actingAs($control)->test(PlanEstudioAdmin::class)
        ->assertForbidden();
});

test('usuario inactivo no puede acceder a planes (403)', function () {
    $inactivo = User::factory()->deRol(RolUsuario::ADMIN)->inactivo()->create();

    Livewire::actingAs($inactivo)->test(PlanEstudioAdmin::class)
        ->assertForbidden();
});

test('invitado es redirigido a login', function () {
    $this->get(route('admin.planes-estudio'))
        ->assertRedirect(route('login'));
});

test('validación de clave: solo A-Z, 0-9, guión y guion bajo', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $programa = Programa::factory()->create(['activo' => true]);

    Livewire::actingAs($admin)->test(PlanEstudioAdmin::class)
        ->call('abrirModalCrear')
        ->set('clave', 'clave-invalida!')
        ->set('nombre', 'Plan Inválido')
        ->set('activo', true)
        ->set('programaId', $programa->id)
        ->call('guardar')
        ->assertHasErrors(['clave']);
});