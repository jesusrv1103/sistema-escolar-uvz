<?php

namespace App\Enums;

/**
 * Tipo de baja académica.
 */
enum TipoBaja: string
{
    case TEMPORAL = 'TEMPORAL';
    case DEFINITIVA = 'DEFINITIVA';
    case MATERIA = 'MATERIA';

    public function label(): string
    {
        return match ($this) {
            self::TEMPORAL => 'Temporal',
            self::DEFINITIVA => 'Definitiva',
            self::MATERIA => 'De Materia',
        };
    }
}