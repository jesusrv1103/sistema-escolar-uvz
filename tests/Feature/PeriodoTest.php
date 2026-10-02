<?php

use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Livewire\Inscripciones\WizardInscripcion;
use App\Models\Alumno;
use App\Models\Inscripcion;
use App\Models\Periodo;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Fase 6 — RB-13: periodo cerrado (`cerrado_at`) bloquea inscripciones (F28)
|--------------------------------------------------------------------------
| Corre EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| Semántica exclusiva de inscripción: NO se extiende a módulos académicos.
*/

test('F28: un periodo cerrado bloquea nuevas inscripciones', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());

    $periodo = Periodo::create([
        'clave' => 'CERR-2026',
        'nombre' => 'Periodo cerrado sintetico',
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-06-30',
        'activo' => true,
        'cerrado_at' => now(),
    ]);
    $alumno = Alumno::factory()->create();

    $componente = Livewire::test(WizardInscripcion::class)
        ->set([
            'alumnoId' => $alumno->id,
            'programaId' => \Database\Factories\InscripcionFactory::programaPrueba()->id,
            'periodoId' => $periodo->id,
            'monto_inscripcion' => '1500.00',
            'monto_mensualidad' => '1200.00',
        ])
        ->call('avanzar')
        ->assertHasErrors(['periodoId'])
        ->assertSet('paso', 1);

    expect($componente->errors()->first('periodoId'))
        ->toContain('El periodo está cerrado')
        ->and(Inscripcion::count())->toBe(0);
});

test('F28: un periodo cerrado bloquea modificaciones del borrador existente', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());

    $inscripcion = Inscripcion::factory()->create();
    $inscripcion->periodo->update(['cerrado_at' => now()]);

    // Modificar la selección del paso 1 queda bloqueado.
    $componente = Livewire::test(WizardInscripcion::class, ['inscripcion' => $inscripcion])
        ->call('irAPaso', 1)
        ->call('avanzar')
        ->assertHasErrors(['paso'])
        ->assertSet('paso', 1);

    expect($componente->errors()->first('paso'))
        ->toContain('El periodo está cerrado');

    // Completar los datos también queda bloqueado: el estatus no cambia.
    Livewire::test(WizardInscripcion::class, ['inscripcion' => $inscripcion])
        ->call('completar')
        ->assertHasErrors(['paso']);

    expect($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::DRAFT)
        ->and($inscripcion->datos_completados_at)->toBeNull();
});
