<?php

namespace App\Services;

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
 * - Sin máquina de estados formal; la inmutabilidad la controla el ACTA (2.0-08)
 */
class EvaluacionService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
    ) {
    }

    /**
     * Crea una nueva evaluación.
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
                ],
                [],
                $usuario,
            );

            return $evaluacion;
        });
    }

    /**
     * Actualiza una evaluación.
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

        return DB::transaction(function () use ($evaluacion, $datos, $usuario): Evaluacion {
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
     * Elimina una evaluación.
     */
    public function eliminar(Evaluacion $evaluacion, ?User $usuario = null): bool
    {
        $usuario ??= auth()->user();

        $this->autorizarGestionEvaluacion($evaluacion->grupo, $usuario);

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

    private function validarPonderacion(Grupo $grupo, int $nuevaPonderacion, ?int $excluirEvaluacionId = null): void
    {
        $ponderacionActual = \App\Models\Evaluacion::where('grupo_id', $grupo->id)
            ->when($excluirEvaluacionId, fn ($q) => $q->where('id', '!=', $excluirEvaluacionId))
            ->sum('ponderacion');

        if ($ponderacionActual + $nuevaPonderacion > 100) {
            throw new EvaluacionException(
                "La ponderación total del grupo superaría 100% (actual: {$ponderacionActual}%, nueva: {$nuevaPonderacion}%)."
            );
        }
    }

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

        // Validar tipo (string libre, solo se rechaza string vacío)
        if (!\App\Enums\TipoEvaluacion::esValido($tipo)) {
            throw new \App\Exceptions\EvaluacionException('El tipo de evaluación no puede estar vacío.');
        }

        if ($ponderacion <= 0 || $ponderacion > 100) {
            throw new \App\Exceptions\EvaluacionException('La ponderación debe estar entre 1 y 100.');
        }

        if ($fecha < now()->subYear()->toDate()) {
            throw new \App\Exceptions\EvaluacionException('La fecha de la evaluación no puede ser muy antigua.');
        }
    }
}