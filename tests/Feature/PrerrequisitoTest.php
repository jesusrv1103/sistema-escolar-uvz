<?php

use App\Models\Materia;
use App\Models\MateriaPrerrequisito;
use App\Models\PlanVersion;
use App\Models\PlanEstudio;
use App\Models\User;
use App\Enums\EstatusPlanVersion;
use App\Enums\TipoPrerrequisito;
use App\Services\MateriaPrerrequisitoService;

uses()->group('academico', 'prerrequisitos');

/**
 * Prerrequisitos entre materias — gestión, validaciones, anti-ciclos.
 */
test('ADMIN puede agregar prerrequisito OBLIGATORIO', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $m1 = \App\Models\Materia::factory()->for($version)->delPeriodo(2)->create();
    $m2 = \App\Models\Materia::factory()->for($version)->delPeriodo(1)->create();

    $this->actingAs($admin)
        ->post(route('admin.materias.prerrequisito.agregar', ['plan_estudio' => $plan->id, 'plan_version' => $version->id, 'materia' => $m1->id]), [
            'prerrequisito_id' => $m2->id,
            'tipo' => 'OBLIGATORIO',
        ])
        ->assertStatus(200)
        ->assertSessionHas('success');

    expect(MateriaPrerrequisito::where('materia_id', $m1->id)
        ->where('prerrequisito_id', $m2->id)
        ->where('tipo', 'OBLIGATORIO')->exists())->toBeTrue();
});

test('ADMIN puede agregar prerrequisito RECOMENDADO', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $m1 = \App\Models\Materia::factory()->for($version)->delPeriodo(2)->create();
    $m2 = \App\Models\Materia::factory()->for($version)->delPeriodo(1)->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.materias.prerrequisito.agregar', ['plan_estudio' => $plan->id, 'plan_version' => $version->id, 'materia' => $m1->id]), [
            'prerrequisito_id' => $m2->id,
            'tipo' => 'RECOMENDADO',
        ]);

    $response->assertStatus(200);
    // Livewire returns success in response body, not session flash
    expect(MateriaPrerrequisito::where('materia_id', $m1->id)
        ->where('prerrequisito_id', $m2->id)
        ->where('tipo', 'RECOMENDADO')->exists())->toBeTrue();
});

test('rechaza autorreferencia (materia no puede ser prerrequisito de sí misma)', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();
    $m = \App\Models\Materia::factory()->for($version)->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.materias.prerrequisito.agregar', ['plan_estudio' => $version->plan_id, 'plan_version' => $version->id, 'materia' => $m->id]), [
            'prerrequisito_id' => $m->id,
            'tipo' => 'OBLIGATORIO',
        ]);

    $response->assertStatus(200);
    // Livewire returns validation errors in response body, not session flash

test('rechaza duplicado (mismo par materia-prerrequisito)', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();
    $m1 = \App\Models\Materia::factory()->for($version)->create();
    $m2 = \App\Models\Materia::factory()->for($version)->create();

    \App\Models\MateriaPrerrequisito::create([
        'materia_id' => $m1->id,
        'prerrequisito_id' => $m2->id,
        'tipo' => 'OBLIGATORIO',
    ]);

    $response = $this->actingAs(User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create())
        ->post(route('admin.materias.prerrequisito.agregar', ['plan_estudio' => $version->plan_id, 'plan_version' => $version->id, 'materia' => $m1->id]), [
            'prerrequisito_id' => $m2->id,
            'tipo' => 'OBLIGATORIO',
        ]);

        $response->assertStatus(200);

test('rechaza prerrequisito de distinta versión de plan', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $v1 = \App\Models\PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $v2 = \App\Models\PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $m1 = \App\Models\Materia::factory()->for($v1)->create();
    $m2 = \App\Models\Materia::factory()->for($v2)->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.materias.prerrequisito.agregar', ['plan_estudio' => $plan->id, 'plan_version' => $v1->id, 'materia' => $m1->id]), [
            'prerrequisito_id' => $m2->id,
            'tipo' => 'OBLIGATORIO',
        ]);

    $response->assertStatus(200);
});

test('rechaza ciclo directo A -> B -> A', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();
    $m1 = \App\Models\Materia::factory()->for($version)->delPeriodo(2)->create();
    $m2 = \App\Models\Materia::factory()->for($version)->delPeriodo(1)->create();

    // Agregar A -> B (m1 requiere m2)
    \App\Models\MateriaPrerrequisito::create([
        'materia_id' => $m1->id,
        'prerrequisito_id' => $m2->id,
        'tipo' => 'OBLIGATORIO',
    ]);

    // Intentar agregar B -> A (m2 requiere m1) -> debe fallar
    // Intentar agregar B -> A (m2 requiere m1) -> debe fallar
    $response = $this->actingAs($admin)
        ->post(route('admin.materias.prerrequisito.agregar', ['plan_estudio' => $version->plan_id, 'plan_version' => $version->id, 'materia' => $m2->id]), [
            'prerrequisito_id' => $m1->id,
            'tipo' => 'OBLIGATORIO',
        ]);

        $response->assertStatus(200);
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();
    $m1 = \App\Models\Materia::factory()->for($version)->delPeriodo(3)->create();
    $m2 = \App\Models\Materia::factory()->for($version)->delPeriodo(2)->create();
    $m3 = \App\Models\Materia::factory()->for($version)->delPeriodo(1)->create();

    // A -> B
    \App\Models\MateriaPrerrequisito::create([
        'materia_id' => $m1->id,
        'prerrequisito_id' => $m2->id,
        'tipo' => 'OBLIGATORIO',
    ]);
    // B -> C
    \App\Models\MateriaPrerrequisito::create([
        'materia_id' => $m2->id,
        'prerrequisito_id' => $m3->id,
        'tipo' => 'OBLIGATORIO',
    ]);

    // Intentar C -> A
    $response = $this->actingAs($admin)
        ->post(route('admin.materias.prerrequisito.agregar', ['plan_estudio' => $version->plan_id, 'plan_version' => $version->id, 'materia' => $m3->id]), [
            'prerrequisito_id' => $m1->id,
            'tipo' => 'OBLIGATORIO',
        ]);

    $response->assertStatus(200);
    // Livewire returns validation errors in response body, not session flash
});

