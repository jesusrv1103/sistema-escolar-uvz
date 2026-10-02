<?php

namespace App\Enums;

/** Estatus del documento (§4.12). */
enum EstatusDocumento: string
{
    case PENDIENTE = 'PENDIENTE';
    case ACEPTADO = 'ACEPTADO';
    case RECHAZADO = 'RECHAZADO';
}
