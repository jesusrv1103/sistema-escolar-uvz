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

    /** @return array<string, string> opciones para selects (valor => etiqueta) */
    public static function etiquetas(): array
    {
        return [
            self::MASCULINO->value => 'Masculino',
            self::FEMENINO->value => 'Femenino',
        ];
    }
}
