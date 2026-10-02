<?php

namespace App\Enums;

/** Método de pago (§4.10). */
enum MetodoPago: string
{
    case EFECTIVO = 'EFECTIVO';
    case TRANSFERENCIA = 'TRANSFERENCIA';
    case TARJETA = 'TARJETA';
    case OTRO = 'OTRO';
}
