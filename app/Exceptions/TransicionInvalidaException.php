<?php

namespace App\Exceptions;

use RuntimeException;

/** Intento de transición fuera de la matriz TRANSICIONES aprobada (§7.3.1). */
class TransicionInvalidaException extends RuntimeException
{
}
