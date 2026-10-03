<?php

use App\Enums\RolUsuario;
use App\Livewire\Catalogos\Aulas;
use App\Models\Aula;
use App\Models\User;
use Livewire\Livewire;

uses()->group('catalogos', 'aulas');

/**
 * Aulas — CRUD, validaciones, autorización.
 */
test('ADMIN puede crear un aula', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(Aulas::class)
        ->call('crear')
        ->set('clave', 'A-101')
        ->set('nombre', 'Aula 101')
        ->set('edificio', 'Edificio A')
        ->set('capacidad', 40)
        ->set('tipo', 'TEORICA')
        ->set('activo', true)
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Aula::where('clave', 'A-101')->exists())->toBeTrue();
});

test('ADMIN no puede crear aula con clave duplicada', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    Aula::factory()->create(['clave' => 'A-101']);

    Livewire::test(Aulas::class)
        ->call('crear')
        ->set('clave', 'A-101')
        ->set('nombre', 'Otra Aula')
        ->set('edificio', 'Edificio B')
        ->set('capacidad', 30)
        ->set('tipo', 'TEORICA')
        ->set('activo', true)
        ->call('guardar')
        ->assertHasErrors(['clave']);

    expect(Aula::where('clave', 'A-101')->count())->toBe(1);
});

test('ADMIN puede editar un aula', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $aula = Aula::factory()->create(['nombre' => 'Original']);

    Livewire::test(Aulas::class)
        ->call('editar', $aula->id)
        ->set('clave', $aula->clave)
        ->set('nombre', 'Actualizada')
        ->set('edificio', $aula->edificio)
        ->set('capacidad', $aula->capacidad)
        ->set('tipo', $aula->tipo)
        ->set('activo', true)
        ->call('guardar')
        ->assertHasNoErrors();

    expect($aula->fresh()->nombre)->toBe('Actualizada');
});

test('ADMIN puede alternar activo de un aula', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $aula = Aula::factory()->create(['activo' => true]);

    Livewire::test(Aulas::class)
        ->call('alternarActivo', $aula->id)
        ->assertHasNoErrors();

    expect($aula->fresh()->activo)->toBeFalse();

    Livewire::test(Aulas::class)
        ->call('alternarActivo', $aula->id)
        ->assertHasNoErrors();

    expect($aula->fresh()->activo)->toBeTrue();
});

test('ADMIN puede eliminar un aula (soft delete)', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $aula = Aula::factory()->create();

    Livewire::test(Aulas::class)
        ->call('eliminar', $aula->id)
        ->assertHasNoErrors();

    expect(Aula::withTrashed()->find($aula->id))->not->toBeNull()
        ->and(Aula::find($aula->id))->toBeNull();
});

test('ADMISIONES no puede acceder a aulas (403)', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());

    $component = Livewire::test(Aulas::class);
    $component->call('crear');
    expect(estadoDePeticion($component))->toBe(403);
});

test('ADMINISTRACION no puede acceder a aulas (403)', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMINISTRACION)->create());

    $component = Livewire::test(Aulas::class);
    $component->call('crear');
    expect(estadoDePeticion($component))->toBe(403);
});

test('CONTROL_ESCOLAR no puede acceder a aulas (403)', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());

    $component = Livewire::test(Aulas::class);
    $component->call('crear');
    expect(estadoDePeticion($component))->toBe(403);
});

test('usuario inactivo no puede acceder a aulas (403)', function () {
    $inactivo = User::factory()->deRol(RolUsuario::ADMIN)->inactivo()->create();

    $this->actingAs($inactivo);

    $component = Livewire::test(Aulas::class);
    $component->call('crear');
    expect(estadoDePeticion($component))->toBe(403);
});

test('invitado es redirigido a login', function () {
    $this->get(route('admin.aulas'))
        ->assertRedirect(route('login'));
});

test('validación de clave: solo A-Z, 0-9, guión y guion bajo', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(Aulas::class)
        ->call('crear')
        ->set('clave', 'clave-invalida!')
        ->set('nombre', 'Test')
        ->set('edificio', 'Edificio A')
        ->set('capacidad', 30)
        ->set('tipo', 'TEORICA')
        ->set('activo', true)
        ->call('guardar')
        ->assertHasErrors(['clave']);
});

test('validación de capacidad: mínimo 1, máximo 500', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(Aulas::class)
        ->call('crear')
        ->set('clave', 'A-101')
        ->set('nombre', 'Test')
        ->set('edificio', 'Edificio A')
        ->set('capacidad', 0)
        ->set('tipo', 'TEORICA')
        ->set('activo', true)
        ->call('guardar')
        ->assertHasErrors(['capacidad']);

    Livewire::test(Aulas::class)
        ->call('crear')
        ->set('clave', 'A-102')
        ->set('nombre', 'Test')
        ->set('edificio', 'Edificio A')
        ->set('capacidad', 501)
        ->set('tipo', 'TEORICA')
        ->set('activo', true)
        ->call('guardar')
        ->assertHasErrors(['capacidad']);
});

test('validación de tipo: solo valores permitidos', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(Aulas::class)
        ->call('crear')
        ->set('clave', 'A-101')
        ->set('nombre', 'Test')
        ->set('edificio', 'Edificio A')
        ->set('capacidad', 30)
        ->set('tipo', 'INVALIDO')
        ->set('activo', true)
        ->call('guardar')
        ->assertHasErrors(['tipo']);
});

test('tipos de aula válidos: TEORICA, PRACTICA, MIXTA, LABORATORIO', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    foreach (['TEORICA', 'PRACTICA', 'MIXTA', 'LABORATORIO'] as $tipo) {
        Livewire::test(Aulas::class)
            ->call('crear')
            ->set('clave', "A-$tipo")
            ->set('nombre', "Aula $tipo")
            ->set('edificio', 'Edificio A')
            ->set('capacidad', 30)
            ->set('tipo', $tipo)
            ->set('activo', true)
            ->call('guardar')
            ->assertHasNoErrors();

        expect(Aula::where('clave', "A-$tipo")->exists())->toBeTrue();
    }
});