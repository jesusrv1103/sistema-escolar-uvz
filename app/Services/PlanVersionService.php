<?php

namespace App\Services;

use App\Enums\EstatusPlanVersion;
use App\Exceptions\PlanVersionInvalidaException;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Servicio dedicado para la transición de versiones de plan.
 *
 * Implementa la transición PROPUESTA → VIGENTE con lockForUpdate sobre el plan padre
 * para garantizar que solo exista una versión VIGENTE por plan.
 * No usar índices filtrados (compatibilidad MySQL/MariaDB).
 */
class PlanVersionService
{
    public function __construct(
        private readonly \App\Services\AuditoriaService $auditoria
    ) {}

    /**
     * Aprueba una versión de plan: PROPUESTA → VIGENTE.
     *
     * En una sola transacción atómica:
     * 1. lockForUpdate() sobre PlanEstudio (padre)
     * 2. Busca VIGENTE actual dentro de la transacción
     * 3. Si existe: VIGENTE → SUPERADA
     * 4. PROPUESTA → VIGENTE + aprobado_por + aprobado_at
     * 5. Auditoría correspondiente
     *
     * @throws PlanVersionInvalidaException si la versión no es PROPUESTA, falta fecha_vigencia, etc.
     */
    public function aprobar(PlanVersion $version): PlanVersion
    {
        $usuario = Auth::user();

        return DB::transaction(function () use ($version, $usuario): PlanVersion {
            // 1. Bloquear el plan padre para serializar aprobaciones concurrentes
            $plan = PlanEstudio::query()->lockForUpdate()->findOrFail($version->plan_id);

            // 2. Recargar la versión dentro de la transacción con lock
            $version = PlanVersion::query()->lockForUpdate()->findOrFail($version->getKey());

            // 3. Validar estado actual
            if (! $version->esPropuesta()) {
                throw new PlanVersionInvalidaException('Solo se pueden aprobar versiones en estado PROPUESTA.');
            }

            // 4. Validar fecha_vigencia obligatoria al aprobar
            if ($version->fecha_vigencia === null) {
                throw new PlanVersionInvalidaException('La fecha de vigencia es obligatoria para aprobar la versión.');
            }

            // 5. Buscar VIGENTE actual del mismo plan (dentro de la transacción con lock)
            $vigenteActual = PlanVersion::query()
                ->where('plan_id', $plan->id)
                ->where('estatus', 'VIGENTE')
                ->lockForUpdate()
                ->first();

            // 6. Si existe VIGENTE actual, pasar a SUPERADA
            if ($vigenteActual !== null) {
                $vigenteActual->estatus = 'SUPERADA';
                $vigenteActual->save();
            }

            // Guardar estado anterior para auditoría
            $estadoAnterior = $version->estatus->value;

            // 7. Transicionar la versión a VIGENTE
            $version->forceFill([
                'estatus' => 'VIGENTE',
                'aprobado_por' => $usuario?->getKey(),
                'aprobado_at' => now(),
            ])->save();

            // 8. Registrar auditoría
            $this->auditoria->registrar(
                $version,
                'plan_version_aprobada',
                ['estatus' => $estadoAnterior],
                ['estatus' => 'VIGENTE'],
                ['fecha_vigencia' => $version->fecha_vigencia?->format('Y-m-d')]
            );

            return $version->refresh();
        });
    }

    /**
     * Crea una nueva versión en estado PROPUESTA.
     */
    public function crear(PlanEstudio $plan, string $version, ?string $fechaVigencia = null): PlanVersion
    {
        return $plan->versiones()->create([
            'version' => $version,
            'fecha_vigencia' => $fechaVigencia,
            'estatus' => 'PROPUESTA',
        ]);
    }
}