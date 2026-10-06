<?php

namespace App\Exceptions;

use Exception;

/**
 * Regla de negocio de Egreso (2.0-14) incumplida.
 *
 * Se lanza desde `App\Services\EgresoService` cuando no se puede registrar el
 * egreso: requisitos incompletos o egreso ya registrado para el alumno.
 */
class EgresoInvalidoException extends Exception
{
    //
}
