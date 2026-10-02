<?php

namespace App\Enums;

/** Tipo de convenio del catálogo (§4.7): PORCENTAJE (10.00 = 10%) o MONTO en MXN. */
enum TipoConvenio: string
{
    case PORCENTAJE = 'PORCENTAJE';
    case MONTO = 'MONTO';
}
