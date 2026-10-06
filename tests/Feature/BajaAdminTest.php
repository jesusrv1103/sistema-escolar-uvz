<?php

use App\Livewire\Academico\BajaAdmin;
use App\Models\Acta;
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
use App\Enums\EstatusMatriculaPeriodo;
use App\Enums\EstatusGrupo;
use App\Enums\TurnoGrupo;
use App\Exceptions\InscripcionMateriaException;
use App\Exceptions\MatriculaException;
use App\Services\HistorialAcademicoService;
use App\Services\InscripcionMateriaService;
use App\Services\MatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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

/*
|--------------------------------------------------------------------------
| Cierre pre-merge — los modales de BajaAdmin no persistían (stub)
|--------------------------------------------------------------------------
|
| `BajaAdmin::registrarBaja()` y `BajaAdmin::autorizarReingreso()` sólo
| cerraban el modal y despachaban el evento: la llamada al servicio estaba
| comentada (`// $service = app(BajaService::class);`), así que no existía
| fila en `bajas`, no cambiaba `matricula_periodos.estatus`, no se marcaba
| `reingreso_at` y la inscripción a materia no pasaba a BAJA. El botón de
| "Autorizar Reingreso" ni siquiera estaba asociado al `<form>` (type="button"
| suelto en el pie), así que esa acción no llegaba a Livewire.
|
| Los tests de más arriba creaban la baja con `Baja::create()` directo y por
| eso no lo veían. Éstos recorren el componente real y verifican las reglas
| aprobadas de 2.0-11 (TEMPORAL / DEFINITIVA / MATERIA).
|
*/

/**
 * Escenario mínimo: alumno con matrícula ACTIVA, grupo ABIERTO e inscripción
 * CURSANDO en una materia del plan.
 *
 * @return array<string, mixed>
 */
function escenarioBajaF11(): array
{
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->paraPrograma($programa)->create();
    $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
    $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true]);
    $matricula = MatriculaPeriodo::factory()
        ->paraAlumno($alumno)->paraPeriodo($periodo)->paraPrograma($programa)->activa()->create();
    $inscripcion = InscripcionMateria::factory()
        ->paraMatricula($matricula)->paraGrupo($grupo)->cursando()->create(['calificacion_final' => null]);

    return compact('alumno', 'periodo', 'programa', 'planVersion', 'materia', 'grupo', 'matricula', 'inscripcion');
}

/**
 * Ejecuta el flujo real del modal de baja sobre el componente.
 *
 * @param  array<string, mixed>  $escenario
 */
function registrarBajaPorUi(array $escenario, string $tipo, User $control, ?int $inscripcionId = null): void
{
    $prueba = Livewire::actingAs($control)
        ->test(BajaAdmin::class)
        ->call('abrirModalBaja', $tipo)
        ->call('seleccionarAlumno', (string) $escenario['alumno']->id);

    if ($inscripcionId !== null) {
        $prueba->call('seleccionarInscripcion', (string) $inscripcionId);
    }

    $prueba
        ->set('motivo', 'Motivo documentado de la baja')
        ->set('fecha', now()->format('Y-m-d'))
        ->call('registrarBaja')
        ->assertHasNoErrors()
        ->assertSet('modalBajaAbierto', false);
}

test('el modal de baja temporal persiste la baja y cambia la matrícula', function () {
    $s = escenarioBajaF11();
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    registrarBajaPorUi($s, 'TEMPORAL', $control);

    $baja = Baja::where('alumno_id', $s['alumno']->id)->first();

    expect($baja)->not->toBeNull()
        ->and($baja->tipo)->toBe(TipoBaja::TEMPORAL)
        ->and($baja->motivo)->toBe('Motivo documentado de la baja')
        ->and($baja->registrado_por)->toBe($control->id)
        ->and($baja->estaVigente())->toBeTrue()
        ->and($s['matricula']->refresh()->estatus)->toBe(EstatusMatriculaPeriodo::BAJA_TEMPORAL);
});

