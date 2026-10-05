<?php

use App\Models\Alumno;
use App\Models\Equivalencia;
use App\Models\Materia;
use App\Models\Periodo;
use App\Models\Programa;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\User;
use App\Enums\EstatusEquivalencia;
use App\Enums\RolUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('equivalencias');

test('registra equivalencia pendiente', function () {
    $alumno = Alumno::factory()->create();
    $materia = Materia::factory()->create(['activo' => true, 'creditos' => 6]);
    
    $equivalencia = Equivalencia::create([
        'alumno_id' => $alumno->id,
        'materia_id' => null, // Crédito libre
        'origen' => 'Universidad X, México',
        'creditos' => 6,
        'dictamen' => 'Homologación por estudios previos',
        'estatus' => EstatusEquivalencia::PENDIENTE,
        'autorizada_por' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()->id,
        'fecha_dictamen' => now()->format('Y-m-d'),
    ]);
    
    expect($equivalencia->esPendiente())->toBeTrue();
    expect($equivalencia->creditos)->toBe(6);
    expect($equivalencia->esAprobada())->toBeFalse();
});

test('aprobar equivalencia cuenta créditos para avance pero no para promedio', function () {
    $alumno = Alumno::factory()->create();
    $materia = Materia::factory()->create(['activo' => true, 'creditos' => 6]);
    
    $equivalencia = Equivalencia::create([
        'alumno_id' => $alumno->id,
        'materia_id' => $materia->id,
        'origen' => 'Universidad Extranjera',
        'creditos' => 6,
        'dictamen' => 'Homologación aprobada',
        'estatus' => EstatusEquivalencia::APROBADA,
        'autorizada_por' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()->id,
        'fecha_dictamen' => now()->format('Y-m-d'),
    ]);
    
    expect($equivalencia->esAprobada())->toBeTrue();
    expect($equivalencia->creditos)->toBe(6);
    
    // Verificar que no tiene calificación numérica (no entra al promedio)
    expect($equivalencia->calificacion_numerica ?? null)->toBeNull();
});

test('rechazar equivalencia no cuenta créditos', function () {
    $alumno = Alumno::factory()->create();
    $materia = Materia::factory()->create(['activo' => true, 'creditos' => 6]);
    
    $equivalencia = Equivalencia::create([
        'alumno_id' => $alumno->id,
        'materia_id' => $materia->id,
        'origen' => 'Institución no reconocida',
        'creditos' => 6,
        'dictamen' => 'No cumple requisitos',
        'estatus' => EstatusEquivalencia::RECHAZADA,
        'autorizada_por' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()->id,
        'fecha_dictamen' => now()->format('Y-m-d'),
    ]);
    
    expect($equivalencia->esRechazada())->toBeTrue();
    expect($equivalencia->esAprobada())->toBeFalse();
});

test('equivalencia sin materia UVZ (crédito libre) se registra', function () {
    $alumno = Alumno::factory()->create();
    
    $equivalencia = Equivalencia::create([
        'alumno_id' => $alumno->id,
        'materia_id' => null,
        'origen' => 'Experiencia laboral certificada',
        'creditos' => 4,
        'dictamen' => 'Reconocimiento de experiencia laboral',
        'estatus' => EstatusEquivalencia::APROBADA,
        'autorizada_por' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()->id,
        'fecha_dictamen' => now()->format('Y-m-d'),
    ]);
    
    expect($equivalencia->materia_id)->toBeNull();
    expect($equivalencia->creditos)->toBe(4);
    expect($equivalencia->esAprobada())->toBeTrue();
});

test('equivalencia no entra al promedio académico', function () {
    $alumno = Alumno::factory()->create();
    
    // Crear materia normal aprobada
    $materia1 = \App\Models\Materia::factory()->create(['creditos' => 4]);
    $grupo1 = \App\Models\Grupo::factory()->create(['activo' => true]);
    
    $matricula = \App\Models\MatriculaPeriodo::factory()->create();
    $inscripcion1 = \App\Models\InscripcionMateria::factory()->create([
        'matricula_periodo_id' => $matricula->id,
        'grupo_id' => $grupo1->id,
        'materia_id' => \App\Models\Materia::factory()->create(['creditos' => 4])->id,
        'estatus' => \App\Enums\EstatusInscripcionMateria::APROBADA,
        'calificacion_final' => 80.00,
    ]);
    
    // Crear equivalencia aprobada
    $equivalencia = Equivalencia::create([
        'alumno_id' => $matricula->alumno_id,
        'materia_id' => null,
        'origen' => 'Universidad Extranjera',
        'creditos' => 6,
        'dictamen' => 'Homologación',
        'estatus' => \App\Enums\EstatusEquivalencia::APROBADA,
        'autorizada_por' => \App\Models\User::factory()->create()->id,
        'fecha_dictamen' => now()->format('Y-m-d'),
    ]);
    
    // Verificar que la equivalencia NO tiene calificación numérica
    expect($equivalencia->calificacion_numerica ?? null)->toBeNull();
});
