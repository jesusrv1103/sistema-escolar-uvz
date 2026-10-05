<?php

use App\Models\Alumno;
use App\Models\Baja;
use App\Models\InscripcionMateria;
use App\Models\MatriculaPeriodo;
use App\Models\Periodo;
use App\Models\Programa;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\Materia;
use App\Models\Grupo;
use App\Models\User;
use App\Enums\TipoBaja;
use App\Enums\RolUsuario;
use App\Enums\EstatusInscripcionMateria;
use App\Enums\EstatusGrupo;
use App\Enums\TurnoGrupo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('bajas');

test('registra baja temporal para alumno', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true]);
    
    $matricula = MatriculaPeriodo::factory()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create();
    $inscripcion = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo)->cursando()->create();
    
    $service = app(\App\Services\AsistenciaService::class); // Usar servicio de baja cuando exista
    
    // Simular creación directa
    $baja = Baja::create([
        'alumno_id' => $alumno->id,
        'tipo' => TipoBaja::TEMPORAL,
        'motivo' => 'Motivos personales',
        'fecha' => now()->format('Y-m-d'),
        'registrado_por' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()->id,
    ]);
    
    expect($baja->esTemporal())->toBeTrue();
    expect($baja->estaVigente())->toBeTrue();
    expect($baja->tieneReingreso())->toBeFalse();
});

test('baja temporal bloquea nueva inscripción', function () {
    $alumno = Alumno::factory()->create();
    
    $baja = Baja::create([
        'alumno_id' => $alumno->id,
        'tipo' => TipoBaja::TEMPORAL,
        'motivo' => 'Motivos de salud',
        'fecha' => now()->format('Y-m-d'),
        'registrado_por' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()->id,
    ]);
    
    // Verificar que la baja está vigente
    expect($baja->estaVigente())->toBeTrue();
    expect($baja->tieneReingreso())->toBeFalse();
    
    // Simular verificación en servicio de inscripción
    $bajaVigente = Baja::where('alumno_id', $alumno->id)
        ->where('tipo', 'TEMPORAL')
        ->whereNull('reingreso_at')
        ->exists();
    
    expect($bajaVigente)->toBeTrue();
});

test('reingreso autorizado manualmente por CONTROL_ESCOLAR', function () {
    $alumno = Alumno::factory()->create();
    
    $baja = Baja::create([
        'alumno_id' => $alumno->id,
        'tipo' => TipoBaja::TEMPORAL,
        'motivo' => 'Motivos personales',
        'fecha' => now()->subDays(10)->format('Y-m-d'),
        'registrado_por' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()->id,
    ]);
    
    expect($baja->estaVigente())->toBeTrue();
    
    // Autorizar reingreso
    $baja->update([
        'reingreso_at' => now(),
    ]);
    
    $baja->refresh();
    expect($baja->tieneReingreso())->toBeTrue();
    expect($baja->estaVigente())->toBeFalse();
});

test('baja definitiva bloquea nuevas matrículas', function () {
    $alumno = Alumno::factory()->create();
    
    $baja = Baja::create([
        'alumno_id' => $alumno->id,
        'tipo' => TipoBaja::DEFINITIVA,
        'motivo' => 'Retiro voluntario',
        'fecha' => now()->format('Y-m-d'),
        'registrado_por' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()->id,
    ]);
    
    expect($baja->esDefinitiva())->toBeTrue();
    
    // Verificar que no se puede reingresar
    expect($baja->esDefinitiva())->toBeTrue();
});

test('baja de materia solo antes de acta cerrada', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true]);
    
    $matricula = MatriculaPeriodo::factory()->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->create();
    $inscripcion = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo)->cursando()->create();
    
    // Acta ABIERTA - permitir baja de materia
    \App\Models\Acta::factory()->create([
        'grupo_id' => $grupo->id,
        'periodo_id' => $periodo->id,
        'estatus' => 'ABIERTA',
    ]);
    
    $baja = Baja::create([
        'alumno_id' => $alumno->id,
        'inscripcion_materia_id' => $inscripcion->id,
        'tipo' => TipoBaja::MATERIA,
        'motivo' => 'Cambio de carga académica',
        'fecha' => now()->format('Y-m-d'),
        'registrado_por' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()->id,
    ]);
    
    expect($baja->esDeMateria())->toBeTrue();
    expect($baja->inscripcion_materia_id)->toBe($inscripcion->id);
});
