<?php

use App\Enums\EstatusPracticaProfesional;
use App\Enums\EstatusServicioSocial;
use App\Enums\RolUsuario;
use App\Livewire\Academico\PracticasProfesionalesIndex;
use App\Livewire\Academico\ServicioSocialIndex;
use App\Models\Alumno;
use App\Models\Materia;
use App\Models\MatriculaPeriodo;
use App\Models\Periodo;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\Programa;
use App\Models\PracticaProfesional;
use App\Models\ServicioSocial;
use App\Models\User;
use App\Services\HistorialAcademicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class)->group('servicio-social', 'practicas');

/**
 * Matriz §8.2 de las capacidades nuevas de 2.0-13.
 */
dataset('capacidades_2_0_13', [
    ['servicio_social.ver', RolUsuario::ADMIN->value, true],
    ['servicio_social.ver', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['servicio_social.ver', RolUsuario::ADMINISTRACION->value, true],
    ['servicio_social.ver', RolUsuario::ADMISIONES->value, false],
    ['servicio_social.ver', RolUsuario::DOCENTE->value, false],

    ['servicio_social.crear', RolUsuario::ADMIN->value, true],
    ['servicio_social.crear', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['servicio_social.crear', RolUsuario::ADMINISTRACION->value, false],
    ['servicio_social.crear', RolUsuario::ADMISIONES->value, false],
    ['servicio_social.crear', RolUsuario::DOCENTE->value, false],

    ['servicio_social.actualizar', RolUsuario::ADMIN->value, true],
    ['servicio_social.actualizar', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['servicio_social.actualizar', RolUsuario::ADMINISTRACION->value, false],

    ['servicio_social.validar', RolUsuario::ADMIN->value, true],
    ['servicio_social.validar', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['servicio_social.validar', RolUsuario::ADMINISTRACION->value, false],
    ['servicio_social.validar', RolUsuario::ADMISIONES->value, false],

    ['practica.ver', RolUsuario::ADMIN->value, true],
    ['practica.ver', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['practica.ver', RolUsuario::ADMINISTRACION->value, true],
    ['practica.ver', RolUsuario::ADMISIONES->value, false],
    ['practica.ver', RolUsuario::DOCENTE->value, false],

    ['practica.crear', RolUsuario::ADMIN->value, true],
    ['practica.crear', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['practica.crear', RolUsuario::ADMINISTRACION->value, false],

    ['practica.actualizar', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['practica.actualizar', RolUsuario::ADMINISTRACION->value, false],
    ['practica.validar', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['practica.validar', RolUsuario::ADMINISTRACION->value, false],
]);

test('capacidad 2.0-13: {capacidad} para {rol} → {esperado}', function (string $capacidad, string $rol, bool $esperado) {
    $usuario = User::factory()->deRol(RolUsuario::from($rol))->create();
    expect(Gate::forUser($usuario)->allows($capacidad))->toBe($esperado);
})->with('capacidades_2_0_13');

test('las pantallas de servicio social y prácticas responden según rol', function (string $rol, int $codigo) {
    $this->actingAs(User::factory()->deRol(RolUsuario::from($rol))->create());

    $this->get('/servicio-social')->assertStatus($codigo);
    $this->get('/practicas-profesionales')->assertStatus($codigo);
})->with([
    [RolUsuario::ADMIN->value, 200],
    [RolUsuario::CONTROL_ESCOLAR->value, 200],
    [RolUsuario::ADMINISTRACION->value, 200], // sólo lectura
    [RolUsuario::ADMISIONES->value, 403],
    [RolUsuario::DOCENTE->value, 403],
]);

test('control escolar crea un registro de servicio social en EN_PROCESO con avance 0', function () {
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $alumno = Alumno::factory()->create();

    $this->actingAs($control);

    Livewire::test(ServicioSocialIndex::class)
        ->call('abrirModalCrear')
        ->call('seleccionarAlumno', (string) $alumno->id)
        ->set('actividad', 'Apoyo en archivo')
        ->set('dependencia', 'Servicios Escolares')
        ->set('fecha_inicio', '2026-02-02')
        ->set('fecha_fin', '2026-06-30')
        ->call('guardar')
        ->assertHasNoErrors();

    $servicio = ServicioSocial::sole();

    expect($servicio->alumno_id)->toBe($alumno->id)
        ->and($servicio->estatus)->toBe(EstatusServicioSocial::EN_PROCESO)
        ->and($servicio->avance)->toBe(0)
        ->and($servicio->validado_at)->toBeNull()
        ->and($servicio->esValidado())->toBeFalse();
});

test('no se pueden crear dos registros de servicio social para el mismo alumno', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());
    $alumno = Alumno::factory()->create();
    ServicioSocial::factory()->create(['alumno_id' => $alumno->id]);

    Livewire::test(ServicioSocialIndex::class)
        ->call('abrirModalCrear')
        ->call('seleccionarAlumno', (string) $alumno->id)
        ->set('actividad', 'Otra actividad')
        ->set('dependencia', 'Otra dependencia')
        ->call('guardar')
        ->assertHasErrors(['guardar']);

    expect(ServicioSocial::count())->toBe(1);
});

test('el avance 0–100 se almacena pero nunca auto-valida el servicio social', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());
    $servicio = ServicioSocial::factory()->create(['avance' => 0]);

    Livewire::test(ServicioSocialIndex::class)
        ->call('abrirModalEditar', $servicio->id)
        ->set('avance', 100)
        ->call('guardar')
        ->assertHasNoErrors();

    $servicio->refresh();

    expect($servicio->avance)->toBe(100)
        ->and($servicio->estatus)->toBe(EstatusServicioSocial::EN_PROCESO)
        ->and($servicio->esValidado())->toBeFalse();
});

test('un avance fuera de rango se rechaza', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());
    $servicio = ServicioSocial::factory()->create();

    expect(fn () => app(\App\Services\ServicioSocialService::class)->registrarAvance($servicio, 101))
        ->toThrow(\App\Exceptions\ServicioSocialInvalidaException::class);
});

test('sólo una validación explícita lleva el servicio social a VALIDADO', function () {
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $this->actingAs($control);
    $servicio = ServicioSocial::factory()->create(['avance' => 100]);

    Livewire::test(ServicioSocialIndex::class)
        ->call('validarRegistro', $servicio->id)
        ->assertHasNoErrors();

    $servicio->refresh();

    expect($servicio->estatus)->toBe(EstatusServicioSocial::VALIDADO)
        ->and($servicio->validado_por)->toBe($control->id)
        ->and($servicio->validado_at)->not->toBeNull();

    // Segunda validación: rechazada (idempotencia controlada, no silenciosa).
    Livewire::test(ServicioSocialIndex::class)
        ->call('validarRegistro', $servicio->id)
        ->assertHasErrors(['validar']);
});

test('una vez validado el servicio social es inmutable', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());
    $servicio = ServicioSocial::factory()->validado()->create();

    $service = app(\App\Services\ServicioSocialService::class);

    expect(fn () => $service->actualizar($servicio, [
        'actividad' => 'Actividad cambiada',
        'dependencia' => 'Dependencia cambiada',
    ]))->toThrow(\App\Exceptions\ServicioSocialInvalidaException::class)
        ->and(fn () => $service->registrarAvance($servicio, 10))->toThrow(\App\Exceptions\ServicioSocialInvalidaException::class);

    $servicio->refresh();

    expect($servicio->avance)->toBe(100)
        ->and($servicio->estatus)->toBe(EstatusServicioSocial::VALIDADO);
});

