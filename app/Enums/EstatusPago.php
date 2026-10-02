<?php

namespace App\Enums;

/** Estatus del pago (§4.10). */
enum EstatusPago: string
{
    case PENDIENTE = 'PENDIENTE';
    case VALIDADO = 'VALIDADO';
    case RECHAZADO = 'RECHAZADO';
}
