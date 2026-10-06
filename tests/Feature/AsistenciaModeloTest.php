<?php

use App\Enums\RolUsuario;
use App\Livewire\Academico\AsistenciaAdmin;
use App\Models\Asistencia;
use App\Models\Concerns\HasAudit;
use App\Models\InscripcionMateria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class)->group('academico', 'asistencia');

/*
|--------------------------------------------------------------------------
| Regresión WAVE UI/UX 2.0-12 — modelo Asistencia
|--------------------------------------------------------------------------
|
| `Asistencia` importaba `App\Concerns\HasAudit` (namespace inexistente) y
| declaraba el trait en la clase: toda carga de la clase era un Error fatal,
| con lo que `/admin/asistencia` devolvía 500. El trait real vive en
| `App\Models\Concerns\HasAudit`.
|
*/

test('la clase Asistencia se puede resolver (trait de auditoria valido)', function () {
    expect(class_exists(Asistencia::class))->toBeTrue();
});

test('Asistencia usa el trait HasAudit del namespace correcto', function () {
    $traits = array_map(
        static fn (string $fqcn): string => ltrim($fqcn, '\\'),
        (new ReflectionClass(Asistencia::class))->getTraitNames(),
    );

    expect($traits)->toContain(HasAudit::class);
});

/*
|--------------------------------------------------------------------------
| Regresión WAVE UI/UX 2.0-12 — carga de la lista
|--------------------------------------------------------------------------
|
| `cargarLista()` ordenaba por `matriculaPeriodo.alumno.apellidos`: no es una
| columna, y una relación tampoco puede ordenar la consulta. La primera carga
| de asistencia devolvía SQLSTATE 42S22 (500 en el navegador) y la lista nunca
| llegaba a pintarse.
|
*/

test('cargar la lista del grupo devuelve a los alumnos sin romper en SQL', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $inscripcion = InscripcionMateria::factory()->cursando()->create();

    Livewire::actingAs($admin)
        ->test(AsistenciaAdmin::class)
        ->call('seleccionarGrupo', (string) $inscripcion->grupo_id)
        ->call('cargarLista')
        ->assertHasNoErrors()
        ->assertSet('asistencias', fn (array $asistencias) => count($asistencias) === 1)
        ->assertSee($inscripcion->matriculaPeriodo->alumno->nombre_completo);
});
