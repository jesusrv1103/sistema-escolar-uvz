<?php

namespace App\Enums;

/**
 * Tipos de formatos académicos internos UVZ.
 *
 * Pipeline: DATOS/REGLAS → SERVICIO → PLANTILLA BLADE → PDF
 * Solo formatos internos UVZ; fuera SEP/MEC/MET/SIGED.
 */
enum TipoFormato: string
{
    case CONSTANCIA_INSCRIPCION = 'CONSTANCIA_INSCRIPCION';
    case COMPROBANTE_INSCRIPCION = 'COMPROBANTE_INSCRIPCION';
    case FICHA = 'FICHA';
    case HORARIO = 'HORARIO';
    case LISTA_GRUPO = 'LISTA_GRUPO';
    case KARDEX = 'KARDEX';
    case BOLETA = 'BOLETA';
    case HISTORIAL = 'HISTORIAL';
    case ACTA = 'ACTA';
    case TIRA = 'TIRA';
    case AVANCE = 'AVANCE';
    case CONSTANCIA_TERMINACION = 'CONSTANCIA_TERMINACION';

    public function label(): string
    {
        return match ($this) {
            self::CONSTANCIA_INSCRIPCION => 'Constancia de Inscripción',
            self::COMPROBANTE_INSCRIPCION => 'Comprobante de Inscripción',
            self::FICHA => 'Ficha del Alumno',
            self::HORARIO => 'Horario del Alumno',
            self::LISTA_GRUPO => 'Lista de Grupo',
            self::KARDEX => 'Kardex',
            self::BOLETA => 'Boleta de Calificaciones',
            self::HISTORIAL => 'Historial Académico',
            self::ACTA => 'Acta de Calificaciones',
            self::TIRA => 'Tira de Materias',
            self::AVANCE => 'Reporte de Avance Académico',
            self::CONSTANCIA_TERMINACION => 'Constancia de Terminación',
        };
    }

    /**
     * Indica si el formato requiere folio (usa tabla consecutivos).
     */
    public function requiereFolio(): bool
    {
        return match ($this) {
            self::CONSTANCIA_INSCRIPCION => true,
            self::ACTA => true,
            default => false,
        };
    }

    /**
     * Indica si el formato requiere un alumno.
     */
    public function requiereAlumno(): bool
    {
        return match ($this) {
            self::LISTA_GRUPO => false,
            self::ACTA => false,
            default => true,
        };
    }

    /**
     * Tipo de consecutivo asociado (para los que requieren folio).
     */
    public function tipoConsecutivo(): ?string
    {
        return match ($this) {
            self::CONSTANCIA_INSCRIPCION => 'CONSTANCIA_INSCRIPCION',
            self::ACTA => 'ACTA_CALIFICACIONES',
            default => null,
        };
    }
}