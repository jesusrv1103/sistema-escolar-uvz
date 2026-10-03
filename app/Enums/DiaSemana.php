<?php

namespace App\Enums;

use App\Concerns\HasEnumLabel;

/**
 * Días de la semana para horarios académicos.
 */
enum DiaSemana: string
{
    use HasEnumLabel;

    case LUNES = 'LUNES';
    case MARTES = 'MARTES';
    case MIERCOLES = 'MIERCOLES';
    case JUEVES = 'JUEVES';
    case VIERNES = 'VIERNES';
    case SABADO = 'SABADO';

    /**
     * Etiquetas legibles en español (Regla §18).
     */
    public function label(): string
    {
        return match ($this) {
            self::LUNES => 'Lunes',
            self::MARTES => 'Martes',
            self::MIERCOLES => 'Miércoles',
            self::JUEVES => 'Jueves',
            self::VIERNES => 'Viernes',
            self::SABADO => 'Sábado',
        };
    }

    /**
     * Orden numérico del día (1 = Lunes, ..., 6 = Sábado).
     */
    public function orden(): int
    {
        return match ($this) {
            self::LUNES => 1,
            self::MARTES => 2,
            self::MIERCOLES => 3,
            self::JUEVES => 4,
            self::VIERNES => 5,
            self::SABADO => 6,
        };
    }
}