test('administración sólo consulta: no puede crear ni editar servicio social', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMINISTRACION)->create());
    $servicio = ServicioSocial::factory()->create();

    Livewire::test(ServicioSocialIndex::class)
        ->call('abrirModalCrear')
        ->assertForbidden();

    Livewire::test(ServicioSocialIndex::class)
        ->call('abrirModalEditar', $servicio->id)
        ->assertForbidden();
});

test('el servicio social creado y validado deja auditoría append-only', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());
    $servicio = ServicioSocial::factory()->create();

    app(\App\Services\ServicioSocialService::class)->validar($servicio);

    $acciones = $servicio->auditorias()->pluck('accion');

    expect($acciones)->toContain('servicio_social_validado')
        ->and($servicio->auditorias()->count())->toBeGreaterThan(0);
});

test('la pantalla muestra el avance académico del alumno en sólo lectura', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());

    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $plan = PlanEstudio::factory()->paraPrograma($programa)->create();
    $version = PlanVersion::factory()->forPlanEstudio($plan)->vigente()->create();

    // 2 materias de 4 créditos = 8 créditos en el plan
    $materias = Materia::factory()->count(2)->paraPlanVersion($version)->create(['activo' => true, 'creditos' => 4]);

    MatriculaPeriodo::factory()->activa()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)
        ->create(['plan_version_id' => $version->id]);

    // Una materia aprobada: 4 de 8 créditos = 50% de avance académico
    $grupo = \App\Models\Grupo::factory()->paraMateria($materias->first())->paraPeriodo($periodo)->abierto()
        ->create(['activo' => true, 'cupo' => 40]);
    $matricula = MatriculaPeriodo::where('alumno_id', $alumno->id)->first();
    $inscripcion = \App\Models\InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo)->cursando()->create();
    $inscripcion->update(['calificacion_final' => 80.00, 'estatus' => \App\Enums\EstatusInscripcionMateria::APROBADA]);

    ServicioSocial::factory()->create(['alumno_id' => $alumno->id]);

    Livewire::test(ServicioSocialIndex::class)
        ->assertSee('50.0%')
        ->assertSee($alumno->nombre_completo);
});

