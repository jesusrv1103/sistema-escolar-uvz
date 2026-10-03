<?php

use App\Enums\RolUsuario;
use App\Livewire\Catalogos\Docentes;
use App\Models\Docente;
use App\Models\User;
use Livewire\Livewire;

uses()->group('catalogos', 'docentes');


/**
 * Docentes — CRUD, validaciones, autorización.
 */
test('ADMIN puede crear un docente', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(Docentes::class)
        ->call('crear')
        ->set('clave', 'DOC-001')
        ->set('nombre', 'Juan')
        ->set('apellidos', 'Perez Lopez')
        ->set('curp', 'PERL850101HDFRZN09')
        ->set('rfc', 'PERJ850101XXX')
        ->set('correo', 'juan.perez@uvz.edu.mx')
        ->set('telefono', '4921234567')
        ->set('especialidad', 'Matematicas')
        ->set('grado_estudios', 'Maestria')
        ->set('cedula_profesional', '1234567')
        ->set('activo', true)
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Docente::where('clave', 'DOC-001')->exists())->toBeTrue();
});

test('ADMIN no puede crear docente con clave duplicada', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    Docente::factory()->create(['clave' => 'DOC-001']);

    Livewire::test(Docentes::class)
        ->call('crear')
        ->set('clave', 'DOC-001')
        ->set('nombre', 'Otro')
        ->set('apellidos', 'Docente')
        ->set('curp', 'DOCE900101HDFRZN08')
        ->call('guardar')
        ->assertHasErrors(['clave']);

    expect(Docente::where('clave', 'DOC-001')->count())->toBe(1);
});

test('ADMIN no puede crear docente con CURP duplicada', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    Docente::factory()->create(['curp' => 'PERL850101HDFRZN09']);

    Livewire::test(Docentes::class)
        ->call('crear')
        ->set('clave', 'DOC-002')
        ->set('nombre', 'Otro')
        ->set('apellidos', 'Docente')
        ->set('curp', 'PERL850101HDFRZN09')
        ->call('guardar')
        ->assertHasErrors(['curp']);
});

test('ADMIN no puede crear docente con RFC duplicada', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    Docente::factory()->create(['rfc' => 'PERJ850101XXX']);

    Livewire::test(Docentes::class)
        ->call('crear')
        ->set('clave', 'DOC-002')
        ->set('nombre', 'Otro')
        ->set('apellidos', 'Docente')
        ->set('curp', 'PERL850101HDFRZN09')
        ->set('rfc', 'PERJ850101XXX')
        ->call('guardar')
        ->assertHasErrors(['rfc']);
});

test('ADMIN puede editar un docente', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $docente = Docente::factory()->create(['nombre' => 'Original']);

    Livewire::test(Docentes::class)
        ->call('editar', $docente->id)
        ->set('clave', $docente->clave)
        ->set('nombre', 'Actualizado')
        ->set('apellidos', $docente->apellidos)
        ->set('curp', $docente->curp)
        ->set('activo', true)
        ->call('guardar')
        ->assertHasNoErrors();

    expect($docente->fresh()->nombre)->toBe('Actualizado');
});

test('ADMIN puede alternar activo de un docente', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $docente = Docente::factory()->create(['activo' => true]);

    Livewire::test(Docentes::class)
        ->call('alternarActivo', $docente->id)
        ->assertHasNoErrors();

    expect($docente->fresh()->activo)->toBeFalse();

    Livewire::test(Docentes::class)
        ->call('alternarActivo', $docente->id)
        ->assertHasNoErrors();

    expect($docente->fresh()->activo)->toBeTrue();
});

test('ADMIN puede eliminar un docente (soft delete)', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $docente = Docente::factory()->create();

    Livewire::test(Docentes::class)
        ->call('eliminar', $docente->id)
        ->assertHasNoErrors();

    expect(Docente::withTrashed()->find($docente->id))->not->toBeNull()
        ->and(Docente::find($docente->id))->toBeNull();
});

test('ADMISIONES no puede acceder a docentes (403)', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());

    $component = Livewire::test(Docentes::class);
    $component->call('crear');
    expect(estadoDePeticion($component))->toBe(403);
});

test('ADMINISTRACION no puede acceder a docentes (403)', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMINISTRACION)->create());

    $component = Livewire::test(Docentes::class);
    $component->call('crear');
    expect(estadoDePeticion($component))->toBe(403);
});

test('CONTROL_ESCOLAR no puede acceder a docentes (403)', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());

    $component = Livewire::test(Docentes::class);
    $component->call('crear');
    expect(estadoDePeticion($component))->toBe(403);
});

test('usuario inactivo no puede acceder a docentes (403)', function () {
    $inactivo = User::factory()->deRol(RolUsuario::ADMIN)->inactivo()->create();

    $this->actingAs($inactivo);

    $component = Livewire::test(Docentes::class);
    $component->call('crear');
    expect(estadoDePeticion($component))->toBe(403);
});

test('invitado es redirigido a login', function () {
    $this->get(route('admin.docentes'))
        ->assertRedirect(route('login'));
});

test('validación de clave: solo A-Z, 0-9, guión y guion bajo', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(Docentes::class)
        ->call('crear')
        ->set('clave', 'clave-invalida!')
        ->set('nombre', 'Test')
        ->set('apellidos', 'Docente')
        ->set('curp', 'PERL850101HDFRZN09')
        ->set('activo', true)
        ->call('guardar')
        ->assertHasErrors(['clave']);
});

test('validación de CURP: formato y longitud', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(Docentes::class)
        ->call('crear')
        ->set('clave', 'DOC-001')
        ->set('nombre', 'Test')
        ->set('apellidos', 'Docente')
        ->set('curp', 'curp-invalida')
        ->set('activo', true)
        ->call('guardar')
        ->assertHasErrors(['curp']);
});

test('validación de RFC: formato', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(Docentes::class)
        ->call('crear')
        ->set('clave', 'DOC-001')
        ->set('nombre', 'Test')
        ->set('apellidos', 'Docente')
        ->set('curp', 'PERL850101HDFRZN09')
        ->set('rfc', 'rfc-invalido')
        ->set('activo', true)
        ->call('guardar')
        ->assertHasErrors(['rfc']);
});