<?php

namespace App\Services;

use App\Enums\EstatusServicioSocial;
use App\Exceptions\ServicioSocialInvalidaException;
use App\Models\Alumno;
use App\Models\ServicioSocial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Servicio único de Servicio Social (2.0-13).
 *
 * Reglas aprobadas:
 *
 * - Un solo registro por alumno; crear nunca valida (EN_PROCESO, avance 0).
 * - El avance es un entero 0–100 que se almacena pero **no** auto-valida:
 *   no existe transición automática hacia VALIDADO.
 * - VALIDADO exige validación explícita (CONTROL_ESCOLAR / ADMIN) y una vez
 *   validado el registro es inmutable: ni edición de datos ni nuevo avance.
 * - Sin horas mínimas ni porcentaje mínimo: no hay fuente que los defina.
 *
 * Toda escritura ocurre dentro de una transacción con auditoría y la
 * autorización (`servicio_social.*`) es server-side.
 */
class ServicioSocialService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
    ) {
    }

    /**
     * Crea el registro del alumno en EN_PROCESO (nunca validado).
     *
     * @param array<string, mixed> $datos actividad, dependencia, fecha_inicio?, fecha_fin?
     *
     * @throws ServicioSocialInvalidaException si ya existe o los datos son inconsistentes
     */
    public function crear(Alumno $alumno, array $datos, ?User $usuario = null): ServicioSocial
    {
        $usuario ??= auth()->user();
        $this->autorizar('servicio_social.crear', null, $usuario);

        return DB::transaction(function () use ($alumno, $datos, $usuario): ServicioSocial {
            if (ServicioSocial::where('alumno_id', $alumno->getKey())->exists()) {
                throw new ServicioSocialInvalidaException('El alumno ya tiene un registro de Servicio Social.');
            }

            $this->validarDatosBasicos($datos);

            $servicio = ServicioSocial::create([
                'alumno_id' => $alumno->getKey(),
                'actividad' => trim((string) $datos['actividad']),
                'dependencia' => trim((string) $datos['dependencia']),
                'fecha_inicio' => $datos['fecha_inicio'] ?? null,
                'fecha_fin' => $datos['fecha_fin'] ?? null,
                'avance' => 0,
                'estatus' => EstatusServicioSocial::EN_PROCESO,
            ]);

            $this->auditoria->registrar(
                $servicio,
                'servicio_social_creado',
                [],
                ['estatus' => EstatusServicioSocial::EN_PROCESO->value],
                ['actividad' => $servicio->actividad, 'dependencia' => $servicio->dependencia],
                $usuario,
            );

            return $servicio;
        });
    }

    /**
     * Actualiza los datos básicos de un registro aún no validado.
     *
     * @param array<string, mixed> $datos
     *
     * @throws ServicioSocialInvalidaException si el registro ya fue validado
     */
    public function actualizar(ServicioSocial $servicio, array $datos, ?User $usuario = null): ServicioSocial
    {
        $usuario ??= auth()->user();
        $this->autorizar('servicio_social.actualizar', $servicio, $usuario);

        if ($servicio->esValidado()) {
            throw new ServicioSocialInvalidaException('El Servicio Social ya fue validado: el registro es inmutable.');
        }

        $this->validarDatosBasicos($datos);

        return DB::transaction(function () use ($servicio, $datos, $usuario): ServicioSocial {
            $antes = ['estatus' => $servicio->estatus->value];

            $servicio->update([
                'actividad' => trim((string) $datos['actividad']),
                'dependencia' => trim((string) $datos['dependencia']),
                'fecha_inicio' => $datos['fecha_inicio'] ?? null,
                'fecha_fin' => $datos['fecha_fin'] ?? null,
            ]);

            $this->auditoria->registrar(
                $servicio,
                'servicio_social_actualizado',
                $antes,
                ['estatus' => $servicio->estatus->value],
                ['actividad' => $servicio->actividad],
                $usuario,
            );

            return $servicio->refresh();
        });
    }

    /**
     * Registra el avance (0–100). No auto-valida y no puede tocarse tras VALIDADO.
     *
     * @throws ServicioSocialInvalidaException si el avance está fuera de rango o el registro ya fue validado
     */
    public function registrarAvance(ServicioSocial $servicio, int $avance, ?User $usuario = null): ServicioSocial
    {
        $usuario ??= auth()->user();
        $this->autorizar('servicio_social.actualizar', $servicio, $usuario);

        if ($servicio->esValidado()) {
            throw new ServicioSocialInvalidaException('El Servicio Social ya fue validado: el avance ya no puede modificarse.');
        }

        if ($avance < 0 || $avance > 100) {
            throw new ServicioSocialInvalidaException('El avance debe estar entre 0 y 100.');
        }

        return DB::transaction(function () use ($servicio, $avance, $usuario): ServicioSocial {
            $antes = ['avance' => $servicio->avance];

            $servicio->update(['avance' => $avance]);

            $this->auditoria->registrar(
                $servicio,
                'servicio_social_avance',
                $antes,
                ['avance' => $avance],
                [],
                $usuario,
            );

            return $servicio->refresh();
        });
    }

    /**
     * Valida explícitamente el proceso (única vía hacia VALIDADO).
     *
     * @throws ServicioSocialInvalidaException si ya estaba validado
     */
    public function validar(ServicioSocial $servicio, ?User $usuario = null): ServicioSocial
    {
        $usuario ??= auth()->user();
        $this->autorizar('servicio_social.validar', $servicio, $usuario);

        if ($servicio->esValidado()) {
            throw new ServicioSocialInvalidaException('El Servicio Social ya fue validado.');
        }

        return DB::transaction(function () use ($servicio, $usuario): ServicioSocial {
            $antes = ['estatus' => $servicio->estatus->value];

            $servicio->update([
                'estatus' => EstatusServicioSocial::VALIDADO,
                'validado_por' => $usuario?->getKey(),
                'validado_at' => now(),
            ]);

            $this->auditoria->registrar(
                $servicio,
                'servicio_social_validado',
                $antes,
                ['estatus' => EstatusServicioSocial::VALIDADO->value],
                ['avance' => $servicio->avance],
                $usuario,
            );

            return $servicio->refresh();
        });
    }

    /**
     * @param array<string, mixed> $datos
     *
     * @throws ServicioSocialInvalidaException
     */
    private function validarDatosBasicos(array $datos): void
    {
        $actividad = trim((string) ($datos['actividad'] ?? ''));
        $dependencia = trim((string) ($datos['dependencia'] ?? ''));

        if ($actividad === '' || $dependencia === '') {
            throw new ServicioSocialInvalidaException('La actividad y la dependencia son obligatorias.');
        }

        $inicio = $datos['fecha_inicio'] ?? null;
        $fin = $datos['fecha_fin'] ?? null;

        if ($inicio !== null && $fin !== null && strtotime((string) $fin) < strtotime((string) $inicio)) {
            throw new ServicioSocialInvalidaException('La fecha final no puede ser anterior a la fecha inicial.');
        }
    }

    private function autorizar(string $capacidad, ?ServicioSocial $servicio, ?User $usuario): void
    {
        if ($usuario !== null) {
            $servicio === null
                ? Gate::forUser($usuario)->authorize($capacidad)
                : Gate::forUser($usuario)->authorize($capacidad, $servicio);

            return;
        }

        $servicio === null
            ? Gate::authorize($capacidad)
            : Gate::authorize($capacidad, $servicio);
    }
}
