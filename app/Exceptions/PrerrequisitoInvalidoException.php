<?php

namespace App\Exceptions;

use Exception;

/**
 * Excepción lanzada cuando un prerrequisito no cumple las reglas de validación.
 */
class PrerrequisitoInvalidoException extends Exception
{
    public function __construct(string $message = 'Prerrequisito inválido.')
    {
        parent::__construct($message);
    }
}