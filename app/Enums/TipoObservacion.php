<?php

namespace App\Enums;

/** Tipo de observación (§4.13). Solo FALTANTE abierto bloquea la finalización. */
enum TipoObservacion: string
{
    case FALTANTE = 'FALTANTE';
    case DOCUMENTO = 'DOCUMENTO';
    case PAGO = 'PAGO';
    case GENERAL = 'GENERAL';
}
