<?php

namespace App\Enums;

/**
 * Tipo de sangre (§4.4, `informacion_medicas.tipo_sangre` varchar(5), nullable).
 *
 * Valores aprobados explícitamente en la Fase 4 (§10 del encargo): 8 tipos con RH.
 * `null` = dato no capturado.
 */
enum TipoSangre: string
{
    case A_POSITIVO = 'A+';
    case A_NEGATIVO = 'A-';
    case B_POSITIVO = 'B+';
    case B_NEGATIVO = 'B-';
    case AB_POSITIVO = 'AB+';
    case AB_NEGATIVO = 'AB-';
    case O_POSITIVO = 'O+';
    case O_NEGATIVO = 'O-';

    /** @return array<string, string> opciones para selects (valor => etiqueta) */
    public static function etiquetas(): array
    {
        $opciones = [];

        foreach (self::cases() as $caso) {
            $opciones[$caso->value] = $caso->value;
        }

        return $opciones;
    }
}
