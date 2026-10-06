<?php

use App\Enums\RolUsuario;
use App\Livewire\Academico\AsistenciaAdmin;
use App\Models\Asistencia;
use App\Models\Alumno;
use App\Models\Grupo;
use App\Models\InscripcionMateria;
use App\Models\Materia;
use App\Models\MatriculaPeriodo;
use App\Models\Periodo;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class)->group('asistencia', 'repro');

test('repro: guardar asistencia persiste el estado marcado de cada alumno', function () {
    $periodo = Periodo::factory()->create([
        'activo' => true,
        'fecha_inicio' => now()->subMonth(),
        'fecha_fin' => now()->addMonth(),
    ]);
    $programa = Programa::factory()->create(['activo' => true]);
    $plan = PlanEstudio::factory()->paraPrograma($programa)->create();
    $version = PlanVersion::factory()->forPlanEstudio($plan)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($version)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true]);

    $matriculaA = MatriculaPeriodo::factory()->paraAlumno(Alumno::factory()->create())->paraPeriodo($periodo)->paraPrograma($programa)->create();
    $matriculaB = MatriculaPeriodo::factory()->paraAlumno(Alumno::factory()->create())->paraPeriodo($periodo)->paraPrograma($programa)->create();

    $i1 = InscripcionMateria::factory()->paraMatricula($matriculaA)->paraGrupo($grupo)->cursando()->create();
    $i2 = InscripcionMateria::factory()->paraMatricula($matriculaB)->paraGrupo($grupo)->cursando()->create();

    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    Livewire::actingAs($admin)
        ->test(AsistenciaAdmin::class)
        ->call('seleccionarGrupo', (string) $grupo->id)
        ->call('cargarLista')
        ->assertHasNoErrors()
        ->set("asistencias.{$i1->id}.presente", true)
        ->set("asistencias.{$i2->id}.presente", false)
        ->call('guardar')
        ->assertHasNoErrors();

    dump(Asistencia::query()->get()->map(fn ($a) => [
        'inscripcion' => $a->inscripcion_materia_id,
        'presente' => $a->presente,
        'raw' => \DB::table('asistencias')->where('id', $a->id)->value('presente'),
    ])->toArray());

    expect(Asistencia::count())->toBe(2);
    expect(Asistencia::where('inscripcion_materia_id', $i1->id)->first()?->presente)->toBeTrue();
    expect(Asistencia::where('inscripcion_materia_id', $i2->id)->first()?->presente)->toBeFalse();
});
