<?php

use App\Models\Materia;
use App\Models\MateriaPrerrequisito;
use App\Models\PlanVersion;
use App\Models\PlanEstudio;
use App\Models\User;
use App\Enums\EstatusPlanVersion;
use App\Enums\RolUsuario;
use App\Enums\TipoPrerrequisito;
use App\Services\MateriaPrerrequisitoService;
use App\Livewire\Academico\MateriaAdmin;
use Livewire\Livewire;

uses()->group('academico', 'prerrequisitos');

/**
 * Prerrequisitos entre materias — gestión, validaciones, anti-ciclos.
 */
test('ADMIN puede agregar prerrequisito OBLIGATORIO', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $m1 = Materia::factory()->for($version)->delPeriodo(2)->create();
    $m2 = Materia::factory()->for($version)->delPeriodo(1)->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalPrerreq', $m1)
        ->set('tipoPrerreq', 'OBLIGATORIO')
        ->call('agregarPrerrequisito', $m2)
        ->assertHasNoErrors();

    expect(MateriaPrerrequisito::where('materia_id', $m1->id)
        ->where('prerrequisito_id', $m2->id)
        ->where('tipo', 'OBLIGATORIO')->exists())->toBeTrue();
});

test('ADMIN puede agregar prerrequisito RECOMENDADO', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $m1 = Materia::factory()->for($version)->delPeriodo(2)->create();
    $m2 = Materia::factory()->for($version)->delPeriodo(1)->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalPrerreq', $m1)
        ->set('tipoPrerreq', 'RECOMENDADO')
        ->call('agregarPrerrequisito', $m2)
        ->assertHasNoErrors();

    expect(MateriaPrerrequisito::where('materia_id', $m1->id)
        ->where('prerrequisito_id', $m2->id)
        ->where('tipo', 'RECOMENDADO')->exists())->toBeTrue();
});

test('rechaza autorreferencia (materia no puede ser prerrequisito de sí misma)', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->propuesta()->create();
    $m = Materia::factory()->for($version)->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalPrerreq', $m)
        ->set('tipoPrerreq', 'OBLIGATORIO')
        ->call('agregarPrerrequisito', $m)
        ->assertHasErrors(['prerreq']);
});

test('rechaza duplicado (mismo par materia-prerrequisito)', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->propuesta()->create();
    $m1 = Materia::factory()->for($version)->create();
    $m2 = Materia::factory()->for($version)->create();

    MateriaPrerrequisito::create([
        'materia_id' => $m1->id,
        'prerrequisito_id' => $m2->id,
        'tipo' => 'OBLIGATORIO',
    ]);

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalPrerreq', $m1)
        ->set('tipoPrerreq', 'OBLIGATORIO')
        ->call('agregarPrerrequisito', $m2)
        ->assertHasErrors(['prerreq']);
});

test('rechaza prerrequisito de distinta versión de plan', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $v1 = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $v2 = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $m1 = Materia::factory()->for($v1)->create();
    $m2 = Materia::factory()->for($v2)->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $v1])
        ->call('abrirModalPrerreq', $m1)
        ->set('tipoPrerreq', 'OBLIGATORIO')
        ->call('agregarPrerrequisito', $m2)
        ->assertHasErrors(['prerreq']);
});

test('rechaza ciclo directo A -> B -> A', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->propuesta()->create();
    $m1 = Materia::factory()->for($version)->delPeriodo(2)->create();
    $m2 = Materia::factory()->for($version)->delPeriodo(1)->create();

    // Agregar A -> B (m1 requiere m2)
    MateriaPrerrequisito::create([
        'materia_id' => $m1->id,
        'prerrequisito_id' => $m2->id,
        'tipo' => 'OBLIGATORIO',
    ]);

    // Intentar agregar B -> A (m2 requiere m1) -> debe fallar
    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalPrerreq', $m2)
        ->set('tipoPrerreq', 'OBLIGATORIO')
        ->call('agregarPrerrequisito', $m1)
        ->assertHasErrors(['prerreq']);
});

