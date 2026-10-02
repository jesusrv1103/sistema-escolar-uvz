<?php

namespace App\Enums;

/**
 * Sexo del alumno (§4.2, `alumnos.sexo` varchar(10)).
 *
 * Valores aprobados explícitamente en la Fase 4 (§10 del encargo).
 */
enum Sexo: string
{
    case MASCULINO = 'MASCULINO';
    case FEMENINO = 'FEMENINO';

    /** Etiqueta visible en español; nunca exponer `->value` al usuario. */
    public function label(): string
    {
        return match ($this) {
            self::MASCULINO => 'Masculino',
            self::FEMENINO => 'Femenino',
        };
    }

    /**
     * @return array<string, string> opciones para selects (valor => etiqueta)
     *
     * Compatibilidad: la fuente única de etiquetas es `label()`.
     */
    public static function etiquetas(): array
    {
        $opciones = [];

        foreach (self::cases() as $caso) {
            $opciones[$caso->value] = $caso->label();
        }

        return $opciones;
    }
}
