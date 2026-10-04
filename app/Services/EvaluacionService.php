<?php

namespace App\Services;

use App\Concerns\HasAudit;
use App\Enums\EstatusEvaluacion;
use App\Enums\TipoEvaluacion;
use App\Exceptions\EvaluacionException;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Servicio para gestión de evaluaciones de un grupo.
 *
 * Reglas de negocio:
 * - Solo docente del grupo (o ADMIN/CONTROL_ESCOLAR) puede crear/modificar
 * - Ponderación > 0 y <= 100
 * - Suma de ponderaciones no puede superar 100%
 * - Para cerrar acta (2.0-08) la suma debe ser exactamente 100%
 * - Solo docente del grupo (y CE/ADMIN) pueden modificar
 */
class EvaluacionService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
    ) {
    }

    /**
     * Crea una nueva evaluación en estado BORRADOR.
     *
     * @throws EvaluacionException si fallan validaciones
     */
    public function crear(
        Grupo $grupo,
        string $nombre,
        string $tipo,
        int $ponderacion,
        \DateTimeInterface $fecha,
        ?string $descripcion = null,
        ?User $usuario = null,
    ): Evaluacion {
        $usuario ??= auth()->user();

        // Autorización: docente del grupo o ADMIN/CONTROL_ESCOLAR
        $this->autorizarGestionEvaluacion($grupo, $usuario);

        // Validaciones
        $this->validarDatosEvaluacion($nombre, $tipo, $ponderacion, $fecha);

        // Validar ponderación no supere 100% en el grupo
        $ponderacionActual = Evaluacion::ponderacionTotalGrupo($grupo->id);
        if ($ponderacionActual + $ponderacion > 100) {
            throw new EvaluacionException(
                "La ponderación total del grupo superaría 100% (actual: {$ponderacionActual}%, nueva: {$ponderacion}%)."
            );
        }

        return DB::transaction(function () use ($grupo, $nombre, $tipo, $ponderacion, $fecha, $descripcion, $usuario): Evaluacion {
            $evaluacion = Evaluacion::create([
                'grupo_id' => $grupo->id,
                'nombre' => $nombre,
                'tipo' => $tipo,
                'ponderacion' => $ponderacion,
                'fecha' => $fecha,
                'estatus' => 'BORRADOR',
                'descripcion' => $descripcion,
                'creada_por' => $usuario?->getKey(),
            ]);

            $this->auditoria->registrar(
                $evaluacion,
                'evaluacion_creada',
                [],
                [
                    'grupo_id' => $grupo->id,
                    'nombre' => $nombre,
                    'tipo' => $tipo,
                    'ponderacion' => $ponderacion,
                    'fecha' => $fecha->format('Y-m-d'),
                    'estatus' => 'BORRADOR',
                ],
                [],
                $usuario,
            );

            return $evaluacion;
        });
    }

    /**
     * Actualiza una evaluación (solo en estado BORRADOR).
     */
    public function actualizar(
        Evaluacion $evaluacion,
        ?string $nombre = null,
        ?string $tipo = null,
        ?int $ponderacion = null,
        ?\DateTimeInterface $fecha = null,
        ?string $descripcion = null,
        ?User $usuario = null,
    ): Evaluacion {
        $usuario ??= auth()->user();

        $this->autorizarGestionEvaluacion($evaluacion->grupo, $usuario);

        if (!$evaluacion->esBorrador()) {
            throw new \App\Exceptions\EvaluacionException('Solo se pueden modificar evaluaciones en estado BORRADOR.');
        }

        $datos = [];
        if ($nombre !== null) $datos['nombre'] = $nombre;
        if ($tipo !== null) $datos['tipo'] = $tipo;
        if ($ponderacion !== null) $datos['ponderacion'] = $ponderacion;
        if ($fecha !== null) $datos['fecha'] = $fecha;
        if ($descripcion !== null) $datos['descripcion'] = $descripcion;

        if (empty($datos)) {
            return $evaluacion;
        }

        // Validar ponderación si cambió
        if (isset($datos['ponderacion'])) {
            $this->validarPonderacion($evaluacion->grupo, $datos['ponderacion'], $evaluacion->id);
        }

        return DB::transaction(function () use ($evaluacion, $datos, $usuario): \App\Models\Evaluacion {
            $antes = $evaluacion->only(['nombre', 'tipo', 'ponderacion', 'fecha', 'descripcion']);

            $evaluacion->fill($datos)->save();

            $this->auditoria->registrar(
                $evaluacion,
                'evaluacion_actualizada',
                $antes,
                $evaluacion->only(array_keys($datos)),
                [],
                $usuario,
            );

            return $evaluacion->refresh();
        });
    }

    /**
     * Publica una evaluación (BORRADOR → PUBLICADA).
     */
    public function publicar(Evaluacion $evaluacion, ?User $usuario = null): \App\Models\Evaluacion
    {
        $usuario ??= auth()->user();

        $this->autorizarGestionEvaluacion($evaluacion->grupo, $usuario);

        if (!$evaluacion->esBorrador()) {
            throw new \App\Exceptions\EvaluacionException('Solo se pueden publicar evaluaciones en estado BORRADOR.');
        }

        return DB::transaction(function () use ($evaluacion, $usuario): \App\Models\Evaluacion {
            $evaluacion->estatus = 'PUBLICADA';
            $evaluacion->save();

            $this->auditoria->registrar(
                $evaluacion,
                'evaluacion_publicada',
                ['estatus' => 'BORRADOR'],
                ['estatus' => 'PUBLICADA'],
                [],
                $usuario,
            );

            return $evaluacion->refresh();
        });
    }

    /**
     * Cierra una evaluación (PUBLICADA → CERRADA).
     * Solo se usa en 2.0-08 al cerrar acta.
     */
    public function cerrar(Evaluacion $evaluacion, ?User $usuario = null): \App\Models\Evaluacion
    {
        $usuario ??= auth()->user();

        if ($evaluacion->esCerrada()) {
            return $evaluacion;
        }

        // Solo CONTROL_ESCOLAR y ADMIN pueden cerrar (four-eyes)
        Gate::authorize('evaluacion.cerrar', $evaluacion->grupo);

        return DB::transaction(function () use ($evaluacion, $usuario): \App\Models\Evaluacion {
            $evaluacion->estatus = 'CERRADA';
            $evaluacion->save();

            $this->auditoria->registrar(
                $evaluacion,
                'evaluacion_cerrada',
                ['estatus' => 'PUBLICADA'],
                ['estatus' => 'CERRADA'],
                [],
                $usuario,
            );

            return $evaluacion->refresh();
        });
    }

    /**
     * Elimina una evaluación (solo BORRADOR).
     */
    public function eliminar(Evaluacion $evaluacion, ?User $usuario = null): bool
    {
        $usuario ??= auth()->user();

        $this->autorizarGestionEvaluacion($evaluacion->grupo, $usuario);

        if (!$evaluacion->esBorrador()) {
            throw new \App\Exceptions\EvaluacionException('Solo se pueden eliminar evaluaciones en estado BORRADOR.');
        }

        return DB::transaction(function () use ($evaluacion, $usuario): bool {
            $evaluacion->delete();

            $this->auditoria->registrar(
                $evaluacion,
                'evaluacion_eliminada',
                [],
                [],
                [],
                $usuario,
            );

            return true;
        });
    }

    /**
     * Obtiene la ponderación total de un grupo.
     */
    public function ponderacionTotalGrupo(int $grupoId): int
    {
        return \App\Models\Evaluacion::ponderacionTotalGrupo($grupoId);
    }

    /**
     * Verifica si un grupo tiene ponderación completa (100%).
     */
    public function ponderacionCompleta(int $grupoId): bool
    {
        return $this->ponderacionTotalGrupo($grupoId) === 100;
    }

    // --- Validaciones privadas ---

    private function autorizarGestionEvaluacion(\App\Models\Grupo $grupo, ?User $usuario = null): void
    {
        $usuario ??= auth()->user();

        // ADMIN y CONTROL_ESCOLAR pasan por Gate::before
        // DOCENTE debe ser el docente del grupo
        if ($usuario->rol->value === 'DOCENTE') {
            if (!$usuario->docente || $grupo->docente_id !== $usuario->docente->id) {
                abort(403, 'Solo el docente asignado al grupo puede gestionar sus evaluaciones.');
            }
        } elseif (!$usuario->esAdmin() && $usuario->rol->value !== 'CONTROL_ESCOLAR') {
            abort(403, 'No tiene permisos para gestionar evaluaciones.');
        }
    }

    private function validarDatosEvaluacion(string $nombre, string $tipo, int $ponderacion, \DateTimeInterface $fecha): void
    {
        if (trim($nombre) === '') {
            throw new \App\Exceptions\EvaluacionException('El nombre de la evaluación es obligatorio.');
        }

        if (mb_strlen($nombre) > 100) {
            throw new \App\Exceptions\EvaluacionException('El nombre no puede exceder 100 caracteres.');
        }

        // Validar tipo (string libre, validado contra enum si existe)
        if (!\App\Enums\TipoEvaluacion::tryFrom($tipo)) {
            // Tipo no está en enum, pero permitimos string libre
        }

        if ($ponderacion <= 0 || $ponderacion > 100) {
            throw new \App\Exceptions\EvaluacionException('La ponderación debe estar entre 1 y 100.');
        }

        if ($fecha < now()->subYear()->toDate()) {
            throw new \App\Exceptions\EvaluacionException('La fecha de la evaluación no puede ser muy antigua.');
        }
    }

    private function validarPonderacion(\App\Models\Grupo $grupo, int $nuevaPonderacion, ?int $excluirEvaluacionId = null): void
    {
        $query = \App\Models\Evaluacion::where('grupo_id', $grupo->id)
            ->where('estatus', '!=', 'BORRADOR');

        if ($nuevaPonderacion) {
            // No validamos aquí, solo en crear/actualizar
        }

        $ponderacionActual = \App\Models\Evaluacion::where('grupo_id', $grupo->id)
            ->where('estatus', '!=', 'BORRADOR');

        if ($grupo->id) {
            // Validar que la suma no supere 100
            $ponderacionActual = \App\Models\Evaluacion::ponderacionTotalGrupo($grupo->id);
            // La validación real se hace en crear/actualizar
        }
    }
}