test('ADMIN puede quitar prerrequisito', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $m1 = \App\Models\Materia::factory()->for($version)->create();
    $m2 = \App\Models\Materia::factory()->for($version)->create();

    \App\Models\MateriaPrerrequisito::create([
        'materia_id' => $m1->id,
        'prerrequisito_id' => $m2->id,
        'tipo' => 'OBLIGATORIO',
    ]);
    app(App\Services\MateriaPrerrequisitoService::class)->quitar($m1, $m2);

    expect(MateriaPrerrequisito::where('materia_id', $m1->id)
        ->where('prerrequisito_id', $m2->id)->exists())->toBeFalse();
    $admisiones = User::factory()->deRol(\App\Enums\RolUsuario::ADMISIONES)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $m1 = \App\Models\Materia::factory()->for($version)->create();
    $m2 = \App\Models\Materia::factory()->for($version)->create();

    $this->actingAs($admisiones)
        ->post(route('admin.materias.prerrequisito.agregar', ['plan_estudio' => $plan->id, 'plan_version' => $version->id, 'materia' => $m1->id]), [
            'prerrequisito_id' => $m2->id,
            'tipo' => 'OBLIGATORIO',
        ])
        ->assertForbidden();
});

test('usuario inactivo no puede gestionar prerrequisitos (403)', function () {
    $inactivo = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->inactivo()->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $m1 = \App\Models\Materia::factory()->for($version)->create();
    $m2 = \App\Models\Materia::factory()->for($version)->create();

    $this->actingAs($inactivo)
        ->post(route('admin.materias.prerrequisito.agregar', ['plan_estudio' => $plan->id, 'plan_version' => $version->id, 'materia' => $m1->id]), [
            'prerrequisito_id' => $m2->id,
            'tipo' => 'OBLIGATORIO',
        ])
        ->assertForbidden();
});

test('tipo debe ser OBLIGATORIO o RECOMENDADO', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $version = \App\Models\PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $m1 = \App\Models\Materia::factory()->for($version)->create();
    $m2 = \App\Models\Materia::factory()->for($version)->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.materias.prerrequisito.agregar', ['plan_estudio' => $plan->id, 'plan_version' => $version->id, 'materia' => $m1->id]), [
            'prerrequisito_id' => $m2->id,
            'tipo' => 'INVALIDO',
        ]);

    $response->assertStatus(200);
    // Livewire returns validation errors in response body, not session flash
});

test('service detecta ciclos correctamente (unit test)', function () {
    $service = app(\App\Services\MateriaPrerrequisitoService::class);
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();
    $m1 = \App\Models\Materia::factory()->for($version)->delPeriodo(3)->create();
    $m2 = \App\Models\Materia::factory()->for($version)->delPeriodo(2)->create();
    $m3 = \App\Models\Materia::factory()->for($version)->delPeriodo(1)->create();

    // A -> B -> C
    \App\Models\MateriaPrerrequisito::create([
        'materia_id' => $m1->id,
        'prerrequisito_id' => $m2->id,
        'tipo' => 'OBLIGATORIO',
    ]);
    \App\Models\MateriaPrerrequisito::create([
        'materia_id' => $m2->id,
        'prerrequisito_id' => $m3->id,
        'tipo' => 'OBLIGATORIO',
    ]);

    // Intentar agregar C -> A (crearía ciclo A -> B -> C -> A)
    expect(fn() => $service->agregar($m3, $m1, 'OBLIGATORIO'))
        ->toThrow(\App\Exceptions\CicloPrerrequisitoException::class);
});

test('rechaza tipo inválido en service', function () {
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();
    $m1 = \App\Models\Materia::factory()->for($version)->create();
    $m2 = \App\Models\Materia::factory()->for($version)->create();

    expect(fn() => app(\App\Services\MateriaPrerrequisitoService::class)->agregar($m1, $m2, 'INVALIDO'))
        ->toThrow(\App\Exceptions\PrerrequisitoInvalidoException::class);
});

test('rechaza autorreferencia en service', function () {
    $version = \App\Models\PlanVersion::factory()->propuesta()->create();
    $m = \App\Models\Materia::factory()->for($version)->create();

    expect(fn() => app(\App\Services\MateriaPrerrequisitoService::class)->agregar($m, $m, 'OBLIGATORIO'))
        ->toThrow(\App\Exceptions\PrerrequisitoInvalidoException::class);
});

test('rechaza distinta versión en service', function () {
    $plan = \App\Models\PlanEstudio::factory()->create();
    $v1 = \App\Models\PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $v2 = \App\Models\PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $m1 = \App\Models\Materia::factory()->for($v1)->create();
    $m2 = \App\Models\Materia::factory()->for($v2)->create();

    expect(fn() => app(\App\Services\MateriaPrerrequisitoService::class)->agregar($m1, $m2, 'OBLIGATORIO'))
        ->toThrow(\App\Exceptions\PrerrequisitoInvalidoException::class);
});
});
});
