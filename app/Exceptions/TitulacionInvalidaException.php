<?php

namespace App\Exceptions;

use Exception;

/**
 * Regla de negocio de Titulación (2.0-14) incumplida.
 *
 * Se lanza desde `App\Services\TitulacionService` cuando no se puede crear o
 * actualizar el expediente de titulación: sin egreso asociado, registro
 * duplicado o folio interno ya usado.
 */
class TitulacionInvalidaException extends Exception
{
    //
}
