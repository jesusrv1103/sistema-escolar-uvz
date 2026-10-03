<?php

namespace App\Enums;

use App\Concerns\HasEnumLabel;

/**
 * Estatus de una versión de plan de estudio.
 *
 * PROPUESTA  → Borrador, no visible para matrícula.
 * VIGENTE    → Única por plan; la que usan los alumnos al matricularse.
 * SUPERADA   → Histórica; no editable, no asignable.
 *
 * Transiciones permitidas (solo ADMIN vía servicio dedicado):
 *   PROPUESTA → VIGENTE (aprobación ADMIN)
 *   VIGENTE   → SUPERADA  (automático al crear nueva VIGENTE)
 */
enum EstatusPlanVersion: string
{
    use HasEnumLabel;

    case PROPUESTA = 'PROPUESTA';
    case VIGENTE   = 'VIGENTE';
    case SUPERADA  = 'SUPERADA';

    /**
     * Etiquetas legibles en español (Regla §18).
     */
    public function label(): string
    {
        return match ($this) {
            self::PROPUESTA  => 'Propuesta',
            self::VIGENTE    => 'Vigente',
            self::SUPERADA   => 'Superada',
        };
    }

    /**
     * Verifica si se puede transicionar al estatus destino.
     * Solo ADMIN puede ejecutar transiciones (validado en servicio).
     */
    public function puedeIrA(self $hasta): bool
    {
        return match ($this) {
            self::PROPUESTA  => $hasta === self::VIGENTE,
            self::VIGENTE    => $hasta === self::SUPERADA,
            self::SUPERADA   => false, // terminal
        };
    }

    /**
     * Verifica si es un estatus terminal (no tiene salidas).
     */
    public function esTerminal(): bool
    {
        return $this === self::SUPERADA;
    }
}