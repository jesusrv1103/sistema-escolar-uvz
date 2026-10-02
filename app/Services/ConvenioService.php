<?php

namespace App\Services;

use App\Enums\EstatusConvenio;
use App\Enums\InscripcionEstatus;
use App\Enums\TipoConvenio;
use App\Exceptions\ConvenioInvalidoException;
use App\Models\Convenio;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Sub-proceso de convenio (FC-3): aplicar → aprobar / rechazar.
 *
 * Four-eyes (matriz §8.2, RB-17): ADMISIONES **aplica** (queda
 * `PENDIENTE_APROBACION`); ADMINISTRACION **aprueba o rechaza**; ADMIN pasa
 * por `Gate::before`. La autorización se resuelve aquí, server-side, en un
 * único punto (`Gate::authorize` sobre los gates `convenio.aplicar` y
 * `convenio.aprobar`); las vistas solo ocultan controles con `@can`.
 *
 * El convenio es un sub-proceso, nunca un estado de la inscripción (§7.3.8).
 * Toda operación ocurre en una transacción con su auditoría (RB-12).
 */
class ConvenioService
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    /**
     * Aplica un convenio a la inscripción → `PENDIENTE_APROBACION` (FC-3).
     * `monto_descuento` permanece en 0 hasta la aprobación.
     */
    public function aplicar(Inscripcion $inscripcion, Convenio $convenio, ?User $usuario = null): Inscripcion
    {
        Gate::authorize('convenio.aplicar');
        $usuario ??= auth()->user();

        return DB::transaction(function () use ($inscripcion, $convenio, $usuario): Inscripcion {
            $this->validarEtapaPrevia($inscripcion);

            if ($inscripcion->convenio_id !== null) {
                throw new ConvenioInvalidoException('La inscripción ya tiene un convenio aplicado: solo se admite uno por inscripción.');
            }

            $this->validarVigencia($convenio);

            $inscripcion->forceFill([
                'convenio_id' => $convenio->getKey(),
                'convenio_estatus' => EstatusConvenio::PENDIENTE_APROBACION->value,
                'convenio_aplicado_por' => $usuario?->getKey(),
                'convenio_aplicado_at' => now(),
                'convenio_motivo' => null,
                'monto_descuento' => '0.00',   // se congela recién al aprobar
            ])->save();

            $this->auditoria->registrar(
                $inscripcion,
                'convenio_aplicado',
                [],
                [],
                [
                    'convenio_id' => $convenio->getKey(),
                    'convenio_estatus' => EstatusConvenio::PENDIENTE_APROBACION->value,
                ],
                $usuario,
            );

            return $inscripcion->refresh();
        });
    }

    /**
     * Aprueba el convenio pendiente y **congela** el descuento sobre
     * `monto_inscripcion` (snapshot, U4/U5). El descuento se aplica
     * únicamente al concepto INSCRIPCION (decisión UVZ).
     */
    public function aprobar(Inscripcion $inscripcion, ?User $usuario = null): Inscripcion
    {
        Gate::authorize('convenio.aprobar');
        $usuario ??= auth()->user();

        return DB::transaction(function () use ($inscripcion, $usuario): Inscripcion {
            $convenio = $this->convenioPendiente($inscripcion);

            $montoInscripcion = (float) $inscripcion->monto_inscripcion;

            $descuento = $convenio->tipo === TipoConvenio::PORCENTAJE
                ? $montoInscripcion * (float) $convenio->valor / 100
                : (float) $convenio->valor;

            // Redondeo monetario a 2 decimales; nunca negativo ni por encima
            // del monto de inscripción (el monto_total jamás queda < 0).
            $descuento = max(0.0, min(round($descuento, 2), $montoInscripcion));
            $descuentoTexto = number_format($descuento, 2, '.', '');

            $inscripcion->forceFill([
                'convenio_estatus' => EstatusConvenio::APROBADO->value,
                'convenio_aprobado_por' => $usuario?->getKey(),
                'convenio_aprobado_at' => now(),
                'convenio_motivo' => null,
                'monto_descuento' => $descuentoTexto,
            ])->save();

            $this->auditoria->registrar(
                $inscripcion,
                'convenio_aprobado',
                [],
                [],
                [
                    'convenio_id' => $convenio->getKey(),
                    'monto_descuento' => $descuentoTexto,
                ],
                $usuario,
            );

            return $inscripcion->refresh();
        });
    }

    /**
     * Rechaza el convenio pendiente: motivo obligatorio (5–2000) y
     * `monto_descuento` vuelve a 0 (sin descuento, U6/F23).
     */
    public function rechazar(Inscripcion $inscripcion, string $motivo, ?User $usuario = null): Inscripcion
    {
        Gate::authorize('convenio.aprobar');
        $usuario ??= auth()->user();

        $motivo = trim($motivo);
        $longitud = mb_strlen($motivo);

        if ($longitud < 5 || $longitud > 2000) {
            throw new ConvenioInvalidoException('El motivo de rechazo debe tener entre 5 y 2000 caracteres.');
        }

        return DB::transaction(function () use ($inscripcion, $motivo, $usuario): Inscripcion {
            $this->convenioPendiente($inscripcion);

            $inscripcion->forceFill([
                'convenio_estatus' => EstatusConvenio::RECHAZADO->value,
                'convenio_motivo' => $motivo,
                'monto_descuento' => '0.00',
            ])->save();

            $this->auditoria->registrar(
                $inscripcion,
                'convenio_rechazado',
                [],
                [],
                [
                    'convenio_id' => $inscripcion->convenio_id,
                    'motivo' => $motivo,
                ],
                $usuario,
            );

            return $inscripcion->refresh();
        });
    }

    /**
     * Etapa válida: previa a la generación de cargos (DRAFT o DATA_COMPLETE
     * y sin conceptos de pago). Impide aplicar convenios sobre cargos ya
     * generados (el recálculo posterior queda fuera del MVP).
     */
    private function validarEtapaPrevia(Inscripcion $inscripcion): void
    {
        if (! in_array($inscripcion->estatus, [InscripcionEstatus::DRAFT, InscripcionEstatus::DATA_COMPLETE], true)) {
            throw new ConvenioInvalidoException(
                'El convenio solo puede aplicarse antes de generar los cargos (etapa previa a PENDING_PAYMENT).',
            );
        }

        if ($inscripcion->conceptosPago()->exists()) {
            throw new ConvenioInvalidoException(
                'La inscripción ya tiene cargos generados: el convenio solo se aplica antes de generarlos.',
            );
        }
    }

    /** RB-13: convenio activo, no eliminado y dentro de su vigencia. */
    private function validarVigencia(Convenio $convenio): void
    {
        if ($convenio->trashed()) {
            throw new ConvenioInvalidoException('El convenio seleccionado fue eliminado.');
        }

        if (! $convenio->activo) {
            throw new ConvenioInvalidoException('El convenio seleccionado está inactivo y no puede aplicarse (RB-13).');
        }

        $hoy = today();

        if ($convenio->fecha_inicio !== null && $hoy->lt($convenio->fecha_inicio)) {
            throw new ConvenioInvalidoException('El convenio seleccionado está fuera de vigencia: aún no inicia (RB-13).');
        }

        if ($convenio->fecha_fin !== null && $hoy->gt($convenio->fecha_fin)) {
            throw new ConvenioInvalidoException('El convenio seleccionado está fuera de vigencia: ya venció (RB-13).');
        }
    }

    /** Exige convenio aplicado y en `PENDIENTE_APROBACION`. */
    private function convenioPendiente(Inscripcion $inscripcion): Convenio
    {
        if ($inscripcion->convenio_id === null) {
            throw new ConvenioInvalidoException('La inscripción no tiene convenio aplicado.');
        }

        if ($inscripcion->convenio_estatus !== EstatusConvenio::PENDIENTE_APROBACION) {
            throw new ConvenioInvalidoException(
                sprintf('El convenio no está pendiente de aprobación (estatus actual: %s).', $inscripcion->convenio_estatus?->value ?? 'sin estatus'),
            );
        }

        return $inscripcion->convenio()->firstOrFail();
    }
}
