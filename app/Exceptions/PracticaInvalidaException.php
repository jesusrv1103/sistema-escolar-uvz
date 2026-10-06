<?php

namespace App\Exceptions;

use Exception;

/**
 * Regla de negocio de Prácticas Profesionales (2.0-13) incumplida.
 *
 * Se lanza desde `App\Services\PracticaProfesionalService` cuando no se puede
 * crear, editar o validar un registro (registro ya VALIDADO e inmutable,
 * fechas inconsistentes).
 */
class PracticaInvalidaException extends Exception
{
    //
}
