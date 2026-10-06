<?php

namespace App\Services;

use App\Exceptions\ActaException;
use App\Models\Acta;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\InscripcionMateria;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Servicio para gestión de actas de calificaciones (2.0-08).
 *
 * Four-eyes: DOCENTE captura, CONTROL_ESCOLAR cierra.
 * Folio: ACT-YYYY-######## (consecutivo con lockForUpdate).
 */
class ActaService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
        private readonly ConsecutivoService $consecutivoService,
    ) {
    }

    /**
     * Crea un acta ABIERTA para un grupo (sin folio hasta el cierre).
     *
     * @throws ActaException si ya existe un acta para el grupo/periodo
     */
    public function crear(
        Grupo $grupo,
        ?User $usuario = null,
    ): Acta {
        $usuario ??= auth()->user();

        Gate::authorize('acta.crear');

        // Portal docente (2.0-15): el DOCENTE sólo crea actas de sus grupos
        // (mismo criterio de los servicios de evaluaciones/calificaciones/asistencia).
        if ($usuario->rol->value === 'DOCENTE') {
            if (!$usuario->docente || $grupo->docente_id !== $usuario->docente->id) {
                abort(403, 'Solo el docente asignado al grupo puede crear su acta.');
            }
        }

        // Validar coherencia grupo/periodo
        if ($grupo->periodo_id !== $grupo->periodo->id) {
            throw new ActaException('El grupo no pertenece al periodo especificado.');
        }

        // Verificar que no exista ya un acta para este grupo/periodo
        $existe = Acta::where('grupo_id', $grupo->id)
            ->where('periodo_id', $grupo->periodo_id)
            ->exists();

        if ($existe) {
            throw new ActaException('Ya existe un acta para este grupo en este periodo.');
        }

        return DB::transaction(function () use ($grupo, $usuario): Acta {
            $acta = Acta::create([
                'grupo_id' => $grupo->id,
                'periodo_id' => $grupo->periodo_id,
                'folio' => null,
                'estatus' => 'ABIERTA',
            ]);

            $this->auditoria->registrar(
                $acta,
                'acta_creada',
                [],
                [
                    'grupo_id' => $grupo->id,
                    'periodo_id' => $grupo->periodo_id,
                    'estatus' => 'ABIERTA',
                ],
                [],
                auth()->user(),
            );

            return $acta;
        });
    }

    /**
     * Cierra un acta (four-eyes: CONTROL_ESCOLAR).
     *
     * Validaciones previas:
     * - Ponderación total = 100%
     * - Todas las evaluaciones tienen calificaciones
     * - Calificaciones finales consolidadas
     * - Grupo/periodo coherentes
     *
     * @throws ActaException si fallan validaciones
     */
    public function cerrar(Acta $acta, ?User $usuario = null): Acta
    {
        $usuario ??= auth()->user();

        Gate::authorize('acta.cerrar', $acta->grupo);

        if ($acta->esCerrada()) {
            throw new \App\Exceptions\ActaException('El acta ya está cerrada.');
        }

        $grupo = $acta->grupo;

        return DB::transaction(function () use ($acta, $usuario): \App\Models\Acta {
            // Validaciones previas al cierre
            $this->validarPreCierre($acta->grupo);

            // Generar folio con consecutivo (D-N7: ACT-YYYY-########)
            $folio = $this->consecutivoService->generar(\App\Enums\TipoConsecutivo::ACTA_CALIFICACIONES);

            $acta->forceFill([
                'folio' => $folio,
                'estatus' => 'CERRADA',
                'cerrada_por' => auth()->id(),
                'cerrada_at' => now(),
            ])->save();

            // Evaluaciones del grupo quedan inmutables (sin estado a modificar)
            // La inmutabilidad se controla vía Acta::esCerrada() en servicios

            $this->auditoria->registrar(
                $acta,
                'acta_cerrada',
                ['estatus' => 'ABIERTA', 'folio' => null],
                ['estatus' => 'CERRADA', 'folio' => $acta->folio],
                [],
                auth()->user(),
            );

            return $acta->refresh();
        });
    }

    /**
     * Valida que se puedan cerrar las evaluaciones del grupo.
     */
    private function validarPreCierre(Grupo $grupo): void
    {
        // 1. Ponderación total = 100%
        $ponderacionTotal = \App\Models\Evaluacion::ponderacionTotalGrupo($grupo->id);
        if ($ponderacionTotal !== 100) {
            throw new \App\Exceptions\ActaException(
                "La ponderación total del grupo es {$ponderacionTotal}%. Debe ser exactamente 100% para cerrar el acta."
            );
        }

        // 2. Todas las evaluaciones deben tener calificaciones para alumnos CURSANDO
        $inscripciones = \App\Models\InscripcionMateria::where('grupo_id', $grupo->id)
            ->where('estatus', \App\Enums\EstatusInscripcionMateria::CURSANDO)
            ->with('calificaciones')
            ->get();

        $evaluaciones = \App\Models\Evaluacion::where('grupo_id', $grupo->id)->get();

        foreach ($inscripciones as $inscripcion) {
            foreach ($evaluaciones as $evaluacion) {
                $tieneCalificacion = \App\Models\Calificacion::where('inscripcion_materia_id', $inscripcion->id)
                    ->where('evaluacion_id', $evaluacion->id)
                    ->exists();

                if (!$tieneCalificacion) {
                    throw new \App\Exceptions\ActaException(
                        "El alumno {$inscripcion->matriculaPeriodo->alumno->nombre_completo} no tiene calificación en la evaluación {$evaluacion->nombre}."
                    );
                }
            }
        }
    }
}