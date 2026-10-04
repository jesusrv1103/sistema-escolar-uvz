<?php

namespace Tests\Feature;

use App\Enums\EstatusGrupo;
use App\Enums\EstatusInscripcionMateria;
use App\Enums\EstatusMatriculaPeriodo;
use App\Enums\OrigenMatricula;
use App\Enums\RolUsuario;
use App\Enums\TipoPrerrequisito;
use App\Models\Alumno;
use App\Models\Grupo;
use App\Models\InscripcionMateria;
use App\Models\MatriculaPeriodo;
use App\Models\Materia;
use App\Models\Periodo;
use App\Models\PlanVersion;
use App\Models\Programa;
use App\Models\User;
use App\Services\InscripcionMateriaService;
use App\Services\MatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * InscripcionMateriaService tests using PHPUnit TestCase with RefreshDatabase.
 * This bypasses Pest's issues with RefreshDatabase trait.
 */
class InscripcionMateriaServicePhpUnitTest extends TestCase
{
    use RefreshDatabase;

    private function createBaseData(): array
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create(['activo' => true]);
        $programa = Programa::factory()->create(['activo' => true]);
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
        $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true]);
        $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        $matriculaService = app(MatriculaService::class);
        $matricula = $matriculaService->crearDesdeInscripcion($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, $usuario);

        return [
            'alumno' => $alumno,
            'periodo' => $periodo,
            'programa' => $programa,
            'planVersion' => $planVersion,
            'materia' => $materia,
            'grupo' => $grupo,
            'usuario' => $usuario,
            'matricula' => $matricula,
            'matriculaService' => $matriculaService,
        ];
    }

    #[Test]
    public function inscribe_matricula_a_grupo_valido(): void
    {
        $data = $this->createBaseData();

        $service = app(InscripcionMateriaService::class);
        $inscripcion = $service->inscribir($data['matricula'], $data['grupo'], $data['usuario']);

        $this->assertInstanceOf(InscripcionMateria::class, $inscripcion);
        $this->assertEquals($data['matricula']->id, $inscripcion->matricula_periodo_id);
        $this->assertEquals($data['grupo']->id, $inscripcion->grupo_id);
        $this->assertEquals($data['materia']->id, $inscripcion->materia_id);
        $this->assertEquals(EstatusInscripcionMateria::CURSANDO, $inscripcion->estatus);
    }

    #[Test]
    public function no_permite_inscribir_en_grupo_sin_cupo(): void
    {
        $data = $this->createBaseData();
        // Create a group with 0 cupo
        $grupoSinCupo = Grupo::factory()->paraMateria($data['materia'])->paraPeriodo($data['periodo'])->abierto()->create(['activo' => true, 'cupo' => 0]);

        $service = app(InscripcionMateriaService::class);
        $this->expectException(\App\Exceptions\InscripcionMateriaException::class);
        $service->inscribir($data['matricula'], $grupoSinCupo, $data['usuario']);
    }

    #[Test]
    public function no_permite_inscribir_en_grupo_no_abierto(): void
    {
        $data = $this->createBaseData();
        $grupoCerrado = Grupo::factory()->paraMateria($data['materia'])->paraPeriodo($data['periodo'])->cerrado()->create(['activo' => true, 'cupo' => 40]);

        $service = app(InscripcionMateriaService::class);
        $this->expectException(\App\Exceptions\InscripcionMateriaException::class);
        $service->inscribir($data['matricula'], $grupoCerrado, $data['usuario']);
    }

    #[Test]
    public function no_permite_inscripcion_duplicada_en_mismo_grupo(): void
    {
        $data = $this->createBaseData();

        $service = app(InscripcionMateriaService::class);
        $service->inscribir($data['matricula'], $data['grupo'], $data['usuario']);

        $this->expectException(\App\Exceptions\InscripcionMateriaException::class);
        $service->inscribir($data['matricula'], $data['grupo'], $data['usuario']);
    }

    #[Test]
    public function no_permite_inscripcion_en_misma_materia_del_mismo_periodo(): void
    {
        $data = $this->createBaseData();
        $grupo2 = Grupo::factory()->paraMateria($data['materia'])->paraPeriodo($data['periodo'])->abierto()->create(['activo' => true, 'cupo' => 40, 'clave' => 'GRP-002']);

        $service = app(InscripcionMateriaService::class);
        $service->inscribir($data['matricula'], $data['grupo'], $data['usuario']);

        $this->expectException(\App\Exceptions\InscripcionMateriaException::class);
        $service->inscribir($data['matricula'], $grupo2, $data['usuario']);
    }

    #[Test]
    public function valida_prerrequisito_recomendado_no_bloquea(): void
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create(['activo' => true]);
        $programa = Programa::factory()->create(['activo' => true]);
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();

        $materiaPrerreq = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'clave' => 'MAT-101']);
        $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'clave' => 'MAT-102']);
        $materia->prerrequisitos()->attach($materiaPrerreq->id, ['tipo' => TipoPrerrequisito::RECOMENDADO->value]);

        $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        $matriculaService = app(MatriculaService::class);
        $matricula = $matriculaService->crearDesdeInscripcion($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, $usuario);

        $service = app(InscripcionMateriaService::class);
        $inscripcion = $service->inscribir($matricula, $grupo, $usuario);

        $this->assertEquals(TipoPrerrequisito::RECOMENDADO, $inscripcion->tipo_prerrequisito);
        $this->assertFalse($inscripcion->prerrequisito_cumplido);
    }

    #[Test]
    public function valida_prerrequisito_obligatorio_sin_historial_no_bloquea(): void
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create(['activo' => true]);
        $programa = Programa::factory()->create(['activo' => true]);
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();

        $materiaPrerreq = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'clave' => 'MAT-101']);
        $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'clave' => 'MAT-102']);
        $materia->prerrequisitos()->attach($materiaPrerreq->id, ['tipo' => TipoPrerrequisito::OBLIGATORIO->value]);

        $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        $matriculaService = app(MatriculaService::class);
        $matricula = $matriculaService->crearDesdeInscripcion($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, $usuario);

        $service = app(InscripcionMateriaService::class);
        $inscripcion = $service->inscribir($matricula, $grupo, $usuario);

        $this->assertEquals(TipoPrerrequisito::OBLIGATORIO, $inscripcion->tipo_prerrequisito);
        $this->assertFalse($inscripcion->prerrequisito_cumplido);
    }

    #[Test]
    public function valida_prerrequisito_obligatorio_reprobado_bloquea(): void
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create(['activo' => true]);
        $programa = Programa::factory()->create(['activo' => true]);
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();

        $materiaPrerreq = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'clave' => 'MAT-101']);
        $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'clave' => 'MAT-102']);
        $materia->prerrequisitos()->attach($materiaPrerreq->id, ['tipo' => TipoPrerrequisito::OBLIGATORIO->value]);

        $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        $matriculaService = app(MatriculaService::class);
        $matricula = $matriculaService->crearDesdeInscripcion($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, $usuario);

        $grupoPrerreq = Grupo::factory()->paraMateria($materiaPrerreq)->paraPeriodo($periodo)->cerrado()->create(['activo' => true, 'cupo' => 40]);
        InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupoPrerreq)->reprobada()->create();

        $service = app(InscripcionMateriaService::class);
        $this->expectException(\App\Exceptions\InscripcionMateriaException::class);
        $service->inscribir($matricula, $grupo, $usuario);
    }

    #[Test]
    public function valida_prerrequisito_obligatorio_aprobado_permite(): void
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create(['activo' => true]);
        $programa = Programa::factory()->create(['activo' => true]);
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();

        $materiaPrerreq = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'clave' => 'MAT-101']);
        $materia = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'clave' => 'MAT-102']);
        $materia->prerrequisitos()->attach($materiaPrerreq->id, ['tipo' => TipoPrerrequisito::OBLIGATORIO->value]);

        $grupo = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        $matriculaService = app(MatriculaService::class);
        $matricula = $matriculaService->crearDesdeInscripcion($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, $usuario);

        $grupoPrerreq = Grupo::factory()->paraMateria($materiaPrerreq)->paraPeriodo($periodo)->cerrado()->create(['activo' => true, 'cupo' => 40]);
        $inscripcionPrerreq = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupoPrerreq)->aprobada()->create();

        // Debug: check the inscripcion
        $this->assertEquals($matricula->id, $inscripcionPrerreq->matricula_periodo_id);
        $this->assertEquals($materiaPrerreq->id, $inscripcionPrerreq->materia_id);
        $this->assertEquals(EstatusInscripcionMateria::APROBADA, $inscripcionPrerreq->estatus);

        // Debug: check if query finds it
        $found = InscripcionMateria::where('matricula_periodo_id', $matricula->id)
            ->where('materia_id', $materiaPrerreq->id)
            ->whereIn('estatus', [EstatusInscripcionMateria::APROBADA->value, EstatusInscripcionMateria::REPROBADA->value, EstatusInscripcionMateria::VALIDADA->value])
            ->first();
        $this->assertNotNull($found, 'Prerrequisito inscripcion should be found by query');
        $this->assertEquals(EstatusInscripcionMateria::APROBADA, $found->estatus);

        // Debug: check the materia relationship on grupo
        $this->assertEquals($materia->id, $grupo->materia_id);
        $grupoMateria = $grupo->materia;
        $this->assertEquals($materia->id, $grupoMateria->id);
        $prerreqsObligatorios = $grupoMateria->prerrequisitosObligatorios()->get();
        $this->assertCount(1, $prerreqsObligatorios, 'Materia should have 1 obligatorio prerrequisito');
        $this->assertEquals($materiaPrerreq->id, $prerreqsObligatorios->first()->id);

        $service = app(InscripcionMateriaService::class);
        $inscripcion = $service->inscribir($matricula, $grupo, $usuario);

        $this->assertEquals(TipoPrerrequisito::OBLIGATORIO, $inscripcion->tipo_prerrequisito);
        $this->assertTrue($inscripcion->prerrequisito_cumplido);
    }

    #[Test]
    public function no_permite_inscripcion_si_matricula_no_esta_activa(): void
    {
        $data = $this->createBaseData();

        $matriculaService = app(MatriculaService::class);
        $matriculaService->cambiarEstatus($data['matricula'], EstatusMatriculaPeriodo::BAJA_TEMPORAL, 'Baja temporal', $data['usuario']);

        $service = app(InscripcionMateriaService::class);
        $this->expectException(\App\Exceptions\InscripcionMateriaException::class);
        $service->inscribir($data['matricula'], $data['grupo'], $data['usuario']);
    }

    #[Test]
    public function cambia_estatus_de_inscripcion_a_aprobada(): void
    {
        $data = $this->createBaseData();

        $service = app(InscripcionMateriaService::class);
        $inscripcion = $service->inscribir($data['matricula'], $data['grupo'], $data['usuario']);

        $inscripcion = $service->cambiarEstatus($inscripcion, EstatusInscripcionMateria::APROBADA, 85.5, 'Aprobado con buena nota', $data['usuario']);

        $this->assertEquals(EstatusInscripcionMateria::APROBADA, $inscripcion->estatus);
        $this->assertEquals('85.50', (string) $inscripcion->calificacion_final);
    }

    #[Test]
    public function no_permite_aprobar_sin_calificacion(): void
    {
        $data = $this->createBaseData();

        $service = app(InscripcionMateriaService::class);
        $inscripcion = $service->inscribir($data['matricula'], $data['grupo'], $data['usuario']);

        $this->expectException(\App\Exceptions\InscripcionMateriaException::class);
        $service->cambiarEstatus($inscripcion, EstatusInscripcionMateria::APROBADA, null, 'Sin calificación', $data['usuario']);
    }

    #[Test]
    public function valida_conflictos_de_horario(): void
    {
        $alumno = Alumno::factory()->create();
        $periodo = Periodo::factory()->create(['activo' => true]);
        $programa = Programa::factory()->create(['activo' => true]);
        $planEstudio = \App\Models\PlanEstudio::factory()->paraPrograma($programa)->create();
        $planVersion = PlanVersion::factory()->forPlanEstudio($planEstudio)->vigente()->create();
        $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();

        // Materia 1 con horario LUNES 08:00-10:00
        $materia1 = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'clave' => 'MAT-101']);
        $grupo1 = Grupo::factory()->paraMateria($materia1)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
        \App\Models\GrupoHorario::create([
            'grupo_id' => $grupo1->id,
            'dia' => \App\Enums\DiaSemana::LUNES,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '10:00:00',
        ]);

        // Materia 2 con horario LUNES 09:00-11:00 (solapa)
        $materia2 = Materia::factory()->paraPlanVersion($planVersion)->create(['activo' => true, 'clave' => 'MAT-102']);
        $grupo2 = Grupo::factory()->paraMateria($materia2)->paraPeriodo($periodo)->abierto()->create(['activo' => true, 'cupo' => 40]);
        \App\Models\GrupoHorario::create([
            'grupo_id' => $grupo2->id,
            'dia' => \App\Enums\DiaSemana::LUNES,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '11:00:00',
        ]);

        $matriculaService = app(MatriculaService::class);
        $matricula = $matriculaService->crearDesdeInscripcion($alumno, $periodo, $programa, $planVersion, OrigenMatricula::PRIMERA_VEZ, $usuario);

        $service = app(InscripcionMateriaService::class);
        $service->inscribir($matricula, $grupo1, $usuario);

        $this->expectException(\App\Exceptions\InscripcionMateriaException::class);
        $service->inscribir($matricula, $grupo2, $usuario);
    }
}