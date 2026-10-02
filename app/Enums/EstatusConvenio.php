<?php

namespace App\Enums;

/** Estatus de la aplicación de un convenio (vive en inscripciones, §4.8). */
enum EstatusConvenio: string
{
    case PENDIENTE_APROBACION = 'PENDIENTE_APROBACION';
    case APROBADO = 'APROBADO';
    case RECHAZADO = 'RECHAZADO';
}
