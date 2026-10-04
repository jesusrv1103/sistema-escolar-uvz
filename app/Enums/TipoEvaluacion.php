<?php

namespace App\Enums;

/**
 * Tipo de evaluación académica.
 *
 * VALORES NO INSTITUCIONALES — Solo ejemplos comunes, NO catálogo cerrado.
 * El campo es string libre validado; los valores son solo ejemplos comunes UVZ.
 * No institucionalizar valores que UVZ no ha proporcionado.
 */
enum TipoEvaluacion: string
{
    case PARCIAL = 'PARCIAL';
    case EXAMEN = 'EXAMEN';
    case PROYECTO = 'PROYECTO';
    case PRACTICA = 'PRACTICA';
    case TAREA = 'TAREA';
    case EXPOSICION = 'EXPOSICION';
    case OTRO = 'OTRO';

    public function label(): string
    {
        return match ($this) {
            self::PARCIAL => 'Parcial',
            self::EXAMEN => 'Examen',
            self::PROYECTO => 'Proyecto',
            self::PRACTICA => 'Práctica',
            self::TAREA => 'Tarea',
            self::EXPOSICION => 'Exposición',
            self::OTRO => 'Otro',
        };
    }

    /**
     * Valores permitidos (no exhaustivos, solo ejemplos).
     * El sistema acepta cualquier string; estos son solo ejemplos comunes.
     */
    public static function ejemplos(): array
    {
        return array_column(self::cases(), 'value');
    }
}