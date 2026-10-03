<?php

namespace App\Concerns;

use BackedEnum;
use Illuminate\Support\Str;

/**
 * Trait para obtener etiquetas legibles en español desde enums BackedEnum.
 * Cumple la regla §18: prohibido mostrar Enum::value al usuario.
 */
trait HasEnumLabel
{
    /**
     * Obtiene la etiqueta legible en español para el valor del enum.
     * Por defecto convierte el valor snake_case a Title Case en español.
     * Los enums pueden sobrescribir este método para etiquetas personalizadas.
     */
    public function label(): string
    {
        if ($this instanceof BackedEnum) {
            $value = $this->value;
            
            if (is_string($value)) {
                // Convertir snake_case o SCREAMING_SNAKE_CASE a Title Case
                return Str::headline(strtolower($value));
            }
            
            return (string) $value;
        }
        
        return (string) $this->value;
    }
}