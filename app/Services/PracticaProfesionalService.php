<?php

namespace App\Services;

use App\Enums\EstatusPracticaProfesional;
use App\Exceptions\PracticaInvalidaException;
use App\Models\Alumno;
use App\Models\PracticaProfesional;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Servicio único de Prácticas Profesionales (2.0-13).
 *
 * Reglas aprobadas:
 *
 * - Un solo registro por alumno; crear nunca valida (EN_PROCESO).
 * - `calificacion` es texto libre: el sistema no infiere ninguna escala ni
 *   porcentaje mínimo para iniciar o validar (no hay fuente que lo defina).
 * - VALIDADO exige validación explícita (CONTROL_ESCOLAR / ADMIN) y una vez
 *   validado el registro es inmutable.
 *
 * Toda escritura ocurre dentro de una transacción con auditoría y la
 * autorización (`practica.*`) es server-side.
 */
class PracticaProfesionalService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
    ) {
    }

    /**
     * Crea el registro del alumno en EN_PROCESO (nunca validado).
     *
     * @param array<string, mixed> $datos empresa, fecha_inicio?, fecha_fin?, asesor?, calificacion?
     *
     * @throws PracticaInvalidaException si ya existe o los datos son inconsistentes
     */
    public function crear(Alumno $alumno, array $datos, ?User $usuario = null): PracticaProfesional
    {
        $usuario ??= auth()->user();
        $this->autorizar('practica.crear', null, $usuario);

        return DB::transaction(function () use ($alumno, $datos, $usuario): PracticaProfesional {
            if (PracticaProfesional::where('alumno_id', $alumno->getKey())->exists()) {
                throw new PracticaInvalidaException('El alumno ya tiene un registro de Prácticas Profesionales.');
            }

            $this->validarDatosBasicos($datos);

            $practica = PracticaProfesional::create([
                'alumno_id' => $alumno->getKey(),
                'empresa' => trim((string) $datos['empresa']),
                'fecha_inicio' => $datos['fecha_inicio'] ?? null,
                'fecha_fin' => $datos['fecha_fin'] ?? null,
                'asesor' => $this->textoOpcional($datos['asesor'] ?? null),
                'calificacion' => $this->textoOpcional($datos['calificacion'] ?? null),
                'estatus' => EstatusPracticaProfesional::EN_PROCESO,
            ]);

            $this->auditoria->registrar(
                $practica,
                'practica_creada',
                [],
                ['estatus' => EstatusPracticaProfesional::EN_PROCESO->value],
                ['empresa' => $practica->empresa],
                $usuario,
            );

            return $practica;
        });
    }

    /**
     * Actualiza los datos de un registro aún no validado.
     *
     * @param array<string, mixed> $datos
     *
     * @throws PracticaInvalidaException si el registro ya fue validado
     */
    public function actualizar(PracticaProfesional $practica, array $datos, ?User $usuario = null): PracticaProfesional
    {
        $usuario ??= auth()->user();
        $this->autorizar('practica.actualizar', $practica, $usuario);

        if ($practica->esValidado()) {
            throw new PracticaInvalidaException('Las Prácticas ya fueron validadas: el registro es inmutable.');
        }

        $this->validarDatosBasicos($datos);

        return DB::transaction(function () use ($practica, $datos, $usuario): PracticaProfesional {
            $antes = ['estatus' => $practica->estatus->value];

            $practica->update([
                'empresa' => trim((string) $datos['empresa']),
                'fecha_inicio' => $datos['fecha_inicio'] ?? null,
                'fecha_fin' => $datos['fecha_fin'] ?? null,
                'asesor' => $this->textoOpcional($datos['asesor'] ?? null),
                'calificacion' => $this->textoOpcional($datos['calificacion'] ?? null),
            ]);

            $this->auditoria->registrar(
                $practica,
                'practica_actualizada',
                $antes,
                ['estatus' => $practica->estatus->value],
                ['empresa' => $practica->empresa],
                $usuario,
            );

            return $practica->refresh();
        });
    }

    /**
     * Valida explícitamente el proceso (única vía hacia VALIDADO).
     *
     * @throws PracticaInvalidaException si ya estaba validado
     */
    public function validar(PracticaProfesional $practica, ?User $usuario = null): PracticaProfesional
    {
        $usuario ??= auth()->user();
        $this->autorizar('practica.validar', $practica, $usuario);

        if ($practica->esValidado()) {
            throw new PracticaInvalidaException('Las Prácticas ya fueron validadas.');
        }

        return DB::transaction(function () use ($practica, $usuario): PracticaProfesional {
            $antes = ['estatus' => $practica->estatus->value];

            $practica->update([
                'estatus' => EstatusPracticaProfesional::VALIDADO,
                'validado_por' => $usuario?->getKey(),
                'validado_at' => now(),
            ]);

            $this->auditoria->registrar(
                $practica,
                'practica_validada',
                $antes,
                ['estatus' => EstatusPracticaProfesional::VALIDADO->value],
                ['empresa' => $practica->empresa],
                $usuario,
            );

            return $practica->refresh();
        });
    }

    /**
     * @param array<string, mixed> $datos
     *
     * @throws PracticaInvalidaException
     */
    private function validarDatosBasicos(array $datos): void
    {
        if (trim((string) ($datos['empresa'] ?? '')) === '') {
            throw new PracticaInvalidaException('La empresa o institución de las prácticas es obligatoria.');
        }

        $inicio = $datos['fecha_inicio'] ?? null;
        $fin = $datos['fecha_fin'] ?? null;

        if ($inicio !== null && $fin !== null && strtotime((string) $fin) < strtotime((string) $inicio)) {
            throw new PracticaInvalidaException('La fecha final no puede ser anterior a la fecha inicial.');
        }
    }

    private function textoOpcional(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }

    private function autorizar(string $capacidad, ?PracticaProfesional $practica, ?User $usuario): void
    {
        if ($usuario !== null) {
            $practica === null
                ? Gate::forUser($usuario)->authorize($capacidad)
                : Gate::forUser($usuario)->authorize($capacidad, $practica);

            return;
        }

        $practica === null
            ? Gate::authorize($capacidad)
            : Gate::authorize($capacidad, $practica);
    }
}
