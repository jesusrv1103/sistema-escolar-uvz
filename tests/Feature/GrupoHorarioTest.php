<?php

use App\Enums\DiaSemana;
use App\Enums\EstatusGrupo;
use App\Enums\RolUsuario;
use App\Livewire\Academico\GrupoHorarioAdmin;
use App\Models\Aula;
use App\Models\Docente;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Materia;
use App\Models\Periodo;
use App\Models\User;
use Livewire\Livewire;

uses()->group('academico', 'grupo_horarios');

/**
 * Horarios de grupo — CRUD, validaciones, conflictos, autorización.
 */
test('ADMIN puede crear un horario de grupo', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $materia = Materia::factory()->create();
    $periodo = Periodo::factory()->create();
    $docente = Docente::factory()->create();
    $aula = Aula::factory()->create();
    $grupo = Grupo::factory()->creado()->paraMateria($materia)->paraPeriodo($periodo)->paraDocente($docente)->paraAula($aula)->create();

    Livewire::test(GrupoHorarioAdmin::class, ['grupo' => $grupo])
        ->call('abrirModalCrear')
        ->set('dia', 'LUNES')
        ->set('horaInicio', '08:00')
        ->set('horaFin', '10:00')
        ->call('guardar')
        ->assertHasNoErrors();

    $horario = GrupoHorario::where('grupo_id', $grupo->id)->where('dia', 'LUNES')->first();
    expect($horario)->not->toBeNull()
        ->and($horario->dia)->toBe(\App\Enums\DiaSemana::LUNES)
        ->and($horario->hora_inicio->format('H:i'))->toBe('08:00')
        ->and($horario->hora_fin->format('H:i'))->toBe('10:00');
});

test('no se puede crear horario sin grupo', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(GrupoHorarioAdmin::class, ['grupo' => null])
        ->call('abrirModalCrear')
        ->set('dia', 'LUNES')
        ->set('horaInicio', '08:00')
        ->set('horaFin', '10:00')
        ->call('guardar')
        ->assertHasErrors(['grupo']);
});

test('validación de día: solo valores permitidos', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $materia = Materia::factory()->create();
    $periodo = Periodo::factory()->create();
    $docente = Docente::factory()->create();
    $aula = Aula::factory()->create();
    $grupo = Grupo::factory()->creado()->create();

    Livewire::test(GrupoHorarioAdmin::class, ['grupo' => $grupo])
        ->call('abrirModalCrear')
        ->set('dia', 'DOMINGO')
        ->set('horaInicio', '08:00')
        ->set('horaFin', '10:00')
        ->call('guardar')
        ->assertHasErrors(['dia']);
});

test('validación de hora: horaFin debe ser después de horaInicio', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $grupo = Grupo::factory()->creado()->create();

    Livewire::test(GrupoHorarioAdmin::class, ['grupo' => $grupo])
        ->call('abrirModalCrear')
        ->set('dia', 'LUNES')
        ->set('horaInicio', '10:00')
        ->set('horaFin', '08:00')
        ->call('guardar')
        ->assertHasErrors(['horaFin']);
});

test('días válidos: LUNES a SABADO', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $grupo = Grupo::factory()->creado()->create();

    foreach (['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'] as $dia) {
        Livewire::test(GrupoHorarioAdmin::class, ['grupo' => $grupo])
            ->call('abrirModalCrear')
            ->set('dia', $dia)
            ->set('horaInicio', '08:00')
            ->set('horaFin', '10:00')
            ->call('guardar')
            ->assertHasNoErrors();

        expect(GrupoHorario::where('grupo_id', $grupo->id)->where('dia', $dia)->exists())->toBeTrue();
    }
});

test('no se puede crear horario que solapa aula en mismo día/hora', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $aula = Aula::factory()->create();
    $materia = Materia::factory()->create();
    $periodo = Periodo::factory()->create();
    $docente = Docente::factory()->create();

    $grupo1 = Grupo::factory()->creado()->paraAula($aula)->create();
    GrupoHorario::create([
        'grupo_id' => $grupo1->id,
        'dia' => 'LUNES',
        'hora_inicio' => '08:00:00',
        'hora_fin' => '10:00:00',
    ]);

    $grupo2 = Grupo::factory()->creado()->paraAula($aula)->create();

    Livewire::test(GrupoHorarioAdmin::class, ['grupo' => $grupo2])
        ->call('abrirModalCrear')
        ->set('dia', 'LUNES')
        ->set('horaInicio', '09:00')
        ->set('horaFin', '11:00')
        ->call('guardar')
        ->assertHasErrors(['horaInicio']);
});

