<?php

namespace App\Services;

use App\Models\Pago;

/**
 * Folio de pago (RB-10, decisión Fase 8 aprobada).
 *
 * Formato exacto: `PAG-YYYYMMDD-########`
 *  - `YYYYMMDD` = fecha/hora de VALIDACIÓN con el timezone configurado de la
 *    aplicación (NO la fecha del hecho `fecha_pago`);
 *  - `########` = `pago.id` con padding a 8 dígitos (la unicidad lógica la
 *    garantiza el ID; el UNIQUE de BD es la defensa final);
 *  - longitud 21 ≤ varchar(30).
 *
 * Implementación central mínima y reutilizable: sin tabla `consecutivos`,
 * sin `MAX()`, sin reutilizar `NumeroControlService` (eso es para el número
 * de control, Fase 12). Una vez asignado el folio es inmutable y un pago
 * RECHAZADO jamás recibe folio (lo garantiza `PagoService`).
 */
class FolioPagoService
{
    public const PREFIJO = 'PAG';

    /** Genera el folio único del pago recién validado. */
    public function generar(Pago $pago): string
    {
        return sprintf(
            '%s-%s-%s',
            self::PREFIJO,
            now()->format('Ymd'),
            str_pad((string) $pago->getKey(), 8, '0', STR_PAD_LEFT),
        );
    }
}