test('rechaza ciclo indirecto A -> B -> C -> A', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->propuesta()->create();
    $m1 = Materia::factory()->for($version)->delPeriodo(3)->create();
    $m2 = Materia::factory()->for($version)->delPeriodo(2)->create();
    $m3 = Materia::factory()->for($version)->delPeriodo(1)->create();

    // A -> B
    MateriaPrerrequisito::create([
        'materia_id' => $m1->id,
        'prerrequisito_id' => $m2->id,
        'tipo' => 'OBLIGATORIO',
    ]);
    // B -> C
    MateriaPrerrequisito::create([
        'materia_id' => $m2->id,
        'prerrequisito_id' => $m3->id,
        'tipo' => 'OBLIGATORIO',
    ]);

    // Intentar C -> A
    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalPrerreq', $m3)
        ->set('tipoPrerreq', 'OBLIGATORIO')
        ->call('agregarPrerrequisito', $m1)
        ->assertHasErrors(['prerreq']);
});

test('ADMIN puede quitar prerrequisito', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $m1 = Materia::factory()->for($version)->create();
    $m2 = Materia::factory()->for($version)->create();

    MateriaPrerrequisito::create([
        'materia_id' => $m1->id,
        'prerrequisito_id' => $m2->id,
        'tipo' => 'OBLIGATORIO',
    ]);
    app(MateriaPrerrequisitoService::class)->quitar($m1, $m2);

    expect(MateriaPrerrequisito::where('materia_id', $m1->id)
        ->where('prerrequisito_id', $m2->id)->exists())->toBeFalse();
});

test('ADMISIONES no puede gestionar prerrequisitos (403)', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();

    Livewire::actingAs($admisiones)->test(MateriaAdmin::class)
        ->assertForbidden();
});

test('usuario inactivo no puede gestionar prerrequisitos (403)', function () {
    $inactivo = User::factory()->deRol(RolUsuario::ADMIN)->inactivo()->create();

    Livewire::actingAs($inactivo)->test(MateriaAdmin::class)
        ->assertForbidden();
});

test('tipo debe ser OBLIGATORIO o RECOMENDADO', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $m1 = Materia::factory()->for($version)->create();
    $m2 = Materia::factory()->for($version)->create();

    Livewire::actingAs($admin)->test(MateriaAdmin::class, ['version' => $version])
        ->call('abrirModalPrerreq', $m1)
        ->set('tipoPrerreq', 'INVALIDO')
        ->call('agregarPrerrequisito', $m2)
        ->assertHasErrors(['prerreq']);
});

test('service detecta ciclos correctamente (unit test)', function () {
    $service = app(MateriaPrerrequisitoService::class);
    $version = PlanVersion::factory()->propuesta()->create();
    $m1 = Materia::factory()->for($version)->delPeriodo(3)->create();
    $m2 = Materia::factory()->for($version)->delPeriodo(2)->create();
    $m3 = Materia::factory()->for($version)->delPeriodo(1)->create();

    // A -> B -> C
    MateriaPrerrequisito::create([
        'materia_id' => $m1->id,
        'prerrequisito_id' => $m2->id,
        'tipo' => 'OBLIGATORIO',
    ]);
    MateriaPrerrequisito::create([
        'materia_id' => $m2->id,
        'prerrequisito_id' => $m3->id,
        'tipo' => 'OBLIGATORIO',
    ]);

    // Intentar agregar C -> A (crearía ciclo A -> B -> C -> A)
    expect(fn() => $service->agregar($m3, $m1, TipoPrerrequisito::OBLIGATORIO))
        ->toThrow(\App\Exceptions\CicloPrerrequisitoException::class);
});

test('rechaza tipo inválido en service', function () {
    $version = PlanVersion::factory()->propuesta()->create();
    $m1 = Materia::factory()->for($version)->create();
    $m2 = Materia::factory()->for($version)->create();

    expect(fn() => app(MateriaPrerrequisitoService::class)->agregar($m1, $m2, 'INVALIDO'))
        ->toThrow(\TypeError::class);
});

test('rechaza autorreferencia en service', function () {
    $version = PlanVersion::factory()->propuesta()->create();
    $m = Materia::factory()->for($version)->create();

    expect(fn() => app(MateriaPrerrequisitoService::class)->agregar($m, $m, TipoPrerrequisito::OBLIGATORIO))
        ->toThrow(\App\Exceptions\PrerrequisitoInvalidoException::class);
});

test('rechaza distinta versión en service', function () {
    $plan = PlanEstudio::factory()->create();
    $v1 = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $v2 = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create();
    $m1 = Materia::factory()->for($v1)->create();
    $m2 = Materia::factory()->for($v2)->create();

    expect(fn() => app(MateriaPrerrequisitoService::class)->agregar($m1, $m2, TipoPrerrequisito::OBLIGATORIO))
        ->toThrow(\App\Exceptions\PrerrequisitoInvalidoException::class);
});