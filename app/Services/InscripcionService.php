<?php

namespace App\Services;

use App\Enums\InscripcionEstatus;
use App\Exceptions\MotivoRequeridoException;
use App\Exceptions\TransicionInvalidaException;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Único punto autorizado para modificar `inscripciones.estatus` (§7.3.6).
 *
 * Toda transición:
 *  1. se valida contra la matriz única `InscripcionEstatus::TRANSICIONES` (§7.3.1);
 *  2. exige motivo cuando corresponde (retrocesos y cancelaciones, §7.3.2);
 *  3. ocurre dentro de una transacción de BD (§7.3.3);
 *  4. registra auditoría y usuario responsable en la misma transacción.
 *
 * Las guardas de negocio de módulos aún inexistentes (pagos, documentos,
 * número de control) NO se simulan: se incorporarán en sus fases (§7.3.5).
 */
class InscripcionService
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    public function puedeTransicionar(InscripcionEstatus $desde, InscripcionEstatus $hasta): bool
    {
        return $desde->puedeIrA($hasta);
    }

    /**
     * Crea el borrador de inscripción en `DRAFT` (FC-2, §4.8).
     *
     * - `estatus` NO se envía: lo da el default de columna (`DRAFT`).
     * - Los montos son *snapshots* capturados aquí (no existe catálogo de tarifas).
     * - Los datos académicos (alumno/periodo/programa) ya fueron validados por
     *   la capa de presentación (§12.4 + RB-13/RB-14); `UNIQUE(alumno_id,
     *   periodo_id)` es la última línea de defensa.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, ?User $usuario = null): Inscripcion
    {
        $usuario ??= auth()->user();

        return DB::transaction(function () use ($datos, $usuario): Inscripcion {
            $inscripcion = Inscripcion::create($datos + ['creado_por' => $usuario?->getKey()]);
            $inscripcion->refresh();   // trae los defaults de columna (`estatus` = DRAFT)

            $this->auditoria->registrar(
                $inscripcion,
                'inscripcion_creada',
                [],
                [
                    'alumno_id' => $inscripcion->alumno_id,
                    'periodo_id' => $inscripcion->periodo_id,
                    'programa_id' => $inscripcion->programa_id,
                    'estatus' => $inscripcion->estatus->value,
                ],
                [],
                $usuario,
            );

            return $inscripcion;
        });
    }

    /**
     * Ejecuta la transición o lanza excepción explícita. Nunca deja la
     * inscripción en el nuevo estado si falla la auditoría (transacción).
     */
    public function transicionar(
        Inscripcion $inscripcion,
        InscripcionEstatus $hasta,
        ?string $motivo = null,
        ?User $usuario = null,
    ): Inscripcion {
        $usuario ??= auth()->user();
        $desde = $inscripcion->estatus;

        if (! $this->puedeTransicionar($desde, $hasta)) {
            throw new TransicionInvalidaException(
                sprintf('Transición inválida: %s → %s.', $desde->value, $hasta->value),
            );
        }

        $motivo = $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null;

        if ($desde->requiereMotivo($hasta) && $motivo === null) {
            throw new MotivoRequeridoException(
                sprintf('La transición %s → %s requiere motivo explícito.', $desde->value, $hasta->value),
            );
        }

        return DB::transaction(function () use ($inscripcion, $desde, $hasta, $motivo, $usuario): Inscripcion {
            $inscripcion->forceFill(array_merge(
                ['estatus' => $hasta->value],
                $this->complementos($inscripcion, $hasta, $motivo, $usuario),
            ))->save();

            $this->auditoria->registrar(
                $inscripcion,
                'estado_cambiado',
                ['estatus' => $desde->value],
                ['estatus' => $hasta->value],
                $motivo === null ? [] : ['motivo' => $motivo],
                $usuario,
            );

            return $inscripcion;
        });
    }

    /**
     * Hitos y responsables que la propia transición deja registrados.
     *
     * @return array<string, mixed>
     */
    private function complementos(
        Inscripcion $inscripcion,
        InscripcionEstatus $hasta,
        ?string $motivo,
        ?User $usuario,
    ): array {
        $campos = [];

        // Hitos: se marcan una sola vez (no se pisan en reintentos/retrocesos).
        $hitos = match ($hasta) {
            InscripcionEstatus::DATA_COMPLETE => ['datos_completados_at'],
            InscripcionEstatus::PAYMENT_VALIDATED => ['pagos_validados_at'],
            InscripcionEstatus::FINAL_REVIEW => ['documentos_validados_at'],
            InscripcionEstatus::ENROLLED => ['finalizada_at'],
            default => [],
        };

        foreach ($hitos as $hito) {
            if ($inscripcion->{$hito} === null) {
                $campos[$hito] = now();
            }
        }

        if ($hasta === InscripcionEstatus::ENROLLED && $usuario !== null) {
            $campos['validada_por'] = $usuario->getKey();   // revisión final responsable
        }

        if ($hasta === InscripcionEstatus::CANCELLED) {
            $campos['cancelada_por'] = $usuario?->getKey();
            $campos['cancelada_at'] = now();
            $campos['cancelacion_motivo'] = $motivo;
        }

        return $campos;
    }
}
