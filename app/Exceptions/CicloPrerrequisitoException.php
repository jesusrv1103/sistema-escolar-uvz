<?php

namespace App\Exceptions;

use Exception;

/**
 * Excepción lanzada cuando se detecta un ciclo en la cadena de prerrequisitos.
 */
class CicloPrerrequisitoException extends Exception
{
    public function __construct(string $message = 'La relación crearía un ciclo de prerrequisitos.')
    {
        parent::__construct($message);
    }
}