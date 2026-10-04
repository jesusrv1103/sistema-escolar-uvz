<?php

namespace App\Enums;

enum EstatusMatriculaPeriodo: string
{
    case ACTIVA = 'ACTIVA';
    case BAJA_TEMPORAL = 'BAJA_TEMPORAL';
    case BAJA_DEFINITIVA = 'BAJA_DEFINITIVA';
    case EGRESADO = 'EGRESADO';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVA => 'Activa',
            self::BAJA_TEMPORAL => 'Baja Temporal',
            self::BAJA_DEFINITIVA => 'Baja Definitiva',
            self::EGRESADO => 'Egresado',
        };
    }

    public function labelClass(): string
    {
        return match ($this) {
            self::ACTIVA => 'badge bg-success',
            self::BAJA_TEMPORAL => 'badge bg-warning text-dark',
            self::BAJA_DEFINITIVA => 'badge bg-danger',
            self::EGRESADO => 'badge bg-primary',
        };
    }

    public function esActiva(): bool
    {
        return $this === self::ACTIVA;
    }

    public function esTerminal(): bool
    {
        return in_array($this, [self::BAJA_DEFINITIVA, self::EGRESADO], true);
    }
}