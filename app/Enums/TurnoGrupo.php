<?php

namespace App\Enums;

use App\Concerns\HasEnumLabel;

/**
 * Turno de un grupo académico.
 */
enum TurnoGrupo: string
{
    use HasEnumLabel;

    case MATUTINO = 'MATUTINO';
    case VESPERTINO = 'VESPERTINO';
    case NOCTURNO = 'NOCTURNO';

    /**
     * Etiquetas legibles en español (Regla §18).
     */
    public function label(): string
    {
        return match ($this) {
            self::MATUTINO => 'Matutino',
            self::VESPERTINO => 'Vespertino',
            self::NOCTURNO => 'Nocturno',
        };
    }
}