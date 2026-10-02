<?php

namespace App\Enums;

/** Tipo de contacto del alumno (§4.3). */
enum TipoContacto: string
{
    case TUTOR = 'TUTOR';
    case EMERGENCIA = 'EMERGENCIA';
}
