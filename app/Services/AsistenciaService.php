<?php

namespace App\Services;

use App\Exceptions\AsistenciaException;
use App\Models\Asistencia;
use App\Models\Grupo;
use App\Models\InscripcionMateria;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Servicio para gestión de asistencia académica (2.0-07).
 *
 * Reglas:
 * - UNIQUE(inscripcion_materia_id, fecha)
 * - DOCENTE: solo sus grupos
 * - CONTROL_ESCOLAR: acceso operativo
 * - ADMIN: Gate::before
 * - Solo presente/ausente (boolean)
 */
class AsistenciaService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
    ) {
    }

    /**
     * Registra o actualiza la asistencia de una inscripción.
     *
     * @throws AsistenciaException si fallan validaciones
     */
    public function registrar(
        InscripcionMateria $inscripcion,
        \DateTimeInterface $fecha,
        bool $presente,
        ?User $usuario = null,
    ): Asistencia {
        $usuario ??= auth()->user();

        // Autorización: docente del grupo, ADMIN, CONTROL_ESCOLAR
        $this->autorizarRegistro($inscripcion->grupo, $usuario);

        // Validaciones
        $this->validarRegistro($inscripcion, $fecha);

        return DB::transaction(function () use ($inscripcion, $fecha, $presente, $usuario): Asistencia {
            $asistencia = Asistencia::updateOrCreate(
                [
                    'inscripcion_materia_id' => $inscripcion->id,
                    'fecha' => $fecha,
                ],
                [
                    'presente' => $presente,
                    'registrado_por' => $usuario->id,
                ]
            );

            $this->auditoria->registrar(
                $asistencia,
                $asistencia->wasRecentlyCreated ? 'asistencia_creada' : 'asistencia_actualizada',
                [],
                [
                    'inscripcion_materia_id' => $inscripcion->id,
                    'fecha' => $fecha->format('Y-m-d'),
                    'presente' => $presente,
                ],
                [],
                $usuario,
            );

            return $asistencia;
        });
    }

    /**
     * Registra asistencia masiva para un grupo en una fecha.
     *
     * @param  array<int, bool>  $asistencias  [inscripcion_materia_id => presente]
     * @return array<int, Asistencia>
     *
     * @throws AsistenciaException si fallan validaciones
     */
    public function registrarMasivo(
        Grupo $grupo,
        \DateTimeInterface $fecha,
        array $asistencias,
        ?User $usuario = null,
    ): array {
        $usuario ??= auth()->user();

        // Autorización: docente del grupo, ADMIN, CONTROL_ESCOLAR
        $this->autorizarRegistro($grupo, $usuario);

        // Validar fecha
        $this->validarFecha($fecha, $grupo);

        $inscripciones = InscripcionMateria::where('grupo_id', $grupo->id)
            ->where('estatus', \App\Enums\EstatusInscripcionMateria::CURSANDO)
            ->get()
            ->keyBy('id');

        $resultado = [];

        return DB::transaction(function () use ($grupo, $fecha, $asistencias, $inscripciones, $usuario, &$resultado) {
            foreach ($asistencias as $inscripcionId => $presente) {
                if (!$inscripciones->has($inscripcionId)) {
                    throw new \App\Exceptions\AsistenciaException(
                        "La inscripción {$inscripcionId} no pertenece a este grupo o no está cursando."
                    );
                }

                $inscripcion = $inscripciones->get($inscripcionId);

                $this->validarRegistro($inscripcion, $fecha);

                $asistencia = Asistencia::updateOrCreate(
                    [
                        'inscripcion_materia_id' => $inscripcionId,
                        'fecha' => $fecha,
                    ],
                    [
                        'presente' => $presente,
                        'registrado_por' => $usuario->id,
                    ]
                );

                $this->auditoria->registrar(
                    $asistencia,
                    $asistencia->wasRecentlyCreated ? 'asistencia_creada' : 'asistencia_actualizada',
                    [],
                    [
                        'inscripcion_materia_id' => $inscripcionId,
                        'fecha' => $fecha->format('Y-m-d'),
                        'presente' => $presente,
                    ],
                    [],
                    $usuario,
                );

                $resultado[$inscripcionId] = $asistencia;
            }

            return $resultado;
        });
    }

    /**
     * Obtiene la asistencia de una inscripción en una fecha.
     */
    public function obtener(InscripcionMateria $inscripcion, \DateTimeInterface $fecha): ?Asistencia
    {
        return Asistencia::where('inscripcion_materia_id', $inscripcion->id)
            ->where('fecha', $fecha)
            ->first();
    }

    /**
     * Obtiene todas las asistencias de una inscripción.
     */
    public function historial(InscripcionMateria $inscripcion): \Illuminate\Database\Eloquent\Collection
    {
        return Asistencia::where('inscripcion_materia_id', $inscripcion->id)
            ->orderBy('fecha', 'desc')
            ->get();
    }

    /**
     * Autoriza el registro de asistencia para un grupo.
     */
    private function autorizarRegistro(Grupo $grupo, ?User $usuario = null): void
    {
        $usuario ??= auth()->user();

        if ($usuario->esAdmin() || $usuario->rol->value === 'CONTROL_ESCOLAR') {
            return;
        }

        if ($usuario->rol->value === 'DOCENTE') {
            if (!$usuario->docente || $grupo->docente_id !== $usuario->docente->id) {
                abort(403, 'Solo el docente asignado al grupo puede registrar asistencia.');
            }
            return;
        }

        abort(403, 'No tiene permisos para registrar asistencia.');
    }

    /**
     * Valida el registro de asistencia individual.
     */
    private function validarRegistro(InscripcionMateria $inscripcion, \DateTimeInterface $fecha): void
    {
        $this->validarFecha($fecha, $inscripcion->grupo);

        // La inscripción debe estar cursando
        if ($inscripcion->estatus !== \App\Enums\EstatusInscripcionMateria::CURSANDO) {
            throw new \App\Exceptions\AsistenciaException('Solo se puede registrar asistencia en inscripciones con estatus CURSANDO.');
        }
    }

    /**
     * Valida que la fecha sea coherente con el grupo.
     */
    private function validarFecha(\DateTimeInterface $fecha, Grupo $grupo): void
    {
        $periodo = $grupo->periodo;

        if ($fecha < $periodo->fecha_inicio || $fecha > $periodo->fecha_fin) {
            throw new \App\Exceptions\AsistenciaException(
                "La fecha debe estar dentro del periodo académico ({$periodo->fecha_inicio->format('d/m/Y')} - {$periodo->fecha_fin->format('d/m/Y')})."
            );
        }
    }
}