<?php

namespace App\Enums;

/**
 * Tipo de beca (porcentaje o monto fijo).
 */
enum TipoBeca: string
{
    case PORCENTAJE = 'PORCENTAJE';
    case MONTO = 'MONTO';

    public function label(): string
    {
        return match ($this) {
            self::PORCENTAJE => 'Porcentaje',
            self::MONTO => 'Monto Fijo',
        };
    }
}