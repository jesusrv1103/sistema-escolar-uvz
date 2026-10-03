<?php

use App\Models\Materia;
use App\Models\PlanVersion;
use App\Models\PlanEstudio;
use App\Models\User;
use App\Enums\EstatusPlanVersion;

uses()->group('academico', 'materias');

/**
 * Materias — CRUD, validaciones, autorización, solo editable en PROPUESTA.
 */
test('ADMIN puede crear una materia en versión PROPUESTA', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();

    $this->actingAs($admin)
        ->post(route('admin.materias', ['plan' => $version->plan_id, 'version' => $version->id]), [
            'clave' => 'MAT-101',
            'nombre' => 'Matemáticas I',
            'creditos' => 6,
            'horas_teoricas' => 48,
            'horas_practicas' => 16,
            'periodo_orden' => 1,
            'bloque' => 'TRONCO_COMUN',
            'obligatoria' => true,
            'activo' => true,
        ])
        ->assertStatus(200)
        ->assertSessionHas('success');

    expect(Materia::where('clave', 'MAT-101')->where('plan_version_id', $version->id)->exists())->toBeTrue();
});

test('materia tiene clave única por versión', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();

    $this->actingAs($admin)
        ->post(route('admin.materias', ['plan' => $version->plan_id, 'version' => $version->id]), [
            'clave' => 'MAT-101',
            'nombre' => 'Matemáticas I',
            'creditos' => 6,
            'periodo_orden' => 1,
        ])
        ->assertStatus(200);

    $this->actingAs($admin)
        ->post(route('admin.materias', ['plan' => $version->plan_id, 'version' => $version->id]), [
            'clave' => 'MAT-101',
            'nombre' => 'Matemáticas II',
            'creditos' => 6,
            'periodo_orden' => 2,
        ])
        ->assertStatus(200)
        ->assertSessionHasErrors('clave');
});

test('misma clave permitida en versión distinta', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $v1 = \App\Models\PlanVersion::factory()->propuesta()->create();
    $v2 = \App\Models\PlanVersion::factory()->propuesta()->create();

    $this->actingAs($admin)
        ->post(route('admin.materias', ['plan' => $v1->plan_id, 'version' => $v1->id]), [
            'clave' => 'MAT-101',
            'nombre' => 'Matemáticas I',
            'creditos' => 6,
            'periodo_orden' => 1,
        ])
        ->assertStatus(200);

    $this->actingAs($admin)
        ->post(route('admin.materias', ['plan' => $v2->plan_id, 'version' => $v2->id]), [
            'clave' => 'MAT-101',
            'nombre' => 'Matemáticas I',
            'creditos' => 6,
            'periodo_orden' => 1,
        ])
        ->assertStatus(200);
});

test('ADMIN puede editar materia en versión PROPUESTA', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();
    $materia = \App\Models\Materia::factory()->for($version)->create(['nombre' => 'Original']);

    $this->actingAs($admin)
        ->put(route('admin.materias.actualizar', $materia->id), [
            'clave' => $materia->clave,
            'nombre' => 'Actualizada',
            'creditos' => 5,
            'periodo_orden' => 2,
            'activo' => true,
        ])
        ->assertStatus(200)
        ->assertSessionHas('success');

    expect($materia->fresh()->nombre)->toBe('Actualizada')
        ->and($materia->fresh()->creditos)->toBe(5)
        ->and($materia->fresh()->periodo_orden)->toBe(2);
});

test('no se puede editar materia en versión VIGENTE', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = \App\Models\PlanVersion::factory()->vigente()->create();
    $materia = \App\Models\Materia::factory()->for($version)->create();

    $this->actingAs(\App\Models\User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create())
        ->put(route('admin.materias.actualizar', $materia->id), [
            'clave' => $materia->clave,
            'nombre' => 'Cambiado',
            'creditos' => 5,
            'periodo_orden' => 2,
            'activo' => true,
        ])
        ->assertStatus(200)
        ->assertSessionHasErrors('materia');
});