test('no se puede crear horario que solapa docente en mismo día/hora', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $docente = Docente::factory()->create();
    $materia = Materia::factory()->create();
    $periodo = Periodo::factory()->create();

    $grupo1 = Grupo::factory()->creado()->paraDocente($docente)->create();
    GrupoHorario::create([
        'grupo_id' => $grupo1->id,
        'dia' => 'LUNES',
        'hora_inicio' => '08:00:00',
        'hora_fin' => '10:00:00',
    ]);

    $grupo2 = Grupo::factory()->creado()->paraDocente($docente)->create();

    Livewire::test(GrupoHorarioAdmin::class, ['grupo' => $grupo2])
        ->call('abrirModalCrear')
        ->set('dia', 'LUNES')
        ->set('horaInicio', '09:00')
        ->set('horaFin', '11:00')
        ->call('guardar')
        ->assertHasErrors(['horaInicio']);
});

test('horarios en distinta aula y docente pueden solaparse', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $aula1 = Aula::factory()->create();
    $aula2 = Aula::factory()->create();
    $docente1 = Docente::factory()->create();
    $docente2 = Docente::factory()->create();
    $materia = Materia::factory()->create();
    $periodo = Periodo::factory()->create();

    $grupo1 = Grupo::factory()->creado()->paraAula($aula1)->paraDocente($docente1)->create();
    GrupoHorario::create([
        'grupo_id' => $grupo1->id,
        'dia' => 'LUNES',
        'hora_inicio' => '08:00:00',
        'hora_fin' => '10:00:00',
    ]);

    $grupo2 = Grupo::factory()->creado()->paraAula($aula2)->paraDocente($docente2)->create();

    Livewire::test(GrupoHorarioAdmin::class, ['grupo' => $grupo2])
        ->call('abrirModalCrear')
        ->set('dia', 'LUNES')
        ->set('horaInicio', '09:00')
        ->set('horaFin', '11:00')
        ->call('guardar')
        ->assertHasNoErrors();
});

test('ADMIN puede editar un horario de grupo', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $grupo = Grupo::factory()->creado()->create();
    $horario = GrupoHorario::create([
        'grupo_id' => $grupo->id,
        'dia' => 'LUNES',
        'hora_inicio' => '08:00:00',
        'hora_fin' => '10:00:00',
    ]);

    Livewire::test(GrupoHorarioAdmin::class, ['grupo' => $grupo])
        ->call('abrirModalEditar', $horario)
        ->set('dia', 'MARTES')
        ->set('horaInicio', '10:00')
        ->set('horaFin', '12:00')
        ->call('guardar')
        ->assertHasNoErrors();

    $horario = $horario->fresh();
    expect($horario->dia)->toBe(\App\Enums\DiaSemana::MARTES)
        ->and($horario->hora_inicio->format('H:i'))->toBe('10:00')
        ->and($horario->hora_fin->format('H:i'))->toBe('12:00');
});

test('no se puede editar horario creando conflicto de aula', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $aula = Aula::factory()->create();
    $grupo = Grupo::factory()->creado()->paraAula($aula)->create();

    GrupoHorario::create([
        'grupo_id' => $grupo->id,
        'dia' => 'LUNES',
        'hora_inicio' => '08:00:00',
        'hora_fin' => '10:00:00',
    ]);

    $horario2 = GrupoHorario::create([
        'grupo_id' => $grupo->id,
        'dia' => 'MARTES',
        'hora_inicio' => '08:00:00',
        'hora_fin' => '10:00:00',
    ]);

    Livewire::test(GrupoHorarioAdmin::class, ['grupo' => $grupo])
        ->call('abrirModalEditar', $horario2)
        ->set('dia', 'LUNES')
        ->set('horaInicio', '09:00')
        ->set('horaFin', '11:00')
        ->call('guardar')
        ->assertHasErrors(['horaInicio']);
});

test('ADMIN puede eliminar un horario', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $grupo = Grupo::factory()->creado()->create();
    $horario = GrupoHorario::create([
        'grupo_id' => $grupo->id,
        'dia' => 'LUNES',
        'hora_inicio' => '08:00:00',
        'hora_fin' => '10:00:00',
    ]);
    $horarioId = $horario->id;

    Livewire::test(GrupoHorarioAdmin::class, ['grupo' => $grupo])
        ->call('eliminar', $horario)
        ->assertHasNoErrors();

    expect(GrupoHorario::find($horarioId))->toBeNull();
});

test('ADMISIONES no puede acceder a horarios (403)', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());

    Livewire::test(GrupoHorarioAdmin::class)
        ->assertForbidden();
});

test('ADMINISTRACION no puede acceder a horarios (403)', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMINISTRACION)->create());

    Livewire::test(GrupoHorarioAdmin::class)
        ->assertForbidden();
});

test('CONTROL_ESCOLAR no puede acceder a horarios (403)', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());

    Livewire::test(GrupoHorarioAdmin::class)
        ->assertForbidden();
});

test('usuario inactivo no puede acceder a horarios (403)', function () {
    $inactivo = User::factory()->deRol(RolUsuario::ADMIN)->inactivo()->create();

    $this->actingAs($inactivo);

    Livewire::test(GrupoHorarioAdmin::class)
        ->assertForbidden();
});

test('invitado es redirigido a login', function () {
    $this->get(route('admin.grupos.horarios', ['grupo' => 1]))
        ->assertRedirect(route('login'));
});