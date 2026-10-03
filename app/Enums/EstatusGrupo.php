<?php

namespace App\Enums;

use App\Concerns\HasEnumLabel;

/**
 * Estatus de un grupo.
 *
 * CREADO → ABIERTO → EN_CURSO → CERRADO
 * CREADO → CANCELADO
 * ABIERTO → CANCELADO
 * EN_CURSO → CANCELADO
 */
enum EstatusGrupo: string
{
    use HasEnumLabel;

    case CREADO = 'CREADO';
    case ABIERTO = 'ABIERTO';
    case EN_CURSO = 'EN_CURSO';
    case CERRADO = 'CERRADO';
    case CANCELADO = 'CANCELADO';

    /**
     * Etiquetas legibles en español (Regla §18).
     */
    public function label(): string
    {
        return match ($this) {
            self::CREADO => 'Creado',
            self::ABIERTO => 'Abierto',
            self::EN_CURSO => 'En Curso',
            self::CERRADO => 'Cerrado',
            self::CANCELADO => 'Cancelado',
        };
    }

    /**
     * Verifica si se puede transicionar al estatus destino.
     */
    public function puedeIrA(self $hasta): bool
    {
        return match ($this) {
            self::CREADO => in_array($hasta, [self::ABIERTO, self::CANCELADO], true),
            self::ABIERTO => in_array($hasta, [self::EN_CURSO, self::CANCELADO], true),
            self::EN_CURSO => in_array($hasta, [self::CERRADO, self::CANCELADO], true),
            self::CERRADO => false,
            self::CANCELADO => false,
        };
    }

    /**
     * Verifica si es un estatus terminal (no tiene salidas).
     */
    public function esTerminal(): bool
    {
        return in_array($this, [self::CERRADO, self::CANCELADO], true);
    }
}