test('no se puede editar materia en versión SUPERADA', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = \App\Models\PlanVersion::factory()->superada()->create();
    $materia = \App\Models\Materia::factory()->for($version)->create();

    $this->actingAs(\App\Models\User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create())
        ->put(route('admin.materias.actualizar', $materia->id), [
            'clave' => $materia->clave,
            'nombre' => 'Cambiado',
            'creditos' => 5,
            'periodo_orden' => 2,
            'activo' => true,
        ])
        ->assertStatus(200)
        ->assertSessionHasErrors('materia');
});

test('ADMIN puede activar/desactivar materia en PROPUESTA', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();
    $materia = \App\Models\Materia::factory()->for($version)->create(['activo' => true]);

    $this->actingAs($admin)
        ->post(route('admin.materias.alternar', $materia->id))
        ->assertStatus(200)
        ->assertSessionHas('success');

    expect($materia->fresh()->activo)->toBeFalse();
});

test('no se puede alternar activo en VIGENTE', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = \App\Models\PlanVersion::factory()->vigente()->create();
    $materia = \App\Models\Materia::factory()->for($version)->create();

    $this->actingAs($admin)
        ->post(route('admin.materias.alternar', $materia->id))
        ->assertStatus(200)
        ->assertSessionHasErrors('materia');
});

test('solo se puede eliminar materia en PROPUESTA', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = \App\Models\PlanVersion::factory()->vigente()->create();
    $materia = \App\Models\Materia::factory()->for($version)->create();

    $this->actingAs($admin)
        ->delete(route('admin.materias.eliminar', $materia->id))
        ->assertStatus(200)
        ->assertSessionHasErrors('materia');
});

test('validación: creditos >= 1', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();

    $this->actingAs($admin)
        ->post(route('admin.materias', ['plan' => $version->plan_id, 'version' => $version->id]), [
            'clave' => 'MAT-101',
            'nombre' => 'Test',
            'creditos' => 0,
            'periodo_orden' => 1,
        ])
        ->assertStatus(200)
        ->assertSessionHasErrors('creditos');
});

test('validación: periodo_orden >= 1', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();

    $this->actingAs($admin)
        ->post(route('admin.materias', ['plan' => $version->plan_id, 'version' => $version->id]), [
            'clave' => 'MAT-101',
            'nombre' => 'Test',
            'creditos' => 3,
            'periodo_orden' => 0,
        ])
        ->assertStatus(200)
        ->assertSessionHasErrors('periodoOrden');
});

test('ADMISIONES no puede crear materia (403)', function () {
    $admisiones = User::factory()->deRol(\App\Enums\RolUsuario::ADMISIONES)->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();

    $this->actingAs($admisiones)
        ->post(route('admin.materias', ['plan' => $version->plan_id, 'version' => $version->id]), [
            'clave' => 'MAT-101',
            'nombre' => 'Test',
            'creditos' => 3,
            'periodo_orden' => 1,
        ])
        ->assertForbidden();
});

test('usuario inactivo no puede crear materia (403)', function () {
    $inactivo = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->inactivo()->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();

    $this->actingAs($inactivo)
        ->post(route('admin.materias', ['plan' => $version->plan_id, 'version' => $version->id]), [
            'clave' => 'MAT-101',
            'nombre' => 'Test',
            'creditos' => 3,
            'periodo_orden' => 1,
        ])
        ->assertForbidden();
});

test('invitado es redirigido a login', function () {
    $this->get(route('admin.materias', ['plan' => 1, 'version' => 1]))
        ->assertRedirect(route('login'));
});

test('creditos >= 1 validación', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();

    $this->actingAs($admin)
        ->post(route('admin.materias', ['plan' => $version->plan_id, 'version' => $version->id]), [
            'clave' => 'MAT-101',
            'nombre' => 'Test',
            'creditos' => 0,
            'periodo_orden' => 1,
        ])
        ->assertStatus(200)
        ->assertSessionHasErrors('creditos');
});

test('clave formato: solo A-Z, 0-9, guión y guion bajo', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();

    $this->actingAs($admin)
        ->post(route('admin.materias', ['plan' => $version->plan_id, 'version' => $version->id]), [
            'clave' => 'clave-invalida!',
            'nombre' => 'Test',
            'creditos' => 3,
            'periodo_orden' => 1,
        ])
        ->assertStatus(200)
        ->assertSessionHasErrors('clave');
});
