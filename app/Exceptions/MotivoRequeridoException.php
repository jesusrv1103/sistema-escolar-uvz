<?php

namespace App\Exceptions;

use RuntimeException;

/** Transición que exige motivo (retroceso o cancelación) sin motivo (§7.3.2). */
class MotivoRequeridoException extends RuntimeException
{
}
