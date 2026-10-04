<?php

namespace Tests\Feature;

use App\Enums\EstatusMatriculaPeriodo;
use App\Enums\OrigenMatricula;
use App\Enums\RolUsuario;
use App\Models\Alumno;
use App\Models\MatriculaPeriodo;
use App\Models\Periodo;
use App\Models\PlanVersion;
use App\Models\Programa;
use App\Models\User;
use App\Services\MatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MatriculaService tests using PHPUnit TestCase with RefreshDatabase.
 * This bypasses Pest's issues with RefreshDatabase trait.
 */
class MatriculaServicePhpUnitTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function crea_matricula_desde_inscripcion_finalizada(): void
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create(['activo' => true]);
        $programa = Programa::factory()->create(['activo' => true]);
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        $service = app(MatriculaService::class);
        $matricula = $service->crearDesdeInscripcion($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, $usuario);

        $this->assertInstanceOf(MatriculaPeriodo::class, $matricula);
        $this->assertEquals($alumno->id, $matricula->alumno_id);
        $this->assertEquals($periodo->id, $matricula->periodo_id);
        $this->assertEquals($programa->id, $matricula->programa_id);
        $this->assertEquals($planVersion->id, $matricula->plan_version_id);
        $this->assertEquals(OrigenMatricula::PRIMERA_VEZ, $matricula->origen);
        $this->assertEquals(EstatusMatriculaPeriodo::ACTIVA, $matricula->estatus);
    }

    #[Test]
    public function no_permite_matricula_duplicada_en_mismo_periodo(): void
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create(['activo' => true]);
        $programa = Programa::factory()->create(['activo' => true]);
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        $service = app(MatriculaService::class);
        $service->crearDesdeInscripcion($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, $usuario);

        $this->expectException(\App\Exceptions\MatriculaException::class);
        $service->crearDesdeInscripcion($alumno, $periodo, $programa, $planVersion, OrigenMatricula::REINGRESO, $usuario);
    }

    #[Test]
    public function crea_matricula_manual(): void
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create(['activo' => true]);
        $programa = Programa::factory()->create(['activo' => true]);
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        $service = app(MatriculaService::class);
        $matricula = $service->crear($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, 'Observación de prueba', $usuario);

        $this->assertInstanceOf(MatriculaPeriodo::class, $matricula);
        $this->assertEquals('Observación de prueba', $matricula->observaciones);
    }

    #[Test]
    public function no_permite_matricula_en_periodo_cerrado(): void
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create(['activo' => true, 'cerrado_at' => now()]);
        $programa = Programa::factory()->create(['activo' => true]);
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        $service = app(MatriculaService::class);
        $this->expectException(\App\Exceptions\MatriculaException::class);
        $service->crear($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, null, $usuario);
    }

    #[Test]
    public function no_permite_matricula_en_programa_inactivo(): void
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create(['activo' => true]);
        $programa = Programa::factory()->create(['activo' => false]);
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        $service = app(MatriculaService::class);
        $this->expectException(\App\Exceptions\MatriculaException::class);
        $service->crear($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, null, $usuario);
    }

    #[Test]
    public function cambia_estatus_de_matricula_correctamente(): void
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create();
        $programa = Programa::factory()->create();
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        $service = app(MatriculaService::class);
        $matricula = $service->crearDesdeInscripcion($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, $usuario);

        $matricula = $service->cambiarEstatus($matricula, EstatusMatriculaPeriodo::BAJA_TEMPORAL, 'Baja temporal por salud', $usuario);
        $this->assertEquals(EstatusMatriculaPeriodo::BAJA_TEMPORAL, $matricula->estatus);

        $matricula = $service->cambiarEstatus($matricula, EstatusMatriculaPeriodo::ACTIVA, 'Reactivación', $usuario);
        $this->assertEquals(EstatusMatriculaPeriodo::ACTIVA, $matricula->estatus);
    }

    #[Test]
    public function no_permite_transiciones_invalidas_de_estatus(): void
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create();
        $programa = Programa::factory()->create();
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        $service = app(MatriculaService::class);
        $matricula = $service->crearDesdeInscripcion($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, $usuario);

        $service->cambiarEstatus($matricula, EstatusMatriculaPeriodo::BAJA_DEFINITIVA, 'Baja definitiva', $usuario);

        $this->expectException(\App\Exceptions\MatriculaException::class);
        $service->cambiarEstatus($matricula, EstatusMatriculaPeriodo::BAJA_TEMPORAL, 'Inválido', $usuario);
    }

    #[Test]
    public function verifica_si_alumno_puede_matricularse(): void
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create();
        $programa = Programa::factory()->create();
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        $service = app(MatriculaService::class);

        $this->assertTrue($service->puedeMatricularse($alumno->id, $periodo->id));

        $service->crearDesdeInscripcion($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, $usuario);

        $this->assertFalse($service->puedeMatricularse($alumno->id, $periodo->id));
    }

    #[Test]
    public function obtiene_matricula_activa(): void
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create();
        $programa = Programa::factory()->create();
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        $service = app(MatriculaService::class);
        $matricula = $service->crearDesdeInscripcion($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, $usuario);

        $activa = $service->obtenerActiva($alumno->id, $periodo->id);

        $this->assertNotNull($activa);
        $this->assertEquals($matricula->id, $activa->id);
    }
}