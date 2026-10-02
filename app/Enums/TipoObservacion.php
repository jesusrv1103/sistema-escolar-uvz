<?php

namespace App\Enums;

/** Tipo de observación (§4.13). Solo FALTANTE abierto bloquea la finalización. */
enum TipoObservacion: string
{
    case FALTANTE = 'FALTANTE';
    case DOCUMENTO = 'DOCUMENTO';
    case PAGO = 'PAGO';
    case GENERAL = 'GENERAL';

    /** Etiqueta visible en español; nunca exponer `->value` al usuario. */
    public function label(): string
    {
        return match ($this) {
            self::FALTANTE => 'Faltante',
            self::DOCUMENTO => 'Documento',
            self::PAGO => 'Pago',
            self::GENERAL => 'General',
        };
    }
}
