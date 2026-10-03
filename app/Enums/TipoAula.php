<?php

namespace App\Enums;

use App\Concerns\HasEnumLabel;

/**
 * Tipo de aula.
 */
enum TipoAula: string
{
    use HasEnumLabel;

    case TEORICA = 'TEORICA';
    case PRACTICA = 'PRACTICA';
    case MIXTA = 'MIXTA';
    case LABORATORIO = 'LABORATORIO';

    /**
     * Etiquetas legibles en español (Regla §18).
     */
    public function label(): string
    {
        return match ($this) {
            self::TEORICA => 'Teórica',
            self::PRACTICA => 'Práctica',
            self::MIXTA => 'Mixta',
            self::LABORATORIO => 'Laboratorio',
        };
    }
}