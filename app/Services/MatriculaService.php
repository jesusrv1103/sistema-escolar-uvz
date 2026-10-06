<?php

namespace App\Services;

use App\Enums\EstatusMatriculaPeriodo;
use App\Enums\OrigenMatricula;
use App\Exceptions\MatriculaException;
use App\Models\Alumno;
use App\Models\MatriculaPeriodo;
use App\Models\Periodo;
use App\Models\PlanVersion;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Servicio para gestión de matrículas por periodo.
 *
 * La matrícula (MatriculaPeriodo) vincula al alumno con un periodo, programa y plan.
 * Es distinta a la inscripción a materias (InscripcionMateria).
 *
 * Reglas de negocio:
 * - UNIQUE(alumno_id, periodo_id): un alumno solo puede tener una matrícula activa por periodo
 * - La primera matrícula se deriva del estatus ENROLLED de la inscripción (InscripcionService::finalizar)
 * - RECOMENDADO no bloquea, OBLIGATORIO solo si hay datos reales
 */
class MatriculaService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
        private readonly BajaService $bajas,
    ) {
    }

    /**
     * Crea la matrícula del periodo a partir de una inscripción finalizada (ENROLLED).
     * Se invoca desde InscripcionService::finalizar() tras asignar número de control.
     *
     * @throws MatriculaException si el alumno ya tiene matrícula en el periodo
     */
    public function crearDesdeInscripcion(
        Alumno $alumno,
        Periodo $periodo,
        Programa $programa,
        ?PlanVersion $planVersion = null,
        OrigenMatricula $origen = OrigenMatricula::PRIMERA_VEZ,
        ?User $usuario = null,
    ): MatriculaPeriodo {
        $usuario ??= auth()->user();

        return DB::transaction(function () use ($alumno, $periodo, $programa, $planVersion, $origen, $usuario): MatriculaPeriodo {
            // Verificar que no exista matrícula previa en el mismo periodo
            $existe = MatriculaPeriodo::where('alumno_id', $alumno->id)
                ->where('periodo_id', $periodo->id)
                ->exists();

            if ($existe) {
                throw new MatriculaException('El alumno ya tiene una matrícula en este periodo.');
            }

            // Baja definitiva o temporal vigente: bloquea nuevas matrículas (2.0-11)
            if ($this->bajas->bloqueaNuevaMatricula($alumno)) {
                throw new MatriculaException('El alumno tiene una baja registrada que impide nuevas matrículas.');
            }

            // Resolver plan_version si no se proporciona
            $planVersion ??= $programa->planVersiones()->vigente()->first();

            $matricula = MatriculaPeriodo::create([
                'alumno_id' => $alumno->id,
                'periodo_id' => $periodo->id,
                'programa_id' => $programa->id,
                'plan_version_id' => $planVersion?->id,
                'origen' => $origen->value,
                'fecha_matricula' => now(),
                'estatus' => EstatusMatriculaPeriodo::ACTIVA->value,
                'observaciones' => 'Matrícula generada automáticamente desde inscripción finalizada.',
            ]);

            $this->auditoria->registrar(
                $matricula,
                'matricula_creada',
                [],
                [
                    'alumno_id' => $alumno->id,
                    'periodo_id' => $periodo->id,
                    'programa_id' => $programa->id,
                    'plan_version_id' => $planVersion?->id,
                    'origen' => $origen->value,
                    'estatus' => $matricula->estatus,
                ],
                [],
                $usuario,
            );

            return $matricula;
        });
    }

    /**
     * Crea una matrícula manual (admin).
     *
     * @throws MatriculaException si fallan validaciones
     */
    public function crear(
        Alumno $alumno,
        Periodo $periodo,
        Programa $programa,
        ?PlanVersion $planVersion = null,
        OrigenMatricula $origen = OrigenMatricula::PRIMERA_VEZ,
        ?string $observaciones = null,
        ?User $usuario = null,
    ): MatriculaPeriodo {
        $usuario ??= auth()->user();

        if ($usuario !== null) {
            Gate::forUser($usuario)->authorize('matricula.create');
        } else {
            Gate::authorize('matricula.create');
        }

        return DB::transaction(function () use ($alumno, $periodo, $programa, $planVersion, $origen, $observaciones, $usuario): MatriculaPeriodo {
            // Validaciones
            $this->validarCreacion($alumno, $periodo, $programa);

            $planVersion ??= $programa->planVersiones()->vigente()->first();

            $matricula = MatriculaPeriodo::create([
                'alumno_id' => $alumno->id,
                'periodo_id' => $periodo->id,
                'programa_id' => $programa->id,
                'plan_version_id' => $planVersion?->id,
                'origen' => $origen->value,
                'fecha_matricula' => now(),
                'estatus' => EstatusMatriculaPeriodo::ACTIVA->value,
                'observaciones' => $observaciones,
            ]);

            $this->auditoria->registrar(
                $matricula,
                'matricula_creada',
                [],
                [
                    'alumno_id' => $alumno->id,
                    'periodo_id' => $periodo->id,
                    'programa_id' => $programa->id,
                    'plan_version_id' => $planVersion?->id,
                    'origen' => $origen->value,
                    'estatus' => $matricula->estatus,
                ],
                [],
                $usuario,
            );

            return $matricula;
        });
    }

    /**
     * Cambia el estatus de una matrícula.
     */
    public function cambiarEstatus(
        MatriculaPeriodo $matricula,
        EstatusMatriculaPeriodo $nuevoEstatus,
        ?string $observaciones = null,
        ?User $usuario = null,
    ): MatriculaPeriodo {
        $usuario ??= auth()->user();

        if ($usuario !== null) {
            Gate::forUser($usuario)->authorize('matricula.update', $matricula);
        } else {
            Gate::authorize('matricula.update', $matricula);
        }

        $estatusAnterior = $matricula->estatus;

        if ($estatusAnterior === $nuevoEstatus->value) {
            return $matricula;
        }

        // Validar transiciones permitidas
        $this->validarTransicion($estatusAnterior, $nuevoEstatus);

        return DB::transaction(function () use ($matricula, $estatusAnterior, $nuevoEstatus, $observaciones, $usuario): MatriculaPeriodo {
            $matricula->forceFill([
                'estatus' => $nuevoEstatus->value,
                'observaciones' => $observaciones ?? $matricula->observaciones,
            ])->save();

            $this->auditoria->registrar(
                $matricula,
                'estatus_cambiado',
                ['estatus' => $estatusAnterior],
                ['estatus' => $nuevoEstatus->value],
                $observaciones !== null ? ['motivo' => $observaciones] : [],
                $usuario,
            );

            return $matricula->refresh();
        });
    }

    /**
     * Obtiene la matrícula activa de un alumno en un periodo.
     */
    public function obtenerActiva(int $alumnoId, int $periodoId): ?MatriculaPeriodo
    {
        return MatriculaPeriodo::where('alumno_id', $alumnoId)
            ->where('periodo_id', $periodoId)
            ->where('estatus', EstatusMatriculaPeriodo::ACTIVA->value)
            ->first();
    }

    /**
     * Verifica si un alumno puede matricularse en un periodo.
     */
    public function puedeMatricularse(int $alumnoId, int $periodoId): bool
    {
        return !MatriculaPeriodo::where('alumno_id', $alumnoId)
            ->where('periodo_id', $periodoId)
            ->exists();
    }

    /**
     * Valida reglas básicas de creación.
     */
    private function validarCreacion(Alumno $alumno, Periodo $periodo, Programa $programa): void
    {
        if (! $periodo->activo) {
            throw new MatriculaException('El periodo está inactivo.');
        }

        if ($periodo->estaCerrado()) {
            throw new MatriculaException('El periodo está cerrado para inscripciones.');
        }

        if (! $programa->activo) {
            throw new MatriculaException('El programa está inactivo.');
        }

        if ($this->puedeMatricularse($alumno->id, $periodo->id) === false) {
            throw new MatriculaException('El alumno ya tiene una matrícula en este periodo.');
        }

        // Baja definitiva o temporal vigente: bloquea nuevas matrículas (2.0-11)
        if ($this->bajas->bloqueaNuevaMatricula($alumno)) {
            throw new MatriculaException('El alumno tiene una baja registrada que impide nuevas matrículas.');
        }
    }

    /**
     * Valida transiciones de estatus permitidas.
     */
    private function validarTransicion(EstatusMatriculaPeriodo $desde, EstatusMatriculaPeriodo $hasta): void
    {
        $transiciones = [
            EstatusMatriculaPeriodo::ACTIVA->value => [
                EstatusMatriculaPeriodo::BAJA_TEMPORAL,
                EstatusMatriculaPeriodo::BAJA_DEFINITIVA,
                EstatusMatriculaPeriodo::EGRESADO,
            ],
            EstatusMatriculaPeriodo::BAJA_TEMPORAL->value => [
                EstatusMatriculaPeriodo::ACTIVA,
                EstatusMatriculaPeriodo::BAJA_DEFINITIVA,
            ],
            EstatusMatriculaPeriodo::BAJA_DEFINITIVA->value => [
                EstatusMatriculaPeriodo::ACTIVA, // reactivación extraordinaria
            ],
            EstatusMatriculaPeriodo::EGRESADO->value => [], // terminal
        ];

        $permitidas = $transiciones[$desde->value] ?? [];

        if (! in_array($hasta, $permitidas, true)) {
            throw new MatriculaException(sprintf(
                'Transición de estatus no permitida: %s → %s',
                $desde->value,
                $hasta->value,
            ));
        }
    }
}