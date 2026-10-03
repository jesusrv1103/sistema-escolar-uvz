<?php

namespace App\Exceptions;

use Exception;

/**
 * Excepción lanzada cuando una operación sobre PlanVersion no es válida.
 */
class PlanVersionInvalidaException extends Exception
{
    public function __construct(string $message = 'Operación inválida sobre versión de plan.')
    {
        parent::__construct($message);
    }
}