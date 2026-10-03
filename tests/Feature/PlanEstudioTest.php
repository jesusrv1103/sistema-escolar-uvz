<?php

use App\Enums\EstatusPlanVersion;
use App\Models\PlanEstudio;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

uses()->group('academico', 'planes_estudio');

/**
 * Planes de estudio — CRUD, validaciones, autorización.
 */
test('ADMIN puede crear un plan de estudio', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $programa = Programa::factory()->create(['activo' => true]);

    $this->actingAs($admin)
        ->post(route('admin.planes-estudio'), [
            'programa_id' => $programa->id,
            'clave' => 'DER-2024',
            'nombre' => 'Derecho 2024',
            'resolucion_rvoe' => 'RVOE-12345',
            'fecha_resolucion' => '2024-01-15',
            'activo' => true,
        ])
        ->assertStatus(200)
        ->assertSessionHas('success');

    expect(PlanEstudio::where('clave', 'DER-2024')->where('programa_id', $programa->id)->exists())->toBeTrue();
});

test('ADMIN no puede crear plan con clave duplicada en el mismo programa', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $programa = Programa::factory()->create(['activo' => true]);
    PlanEstudio::factory()->create(['programa_id' => $programa->id, 'clave' => 'DER-2024']);

    $this->actingAs($admin)
        ->post(route('admin.planes-estudio'), [
            'programa_id' => $programa->id,
            'clave' => 'DER-2024',
            'nombre' => 'Derecho 2024 bis',
            'activo' => true,
        ])
        ->assertStatus(200)
        ->assertSessionHasErrors('clave');
});

test('ADMIN puede crear planes con misma clave en programas distintos', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $programa1 = Programa::factory()->create(['activo' => true, 'clave' => 'DER']);
    $programa2 = Programa::factory()->create(['activo' => true, 'clave' => 'SAI']);

    $this->actingAs($admin)
        ->post(route('admin.planes-estudio'), [
            'programa_id' => $programa1->id,
            'clave' => 'PLAN-001',
            'nombre' => 'Plan Derecho',
            'activo' => true,
        ])
        ->assertStatus(200);

    $this->actingAs($admin)
        ->post(route('admin.planes-estudio'), [
            'programa_id' => $programa2->id,
            'clave' => 'PLAN-001',
            'nombre' => 'Plan Seguridad',
            'activo' => true,
        ])
        ->assertStatus(200);

    expect(PlanEstudio::where('clave', 'PLAN-001')->count())->toBe(2);
});

test('ADMIN puede editar un plan de estudio', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.planes-estudio'), [
            'id' => $plan->id,
            'clave' => $plan->clave,
            'nombre' => 'Nombre Actualizado',
            'activo' => false,
        ])
        ->assertStatus(200)
        ->assertSessionHas('success');

    expect($plan->fresh()->nombre)->toBe('Nombre Actualizado')
        ->and($plan->fresh()->activo)->toBeFalse();
});

test('ADMIN no puede editar clave de un plan', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create(['clave' => 'ORIGINAL']);

    $this->actingAs($admin)
        ->put(route('admin.planes-estudio'), [
            'id' => $plan->id,
            'clave' => 'CAMBIADA',
            'nombre' => $plan->nombre,
            'activo' => $plan->activo,
        ])
        ->assertStatus(200)
        ->assertSessionHasErrors('clave');
});

test('ADMIN puede activar/desactivar plan (soft delete)', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create(['activo' => true]);

    $this->actingAs($admin)
        ->post(route('admin.planes-estudio.alternar', $plan->id))
        ->assertStatus(200);

    expect($plan->fresh()->activo)->toBeFalse();

    $this->actingAs($admin)
        ->post(route('admin.planes-estudio.alternar', $plan->id))
        ->assertStatus(200);

    expect($plan->fresh()->activo)->toBeTrue();
});

test('ADMIN puede eliminar plan (soft delete)', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.planes-estudio.eliminar', $plan->id))
        ->assertStatus(200)
        ->assertSessionHas('success');

    expect(PlanEstudio::withTrashed()->find($plan->id))->not->toBeNull()
        ->and(PlanEstudio::find($plan->id))->toBeNull();
});

test('ADMISIONES no puede acceder a planes de estudio (403)', function () {
    $admisiones = User::factory()->deRol(\App\Enums\RolUsuario::ADMISIONES)->create();

    $this->actingAs($admisiones)
        ->get(route('admin.planes-estudio'))
        ->assertForbidden();
});

test('ADMINISTRACION no puede acceder a planes de estudio (403)', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMINISTRACION)->create();

    $this->actingAs($admin)
        ->get(route('admin.planes-estudio'))
        ->assertForbidden();
});

test('CONTROL_ESCOLAR no puede acceder a planes de estudio (403)', function () {
    $control = User::factory()->deRol(\App\Enums\RolUsuario::CONTROL_ESCOLAR)->create();

    $this->actingAs($control)
        ->get(route('admin.planes-estudio'))
        ->assertForbidden();
});

test('usuario inactivo no puede acceder a planes (403)', function () {
    $inactivo = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->inactivo()->create();

    $this->actingAs($inactivo)
        ->get(route('admin.planes-estudio'))
        ->assertForbidden();
});

test('invitado es redirigido a login', function () {
    $this->get(route('admin.planes-estudio'))
        ->assertRedirect(route('login'));
});

test('validación de clave: solo A-Z, 0-9, guión y guion bajo', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $programa = Programa::factory()->create(['activo' => true]);

    $this->actingAs($admin)
        ->post(route('admin.planes-estudio'), [
            'programa_id' => $programa->id,
            'clave' => 'clave-invalida!',
            'nombre' => 'Plan Inválido',
            'activo' => true,
        ])
        ->assertStatus(200)
        ->assertSessionHasErrors('clave');
});