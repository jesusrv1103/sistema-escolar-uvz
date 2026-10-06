<?php

namespace App\Exceptions;

use Exception;

/**
 * Regla de negocio de Servicio Social (2.0-13) incumplida.
 *
 * Se lanza desde `App\Services\ServicioSocialService` cuando no se puede
 * crear, editar, actualizar avance o validar un registro (registro ya
 * VALIDADO e inmutable, avance fuera de rango, fechas inconsistentes).
 */
class ServicioSocialInvalidaException extends Exception
{
    //
}
