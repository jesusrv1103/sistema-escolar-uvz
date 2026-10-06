<?php

use App\Enums\RolUsuario;
use App\Livewire\Academico\CalificacionAdmin;
use App\Models\Calificacion;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\InscripcionMateria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class)->group('calificaciones');

/*
|--------------------------------------------------------------------------
| Captura de calificaciones (WAVE UI/UX 2.0-12 — GATE FINAL)
|--------------------------------------------------------------------------
|
| La página de Calificaciones tenía tres defectos preexistentes que la dejaban
| sin uso:
|
|  * No existía forma de elegir grupo: `mount(?Grupo)` sólo podía recibir un
|    parámetro de ruta que nunca se declaró, así que la matriz nunca se abría.
|  * `render()` filtraba `evaluaciones.estatus`, columna eliminada en
|    2026_10_04 (remove_estatus_from_evaluaciones_table) → SQLSTATE 42S22.
|  * `guardarCalificacion()` leía `$this->dispatch->get(...)`: `dispatch` es un
|    método, no una propiedad, y el botón de la fila pasaba siempre la primera
|    evaluación; el modal ni siquiera se abría (`modalCalificacionAbierto`
|    jamás se ponía en true) y un hidden `wire:model="dispatch"` apuntaba a una
|    propiedad inexistente.
|
*/

test('sin grupo seleccionado la página ofrece el listado de grupos', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $grupo = Grupo::factory()->create();

    Livewire::actingAs($usuario)
        ->test(CalificacionAdmin::class, ['grupo' => null])
        ->assertSee('Seleccionar grupo')
        ->assertSee($grupo->clave)
        ->assertSee('Sin grupo seleccionado');
});

test('seleccionar un grupo abre la matriz de calificaciones', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $grupo = Grupo::factory()->abierto()->create();
    $evaluacion = Evaluacion::factory()->create([
        'grupo_id' => $grupo->getKey(),
        'ponderacion' => 30,
    ]);

    Livewire::actingAs($usuario)
        ->test(CalificacionAdmin::class, ['grupo' => $grupo])
        ->assertSee('Matriz de calificaciones')
        ->assertSee($grupo->clave)
        ->assertSee($evaluacion->nombre);
});

test('capturar una calificación desde el modal la registra', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $inscripcion = InscripcionMateria::factory()->cursando()->create();
    $evaluacion = Evaluacion::factory()->create(['grupo_id' => $inscripcion->grupo_id]);

    Livewire::actingAs($usuario)
        ->test(CalificacionAdmin::class, ['grupo' => Grupo::find($inscripcion->grupo_id)])
        ->call('abrirModalCapturar', $inscripcion->getKey(), $evaluacion->getKey())
        ->assertSet('modalCalificacionAbierto', true)
        ->assertSet('inscripcionCaptura', fn ($i) => $i?->is($inscripcion))
        ->assertSet('evaluacionCaptura', fn ($e) => $e?->is($evaluacion))
        ->set('valor', 85.5)
        ->set('observacion', 'Buena participación')
        ->call('guardarCalificacion')
        ->assertHasNoErrors();

    expect(Calificacion::query()
        ->where('inscripcion_materia_id', $inscripcion->getKey())
        ->where('evaluacion_id', $evaluacion->getKey())
        ->exists())->toBeTrue();
});

test('el modal de captura abre en modo edición con la calificación existente', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $inscripcion = InscripcionMateria::factory()->cursando()->create();
    $evaluacion = Evaluacion::factory()->create(['grupo_id' => $inscripcion->grupo_id]);

    $calificacion = Calificacion::create([
        'inscripcion_materia_id' => $inscripcion->getKey(),
        'evaluacion_id' => $evaluacion->getKey(),
        'valor' => 70,
        'capturada_por' => $usuario->getKey(),
        'capturada_at' => now(),
    ]);

    Livewire::actingAs($usuario)
        ->test(CalificacionAdmin::class, ['grupo' => Grupo::find($inscripcion->grupo_id)])
        ->call('abrirModalCapturar', $inscripcion->getKey(), $evaluacion->getKey())
        ->assertSet('modalCalificacionAbierto', true)
        ->assertSet('valor', fn ($v) => (float) $v === 70.0)
        ->assertSet('calificacionEnEdicion', fn ($c) => $c?->is($calificacion));
});

test('cerrar el modal limpia la captura en curso', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $inscripcion = InscripcionMateria::factory()->cursando()->create();
    $evaluacion = Evaluacion::factory()->create(['grupo_id' => $inscripcion->grupo_id]);

    Livewire::actingAs($usuario)
        ->test(CalificacionAdmin::class, ['grupo' => Grupo::find($inscripcion->grupo_id)])
        ->call('abrirModalCapturar', $inscripcion->getKey(), $evaluacion->getKey())
        ->call('cerrarModalCalificacion')
        ->assertSet('modalCalificacionAbierto', false)
        ->assertSet('inscripcionCaptura', null)
        ->assertSet('evaluacionCaptura', null);
});