test('control escolar registra y valida prácticas con calificación como texto libre', function () {
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $this->actingAs($control);
    $alumno = Alumno::factory()->create();

    Livewire::test(PracticasProfesionalesIndex::class)
        ->call('abrirModalCrear')
        ->call('seleccionarAlumno', (string) $alumno->id)
        ->set('empresa', 'Empresa Demo S.A.')
        ->set('fecha_inicio', '2026-03-01')
        ->set('fecha_fin', '2026-08-01')
        ->set('asesor', 'Ing. Demo')
        ->set('calificacion', 'Satisfactoria (documentada por la institución)')
        ->call('guardar')
        ->assertHasNoErrors();

    $practica = PracticaProfesional::sole();

    expect($practica->estatus)->toBe(EstatusPracticaProfesional::EN_PROCESO)
        ->and($practica->calificacion)->toBe('Satisfactoria (documentada por la institución)');

    Livewire::test(PracticasProfesionalesIndex::class)
        ->call('validarRegistro', $practica->id)
        ->assertHasNoErrors();

    $practica->refresh();

    expect($practica->estatus)->toBe(EstatusPracticaProfesional::VALIDADO)
        ->and($practica->validado_por)->toBe($control->id);

    // Inmutable después de validar
    expect(fn () => app(\App\Services\PracticaProfesionalService::class)->actualizar($practica, [
        'empresa' => 'Otra empresa',
    ]))->toThrow(\App\Exceptions\PracticaInvalidaException::class);

    // Un solo registro por alumno
    expect(fn () => app(\App\Services\PracticaProfesionalService::class)->crear($alumno, [
        'empresa' => 'Segunda empresa',
    ]))->toThrow(\App\Exceptions\PracticaInvalidaException::class);
});

test('las fechas de prácticas no pueden invertirse', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());
    $alumno = Alumno::factory()->create();

    Livewire::test(PracticasProfesionalesIndex::class)
        ->call('abrirModalCrear')
        ->call('seleccionarAlumno', (string) $alumno->id)
        ->set('empresa', 'Empresa Demo')
        ->set('fecha_inicio', '2026-05-01')
        ->set('fecha_fin', '2026-04-01')
        ->call('guardar')
        ->assertHasErrors(['fecha_fin']);

    expect(PracticaProfesional::count())->toBe(0);
});

test('admisiones y docente no alcanzan las acciones de escritura de prácticas', function () {
    foreach ([RolUsuario::ADMISIONES, RolUsuario::DOCENTE] as $rol) {
        $this->actingAs(User::factory()->deRol($rol)->create());

        Livewire::test(PracticasProfesionalesIndex::class)
            ->assertForbidden();
    }
});

test('el avance académico se lee del servicio canónico y usa el alumno indicado', function () {
    // Regresión: creditosTotalesPlan() usaba auth()->user()->id como alumno_id,
    // así que con sesión iniciada devolvía los créditos de otro expediente.
    $usuario = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $this->actingAs($usuario);

    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $plan = PlanEstudio::factory()->paraPrograma($programa)->create();
    $version = PlanVersion::factory()->forPlanEstudio($plan)->vigente()->create();
    Materia::factory()->count(2)->paraPlanVersion($version)->create(['activo' => true, 'creditos' => 4]);

    MatriculaPeriodo::factory()->activa()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)
        ->create(['plan_version_id' => $version->id]);

    expect($usuario->id)->not->toBe($alumno->id)
        ->and(app(HistorialAcademicoService::class)->creditosTotalesPlan($alumno))->toBe(8);
});
