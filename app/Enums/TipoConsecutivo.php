<?php

namespace App\Enums;

/**
 * Tipo de secuencial de la tabla `consecutivos` (§4.15, P2 aprobado).
 *
 * Enum mínimo y deliberadamente separado de los demás enums: los otros
 * (`TipoConcepto`, `TipoObservacion`, …) tienen semántica propia y NO se
 * reutilizan para secuenciales. En el MVP solo existe un tipo soportado.
 */
enum TipoConsecutivo: string
{
    case NUMERO_CONTROL = 'NUMERO_CONTROL';
}
