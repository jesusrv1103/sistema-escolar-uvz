<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Generación de cargos inválida (RB-3, FC-4): estatus distinto de
 * `DATA_COMPLETE` o intento duplicado de generación (idempotencia).
 */
class CargosInvalidosException extends RuntimeException
{
}
