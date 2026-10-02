<?php

use App\Enums\RolUsuario;
use App\Livewire\Inscripciones\WizardInscripcion;
use App\Models\Alumno;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/*
|--------------------------------------------------------------------------
| Fase 6 — RB-14: duplicidad alumno + periodo (F22)
|--------------------------------------------------------------------------
| Corre EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
*/

test('F22: la doble inscripcion de un alumno en el mismo periodo es rechazada', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());

    $alumno = Alumno::factory()->create();
    $existente = Inscripcion::factory()->create(['alumno_id' => $alumno->id]);

    // Validación server-side en el wizard (antes de guardar), con mensaje claro.
    Livewire::test(WizardInscripcion::class)
        ->set([
            'alumnoId' => $alumno->id,
            'programaId' => $existente->programa_id,
            'periodoId' => $existente->periodo_id,
            'monto_inscripcion' => '1500.00',
            'monto_mensualidad' => '1200.00',
        ])
        ->call('avanzar')
        ->assertHasErrors(['alumnoId' => 'Este alumno ya tiene una inscripción en el periodo seleccionado (RB-14).'])
        ->assertSet('paso', 1);

    expect(Inscripcion::count())->toBe(1);

    // Respaldo: la restricción UNIQUE de BD sigue siendo la última línea.
    expect(fn () => Inscripcion::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_id' => $existente->periodo_id,
    ]))->toThrow(UniqueConstraintViolationException::class);

    expect(Inscripcion::count())->toBe(1);
});

test('el mismo alumno si puede inscribirse en un periodo diferente', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());

    $alumno = Alumno::factory()->create();
    $primera = Inscripcion::factory()->create(['alumno_id' => $alumno->id]);

    $otroPeriodo = \App\Models\Periodo::create([
        'clave' => 'OTRO-2026',
        'nombre' => 'Otro periodo sintetico',
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-05-31',
        'activo' => true,
    ]);

    Livewire::test(WizardInscripcion::class)
        ->set([
            'alumnoId' => $alumno->id,
            'programaId' => $primera->programa_id,
            'periodoId' => $otroPeriodo->id,
            'monto_inscripcion' => '900.00',
            'monto_mensualidad' => '800.00',
        ])
        ->call('avanzar')
        ->assertHasNoErrors()
        ->assertSet('paso', 2);

    expect(Inscripcion::count())->toBe(2);
});
