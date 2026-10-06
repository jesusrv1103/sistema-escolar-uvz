<?php

namespace App\Exceptions;

use Exception;

/**
 * Regla de negocio de bajas (2.0-11) incumplida.
 *
 * Se lanza desde `App\Services\BajaService` cuando no se puede registrar la
 * baja (baja vigente previa, acta cerrada, inscripción ajena) ni autorizar el
 * reingreso (baja definitiva o ya reingresada).
 */
class BajaInvalidaException extends Exception
{
    //
}
