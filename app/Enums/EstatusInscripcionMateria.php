<?php

namespace App\Enums;

enum EstatusInscripcionMateria: string
{
    case CURSANDO = 'CURSANDO';
    case APROBADA = 'APROBADA';
    case REPROBADA = 'REPROBADA';
    case EXTRAORDINARIO = 'EXTRAORDINARIO';
    case BAJA = 'BAJA';
    case VALIDADA = 'VALIDADA';

    public function label(): string
    {
        return match ($this) {
            self::CURSANDO => 'Cursando',
            self::APROBADA => 'Aprobada',
            self::REPROBADA => 'Reprobada',
            self::EXTRAORDINARIO => 'Extraordinario',
            self::BAJA => 'Baja',
            self::VALIDADA => 'Validada',
        };
    }

    public function labelClass(): string
    {
        return match ($this) {
            self::CURSANDO => 'badge bg-primary',
            self::APROBADA => 'badge bg-success',
            self::REPROBADA => 'badge bg-danger',
            self::EXTRAORDINARIO => 'badge bg-warning text-dark',
            self::BAJA => 'badge bg-secondary',
            self::VALIDADA => 'badge bg-info text-dark',
        };
    }

    public function esTerminal(): bool
    {
        return in_array($this, [self::APROBADA, self::REPROBADA, self::EXTRAORDINARIO, self::BAJA, self::VALIDADA], true);
    }
}