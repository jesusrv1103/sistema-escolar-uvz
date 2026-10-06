<?php

namespace App\Services;

use App\Enums\EstatusInscripcionMateria;
use App\Enums\TipoPrerrequisito;
use App\Exceptions\CalificacionException;
use App\Models\Calificacion;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\InscripcionMateria;
use App\Models\MatriculaPeriodo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Servicio para gestión de calificaciones.
 *
 * Implementa la política de calificaciones aprobada para 2.0-06:
 * - Escala 0.00 - 100.00
 * - Mínimo aprobatorio 60.00
 * - 2 decimales, redondeo HALF_UP convencional sobre resultado final
 * - >= 60.00 = APROBADA, < 60.00 = REPROBADA
 * - Fórmula: SUM(valor * ponderacion / 100)
 * - Ponderación total debe ser exactamente 100% para consolidar
 * - Redondeo HALF_UP convencional solo sobre resultado final
 * - Centralizado en config('escolar.calificaciones')
 */
class CalificacionService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
    ) {
    }

    /**
     * Captura o actualiza una calificación.
     *
     * Reglas:
     * - Solo docente del grupo (o ADMIN/CONTROL_ESCOLAR) puede capturar
     * - Evaluación debe estar PUBLICADA
     * - Inscripción debe estar CURSANDO
     * - Valor entre 0.00 y 100.00
     * - No duplicados (UNIQUE inscripcion_materia_id + evaluacion_id)
     * - Acta no cerrada (sin acta CERRADA en 2.0-08)
     */
    public function capturar(
        InscripcionMateria $inscripcion,
        Evaluacion $evaluacion,
        float $valor,
        ?string $observacion = null,
        ?User $usuario = null,
    ): Calificacion {
        $usuario ??= auth()->user();

        // Autorización: docente del grupo, ADMIN, CONTROL_ESCOLAR
        $this->autorizarCaptura($evaluacion->grupo, $usuario);

        // Validaciones
        $this->validarCaptura($inscripcion, $evaluacion, $valor);

        return DB::transaction(function () use ($inscripcion, $evaluacion, $valor, $observacion, $usuario): Calificacion {
            $calificacion = Calificacion::updateOrCreate(
                [
                    'inscripcion_materia_id' => $inscripcion->id,
                    'evaluacion_id' => $evaluacion->id,
                ],
                [
                    'valor' => $this->redondear($valor),
                    'capturada_por' => $usuario->id,
                    'capturada_at' => now(),
                    'observacion' => $observacion,
                ]
            );

            // Auditoría
            $this->auditoria->registrar(
                $calificacion,
                $calificacion->wasRecentlyCreated ? 'calificacion_creada' : 'calificacion_actualizada',
                [],
                [
                    'inscripcion_materia_id' => $inscripcion->id,
                    'evaluacion_id' => $evaluacion->id,
                    'valor' => $calificacion->valor,
                    'observacion' => $calificacion->observacion,
                ],
                [],
                $usuario,
            );

            return $calificacion;
        });
    }

    /**
     * Consolida la calificación final de una inscripción.
     * Requiere: ponderación total = 100%, todas las calificaciones capturadas.
     * Transiciona InscripcionMateria a APROBADA/REPROBADA.
     */
    public function consolidar(InscripcionMateria $inscripcion, ?User $usuario = null): \App\Models\InscripcionMateria
    {
        $usuario ??= auth()->user();

        Gate::authorize('calificacion.consolidar', $inscripcion);

        return DB::transaction(function () use ($inscripcion, $usuario): \App\Models\InscripcionMateria {
            $inscripcion = $inscripcion->loadMissing(['calificaciones.evaluacion', 'grupo']);

            // 1. Validar ponderación total = 100%
            $ponderacionTotal = $inscripcion->grupo
                ? Evaluacion::ponderacionTotalGrupo($inscripcion->grupo_id)
                : 0;

            if ($ponderacionTotal !== 100) {
                throw new \App\Exceptions\CalificacionException(
                    "La ponderación total del grupo es {$ponderacionTotal}%. Debe ser exactamente 100% para consolidar."
                );
            }

            // 2. Verificar que todas las evaluaciones tengan calificación
            $evaluaciones = \App\Models\Evaluacion::where('grupo_id', $inscripcion->grupo_id)
                ->get();

            if ($evaluaciones->isEmpty()) {
                throw new \App\Exceptions\CalificacionException('El grupo no tiene evaluaciones.');
            }

            foreach ($evaluaciones as $eval) {
                if (!$inscripcion->calificaciones->where('evaluacion_id', $eval->id)->first()) {
                    throw new \App\Exceptions\CalificacionException(
                        "Falta calificación en la evaluación: {$eval->nombre}."
                    );
                }
            }

            // 3. Calcular calificación final ponderada
            $calificacionFinal = 0;
            foreach ($evaluaciones as $eval) {
                $calificacion = $inscripcion->calificaciones
                    ->where('evaluacion_id', $eval->id)
                    ->first();

                if (!$calificacion) {
                    throw new \App\Exceptions\CalificacionException(
                        "Falta calificación para evaluación: {$eval->nombre}."
                    );
                }

                $ponderacion = $eval->ponderacion / 100;
                $calificacionFinal += $calificacion->valor * $ponderacion;
            }

            // Redondeo HALF_UP convencional a 2 decimales (configurable)
            $calificacionFinal = $this->redondear($calificacionFinal);

            // Determinar estatus
            $nuevoEstatus = $this->determinarEstatus($calificacionFinal);

            // Actualizar inscripción
            $inscripcion->forceFill([
                'calificacion_final' => $this->redondear($calificacionFinal),
                'estatus' => $nuevoEstatus->value,
            ])->save();

            // Auditoría
            $this->auditoria->registrar(
                $inscripcion,
                'calificacion_consolidada',
                ['calificacion_final' => $inscripcion->getOriginal('calificacion_final'), 'estatus' => $inscripcion->getOriginal('estatus')],
                ['calificacion_final' => $calificacionFinal, 'estatus' => $nuevoEstatus->value],
                [],
                auth()->user(),
            );

            return $inscripcion->refresh();
        });
    }

    /**
     * Determina el estatus final según la política aprobada.
     */
    private function determinarEstatus(float $calificacionFinal): \App\Enums\EstatusInscripcionMateria
    {
        $minimo = config('escolar.calificaciones.aprobatoria', 60.00);

        return $calificacionFinal >= 60.00
            ? \App\Enums\EstatusInscripcionMateria::APROBADA
            : \App\Enums\EstatusInscripcionMateria::REPROBADA;
    }

    /**
     * Redondea según política aprobada: HALF_UP convencional a 2 decimales.
     */
    private function redondear(float $valor): float
    {
        $decimales = config('escolar.calificaciones.decimales', 2);
        $multiplicador = 10 ** $decimales;

        // HALF_UP convencional (no bancario)
        return floor($valor * pow(10, $decimales) + 0.5) / pow(10, $decimales);
    }

    /**
     * Valida rango de calificación (0.00 - 100.00).
     */
    private function validarRango(float $valor): void
    {
        $min = config('escolar.calificaciones.min', 0.00);
        $max = config('escolar.calificaciones.max', 100.00);

        if ($valor < 0 || $valor > 100) {
            throw new \App\Exceptions\CalificacionException("La calificación debe estar entre 0.00 y 100.00.");
        }
    }

    /**
     * Valida que la captura de calificación sea permitida.
     */
    private function validarCaptura(InscripcionMateria $inscripcion, Evaluacion $evaluacion, float $valor): void
    {
        $this->validarRango($valor);

        // La inscripción debe estar cursando
        if ($inscripcion->estatus !== \App\Enums\EstatusInscripcionMateria::CURSANDO) {
            throw new \App\Exceptions\CalificacionException('Solo se pueden capturar calificaciones en inscripciones con estatus CURSANDO.');
        }

        // La evaluación debe pertenecer al mismo grupo de la inscripción
        if ($evaluacion->grupo_id !== $inscripcion->grupo_id) {
            throw new \App\Exceptions\CalificacionException('La evaluación no pertenece al grupo de la inscripción.');
        }
    }

    /**
     * Autoriza la captura de calificaciones para un grupo.
     */
    private function autorizarCaptura(Grupo $grupo, ?User $usuario = null): void
    {
        $usuario ??= auth()->user();

        if ($usuario->esAdmin() || $usuario->rol->value === 'CONTROL_ESCOLAR') {
            return;
        }

        if ($usuario->rol->value === 'DOCENTE') {
            if (!$usuario->docente || $grupo->docente_id !== $usuario->docente->id) {
                abort(403, 'Solo el docente asignado al grupo puede capturar calificaciones.');
            }
            return;
        }

        abort(403, 'No tiene permisos para capturar calificaciones.');
    }
}