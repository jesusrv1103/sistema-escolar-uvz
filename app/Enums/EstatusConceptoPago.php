<?php

namespace App\Enums;

/**
 * Estatus de un concepto de pago (§4.9).
 *
 * VARCHAR protegido por BackedEnum. No se reutiliza `EstatusPago`: su
 * semántica pertenece a la tabla `pagos` (registro/validación de pagos, Fase 8).
 */
enum EstatusConceptoPago: string
{
    case PENDIENTE = 'PENDIENTE';
    case PAGADO = 'PAGADO';
    case CANCELADO = 'CANCELADO';
}
