<?php

namespace App\Services;

use App\Enums\EstatusObservacion;
use App\Enums\InscripcionEstatus;
use App\Enums\TipoObservacion;
use App\Exceptions\ObservacionInvalidaException;
use App\Models\Inscripcion;
use App\Models\Observacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Único punto de negocio de observaciones (Fase 11, §4.13/§12.9/RB-8/RB-15).
 *
 * Dos operaciones, cada UNA dentro de una transacción con ROLLBACK TOTAL y
 * auditoría en la misma transacción (RB-12):
 *  - `crear()` (los 4 roles, matriz §8.2): valida tipo en el BackedEnum y
 *    detalle de 5 a 2000 caracteres (con trim), nace `ABIERTA` con
 *    `creada_por` = usuario autenticado y `resuelta_* = null`. No se crean
 *    sobre una inscripción `CANCELLED` (estado terminal: el proceso está
 *    cerrado); en `ENROLLED` se permiten y NO tienen ningún efecto sobre el
 *    proceso (la lectura histórica siempre está disponible).
 *  - `resolver()` (los 4 roles): solo `ABIERTA` ⇒ `RESUELTA` con
 *    `resuelta_por`/`resuelta_at`; una observación ya `RESUELTA` se rechaza
 *    explícitamente (no hay reapertura en el MVP).
 *
 * APPEND-ONLY: no existe edición de `detalle`/`tipo` ni borrado (lo garantiza
 * además el modelo). NUNCA modifica `inscripciones.estatus`: los estados solo
 * cambian vía la máquina de estados (§7.3.6). Sin datos médicos (RB-18): el
 * payload de auditoría guarda tipo/estatus/inscripción, jamás el texto libre.
 */
class ObservacionService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
    ) {
    }

    /**
     * Crea una observación (FC del paso 15): autorización → validación →
     * transacción (fila + auditoría `observacion_creada`).
     *
     * @throws ObservacionInvalidaException si el tipo, el detalle o la
     *                                      inscripción no cumplen las reglas.
     */
    public function crear(
        Inscripcion $inscripcion,
        TipoObservacion|string $tipo,
        string $detalle,
        ?User $usuario = null,
    ): Observacion {
        // Autorización server-side (la UI solo oculta controles con @can).
        Gate::authorize('observacion.crear');
        $usuario ??= auth()->user();

        $tipo = $this->tipoValido($tipo);
        $detalle = $this->detalleValido($detalle);

        return DB::transaction(function () use ($inscripcion, $tipo, $detalle, $usuario): Observacion {
            // Lock de la inscripción: serializa creaciones concurrentes.
            $inscripcion = Inscripcion::query()->lockForUpdate()->findOrFail($inscripcion->getKey());

            if ($inscripcion->estatus === InscripcionEstatus::CANCELLED) {
                // Estado terminal: el proceso está cerrado, no se abren notas
                // nuevas sobre él (el histórico se conserva y es legible).
                throw new ObservacionInvalidaException('No se crean observaciones sobre una inscripción cancelada: el proceso está cerrado.');
            }

            $observacion = new Observacion();
            $observacion->forceFill([
                'inscripcion_id' => $inscripcion->getKey(),
                'tipo' => $tipo->value,
                'detalle' => $detalle,
                'estatus' => EstatusObservacion::ABIERTA->value,   // nace ABIERTA (§4.13)
                'creada_por' => $usuario?->getKey(),               // usuario autenticado server-side
                'resuelta_por' => null,
                'resuelta_at' => null,
            ])->save();

            // Auditoría dentro de la MISMA transacción (RB-12): payload
            // depurado — tipo/estatus/inscripción, sin texto libre.
            $this->auditoria->registrar(
                $observacion,
                'observacion_creada',
                [],
                ['estatus' => EstatusObservacion::ABIERTA->value],
                ['inscripcion_id' => $inscripcion->getKey(), 'tipo' => $tipo->value],
                $usuario,
            );

            return $observacion;
        });
    }

    /**
     * Resuelve una observación `ABIERTA` (§12.9): `RESUELTA` con
     * `resuelta_por`/`resuelta_at` + auditoría `observacion_resuelta`, todo
     * en UNA transacción. Una segunda resolución se rechaza explícitamente.
     *
     * @throws ObservacionInvalidaException si la observación ya está RESUELTA.
     */
    public function resolver(Observacion $observacion, ?User $usuario = null): Observacion
    {
        Gate::authorize('observacion.resolver');
        $usuario ??= auth()->user();

        return DB::transaction(function () use ($observacion, $usuario): Observacion {
            $observacion = Observacion::query()->lockForUpdate()->findOrFail($observacion->getKey());

            if ($observacion->estatus !== EstatusObservacion::ABIERTA) {
                throw new ObservacionInvalidaException(sprintf(
                    'Solo se resuelven observaciones con estatus %s (estatus actual: %s).',
                    EstatusObservacion::ABIERTA->label(),
                    $observacion->estatus->label(),
                ));
            }

            $observacion->forceFill([
                'estatus' => EstatusObservacion::RESUELTA->value,
                'resuelta_por' => $usuario?->getKey(),
                'resuelta_at' => now(),
            ])->save();

            $this->auditoria->registrar(
                $observacion,
                'observacion_resuelta',
                ['estatus' => EstatusObservacion::ABIERTA->value],
                ['estatus' => EstatusObservacion::RESUELTA->value],
                ['inscripcion_id' => $observacion->inscripcion_id, 'tipo' => $observacion->tipo->value],
                $usuario,
            );

            // Detalle y tipo intactos (append-only): solo cambia el estatus.
            return $observacion->refresh();
        });
    }

    /** El tipo debe pertenecer al BackedEnum `TipoObservacion` (§12.9). */
    private function tipoValido(TipoObservacion|string $tipo): TipoObservacion
    {
        $resuelto = $tipo instanceof TipoObservacion
            ? $tipo
            : TipoObservacion::tryFrom(trim($tipo));

        if ($resuelto === null) {
            throw new ObservacionInvalidaException(sprintf(
                'El tipo de observación es obligatorio y debe ser %s, %s, %s o %s.',
                TipoObservacion::FALTANTE->label(),
                TipoObservacion::DOCUMENTO->label(),
                TipoObservacion::PAGO->label(),
                TipoObservacion::GENERAL->label(),
            ));
        }

        return $resuelto;
    }

    /** Detalle: trim + obligatorio + 5–2000 caracteres (§12.9). */
    private function detalleValido(string $detalle): string
    {
        $detalle = trim($detalle);
        $longitud = mb_strlen($detalle);

        if ($longitud < 5 || $longitud > 2000) {
            throw new ObservacionInvalidaException('El detalle es obligatorio y debe tener entre 5 y 2000 caracteres.');
        }

        return $detalle;
    }
}
