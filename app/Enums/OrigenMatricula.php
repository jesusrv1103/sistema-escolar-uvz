<?php

namespace App\Enums;

enum OrigenMatricula: string
{
    case PRIMERA_VEZ = 'PRIMERA_VEZ';
    case REINGRESO = 'REINGRESO';
    case CAMBIO_PROGRAMA = 'CAMBIO_PROGRAMA';
    case TRASLADO_EXTERNO = 'TRASLADO_EXTERNO';

    public function label(): string
    {
        return match ($this) {
            self::PRIMERA_VEZ => 'Primera Vez',
            self::REINGRESO => 'Reingreso',
            self::CAMBIO_PROGRAMA => 'Cambio de Programa',
            self::TRASLADO_EXTERNO => 'Traslado Externo',
        };
    }
}