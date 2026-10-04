<?php

namespace App\Services;

use App\Exceptions\ExtraordinarioException;
use App\Models\Calificacion;
use App\Models\Evaluacion;
use App\Models\InscripcionMateria;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Servicio para gestión de evaluaciones extraordinarias (2.0-07, D-N4).
 *
 * Reglas (D-N4 resuelto):
 * - 1 oportunidad
 * - Solo desde REPROBADA
 * - Sin requisito adicional de calificación previa
 * - Sin porcentaje mínimo de asistencia
 * - Sin límite de materias
 * - Fechas flexibles
 * - Misma escala 0–100
 * - Misma aprobatoria 60
 * - Precisión 2 decimales
 * - HALF_UP
 * - Sustituye calificación_final
 * - Conserva trazabilidad del resultado ordinario
 * - No promedia
 * - Sin cobros
 * - Sin segunda oportunidad
 * - Sin modalidades adicionales
 */
class ExtraordinarioService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
        private readonly CalificacionService $calificacionService,
    ) {
    }

    /**
     * Inicia el proceso extraordinario para una inscripción.
     *
     * @throws ExtraordinarioException si no cumple requisitos
     */
    public function iniciar(
        InscripcionMateria $inscripcion,
        ?User $usuario = null,
    ): InscripcionMateria {
        $usuario ??= auth()->user();

        // Autorización: CONTROL_ESCOLAR, ADMIN
        $this->autorizar($usuario);

        $inscripcion->loadMissing(['calificaciones.evaluacion']);

        // Validaciones D-N4
        if ($inscripcion->estatus !== \App\Enums\EstatusInscripcionMateria::REPROBADA) {
            throw new \App\Exceptions\ExtraordinarioException(
                'Solo se puede solicitar extraordinario desde estatus REPROBADA.'
            );
        }

        if ($inscripcion->estatus === \App\Enums\EstatusInscripcionMateria::EXTRAORDINARIO) {
            throw new \App\Exceptions\ExtraordinarioException(
                'La inscripción ya está en proceso extraordinario.'
            );
        }

        // Verificar que no haya tenido extraordinario previo
        $tieneExtraordinarioPrevio = $inscripcion->calificaciones()
            ->whereHas('evaluacion', fn ($q) => $q->where('tipo', 'EXTRAORDINARIO'))
            ->exists();

        if ($tieneExtraordinarioPrevio) {
            throw new \App\Exceptions\ExtraordinarioException(
                'No se permite una segunda oportunidad extraordinaria (D-N4: 1 única oportunidad).'
            );
        }

        return DB::transaction(function () use ($inscripcion, $usuario): InscripcionMateria {
            // Guardar calificación_final ordinaria para trazabilidad
            $calificacionOrdinaria = $inscripcion->calificacion_final;

            // Cambiar estatus a EXTRAORDINARIO
            $inscripcion->forceFill([
                'estatus' => \App\Enums\EstatusInscripcionMateria::EXTRAORDINARIO,
            ])->save();

            $this->auditoria->registrar(
                $inscripcion,
                'extraordinario_iniciado',
                [
                    'calificacion_final_ordinaria' => $calificacionOrdinaria,
                    'estatus_anterior' => \App\Enums\EstatusInscripcionMateria::REPROBADA->value,
                ],
                [
                    'estatus' => \App\Enums\EstatusInscripcionMateria::EXTRAORDINARIO->value,
                ],
                [],
                $usuario,
            );

            return $inscripcion->refresh();
        });
    }

    /**
     * Captura la calificación del examen extraordinario.
     *
     * @throws ExtraordinarioException si fallan validaciones
     */
    public function capturarCalificacion(
        InscripcionMateria $inscripcion,
        float $valor,
        ?string $observacion = null,
        ?User $usuario = null,
    ): Calificacion {
        $usuario ??= auth()->user();

        // Autorización
        $this->autorizar($usuario);

        $inscripcion->loadMissing(['grupo']);

        // Validaciones
        if ($inscripcion->estatus !== \App\Enums\EstatusInscripcionMateria::EXTRAORDINARIO) {
            throw new \App\Exceptions\ExtraordinarioException(
                'La inscripción debe estar en estatus EXTRAORDINARIO para capturar calificación.'
            );
        }

        // Validar rango (misma escala 0–100, D-N4)
        if ($valor < 0 || $valor > 100) {
            throw new \App\Exceptions\ExtraordinarioException(
                'La calificación extraordinaria debe estar entre 0.00 y 100.00.'
            );
        }

        // Buscar o crear evaluación de tipo EXTRAORDINARIO para el grupo
        $evaluacion = Evaluacion::firstOrCreate(
            [
                'grupo_id' => $inscripcion->grupo_id,
                'tipo' => 'EXTRAORDINARIO',
                'nombre' => 'Examen Extraordinario',
            ],
            [
                'ponderacion' => 100,
                'fecha' => now()->format('Y-m-d'),
                'descripcion' => 'Examen extraordinario (D-N4: sustitución de calificación ordinaria)',
                'creada_por' => $usuario->id,
                'estatus' => 'PUBLICADA',
            ]
        );

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

            $this->auditoria->registrar(
                $calificacion,
                'calificacion_extraordinaria_capturada',
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

            // Consolidar inmediatamente (la extraordinaria sustituye la final)
            $this->consolidarExtraordinario($inscripcion, $usuario);

            return $calificacion;
        });
    }

    /**
     * Consolida la calificación extraordinaria (sustituye la final).
     *
     * D-N4: misma escala, misma aprobatoria (60), HALF_UP, 2 decimales.
     */
    private function consolidarExtraordinario(InscripcionMateria $inscripcion, User $usuario): InscripcionMateria
    {
        $inscripcion->loadMissing(['calificaciones.evaluacion']);

        // Buscar calificación extraordinaria
        $calificacionExtra = $inscripcion->calificaciones()
            ->whereHas('evaluacion', fn ($q) => $q->where('tipo', 'EXTRAORDINARIO'))
            ->first();

        if (!$calificacionExtra) {
            throw new \App\Exceptions\ExtraordinarioException(
                'No hay calificación extraordinaria para consolidar.'
            );
        }

        // La calificación extraordinaria sustituye la final directamente (D-N4: no promedia)
        $calificacionFinal = $this->redondear($calificacionExtra->valor);
        $nuevoEstatus = $calificacionFinal >= 60.00
            ? \App\Enums\EstatusInscripcionMateria::APROBADA
            : \App\Enums\EstatusInscripcionMateria::REPROBADA;

        $inscripcion->forceFill([
            'calificacion_final' => $calificacionFinal,
            'estatus' => $nuevoEstatus->value,
        ])->save();

        $this->auditoria->registrar(
            $inscripcion,
            'extraordinario_consolidado',
            ['calificacion_final' => $inscripcion->getOriginal('calificacion_final'), 'estatus' => $inscripcion->getOriginal('estatus')],
            [
                'calificacion_final' => $calificacionFinal,
                'estatus' => $nuevoEstatus->value,
                'calificacion_extraordinaria' => $calificacionExtra->valor,
            ],
            [],
            $usuario,
        );

        return $inscripcion->refresh();
    }

    /**
     * Redondeo HALF_UP a 2 decimales (configurable desde config/escolar.php).
     */
    private function redondear(float $valor): float
    {
        $decimales = config('escolar.calificaciones.decimales', 2);
        return floor($valor * pow(10, $decimales) + 0.5) / pow(10, $decimales);
    }

    /**
     * Autoriza acciones de extraordinario (CONTROL_ESCOLAR, ADMIN).
     */
    private function autorizar(?User $usuario = null): void
    {
        $usuario ??= auth()->user();

        if ($usuario->esAdmin() || $usuario->rol->value === 'CONTROL_ESCOLAR') {
            return;
        }

        abort(403, 'Solo CONTROL_ESCOLAR y ADMIN pueden gestionar extraordinarios.');
    }
}