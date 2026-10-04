<?php

namespace App\Enums;

/**
 * Sugerencias de tipos de evaluación académica.
 *
 * NO ES UN CATÁLOGO CERRADO — El campo `evaluaciones.tipo` es string libre.
 * Estos valores son solo ejemplos comunes UVZ para la UI.
 * No institucionalizar valores que UVZ no ha proporcionado oficialmente.
 */
class TipoEvaluacion
{
    /** Ejemplos comunes (no exhaustivos, no vinculantes). */
    public const EJEMPLOS = [
        'PARCIAL' => 'Parcial',
        'EXAMEN' => 'Examen',
        'PROYECTO' => 'Proyecto',
        'PRACTICA' => 'Práctica',
        'TAREA' => 'Tarea',
        'EXPOSICION' => 'Exposición',
        'OTRO' => 'Otro',
    ];

    /**
     * Devuelve los ejemplos para selects/UI (clave => etiqueta).
     */
    public static function ejemplos(): array
    {
        return self::EJEMPLOS;
    }

    /**
     * Verifica si un valor es válido (cualquier string no vacío).
     */
    public static function esValido(string $valor): bool
    {
        return $valor !== '';
    }
}