test('el reingreso autorizado persiste la fecha y restaura la matrícula', function () {
    $s = escenarioBajaF11();
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    registrarBajaPorUi($s, 'TEMPORAL', $control);
    $baja = Baja::firstOrFail();

    Livewire::actingAs($control)
        ->test(BajaAdmin::class)
        ->call('abrirModalReingreso', $baja->id)
        ->assertSet('modalReingresoAbierto', true)
        ->set('reingresoAt', now()->format('Y-m-d H:i'))
        ->call('autorizarReingreso')
        ->assertHasNoErrors()
        ->assertSet('modalReingresoAbierto', false);

    expect($baja->refresh()->reingreso_at)->not->toBeNull()
        ->and($baja->tieneReingreso())->toBeTrue()
        ->and($baja->estaVigente())->toBeFalse()
        ->and($s['matricula']->refresh()->estatus)->toBe(EstatusMatriculaPeriodo::ACTIVA);
});

test('la baja definitiva persiste con auditoría e impide nuevas matrículas', function () {
    $s = escenarioBajaF11();
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    registrarBajaPorUi($s, 'DEFINITIVA', $control);

    $baja = Baja::firstOrFail();

    expect($baja->esDefinitiva())->toBeTrue()
        ->and($s['matricula']->refresh()->estatus)->toBe(EstatusMatriculaPeriodo::BAJA_DEFINITIVA)
        ->and($baja->auditorias()->count())->toBeGreaterThan(0)
        ->and($s['alumno']->fresh()->deleted_at)->toBeNull();

    $otroPeriodo = Periodo::factory()->create(['activo' => true]);
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $this->actingAs($admin);

    expect(fn () => app(MatriculaService::class)
            ->crear($s['alumno'], $otroPeriodo, $s['programa']))
        ->toThrow(MatriculaException::class);
});

test('la baja temporal bloquea nuevas cargas académicas', function () {
    $s = escenarioBajaF11();
    $materia2 = Materia::factory()->paraPlanVersion($s['planVersion'])->create(['activo' => true]);
    $grupo2 = Grupo::factory()->paraMateria($materia2)->paraPeriodo($s['periodo'])->abierto()->create(['activo' => true]);
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    registrarBajaPorUi($s, 'TEMPORAL', $control);

    expect(fn () => app(InscripcionMateriaService::class)
            ->inscribir($s['matricula']->refresh(), $grupo2, $control))
        ->toThrow(InscripcionMateriaException::class);

    expect($s['inscripcion']->refresh()->estatus)->toBe(EstatusInscripcionMateria::CURSANDO);
});

test('la baja de materia cambia la inscripción a BAJA y sale del historial', function () {
    $s = escenarioBajaF11();
    $s['inscripcion']->forceFill([
        'estatus' => EstatusInscripcionMateria::APROBADA->value,
        'calificacion_final' => 90,
    ])->save();
    Acta::factory()->create([
        'grupo_id' => $s['grupo']->id,
        'periodo_id' => $s['periodo']->id,
        'estatus' => 'ABIERTA',
    ]);

    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    registrarBajaPorUi($s, 'MATERIA', $control, $s['inscripcion']->id);

    expect($s['inscripcion']->refresh()->estatus)->toBe(EstatusInscripcionMateria::BAJA)
        ->and($s['inscripcion']->fecha_baja)->not->toBeNull()
        ->and($s['matricula']->refresh()->estatus)->toBe(EstatusMatriculaPeriodo::ACTIVA);

    $historial = app(HistorialAcademicoService::class);

    expect($historial->obtenerHistorial($s['alumno'])->pluck('materia.clave')->all())
        ->not->toContain($s['materia']->clave)
        ->and($historial->calcularPromedio($s['alumno']))->toBeNull()
        ->and($historial->creditosAprobados($s['alumno']))->toBe(0);
});

test('la baja de materia se rechaza cuando el acta está cerrada', function () {
    $s = escenarioBajaF11();
    Acta::factory()->create([
        'grupo_id' => $s['grupo']->id,
        'periodo_id' => $s['periodo']->id,
        'estatus' => 'CERRADA',
    ]);

    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    Livewire::actingAs($control)
        ->test(BajaAdmin::class)
        ->call('abrirModalBaja', 'MATERIA')
        ->call('seleccionarAlumno', (string) $s['alumno']->id)
        ->call('seleccionarInscripcion', (string) $s['inscripcion']->id)
        ->set('motivo', 'Motivo documentado de la baja')
        ->set('fecha', now()->format('Y-m-d'))
        ->call('registrarBaja')
        ->assertHasErrors(['registrar']);

    expect(Baja::count())->toBe(0)
        ->and($s['inscripcion']->refresh()->estatus)->toBe(EstatusInscripcionMateria::CURSANDO);
});
