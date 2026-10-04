<?php

namespace App\Enums;

/**
 * Tipo de ajuste financiero en el ledger.
 */
enum TipoAjusteFinanciero: string
{
    case DESCUENTO_BECA = 'DESCUENTO_BECA';
    case REVERSO_DESCUENTO_BECA = 'REVERSO_DESCUENTO_BECA';

    public function label(): string
    {
        return match ($this) {
            self::DESCUENTO_BECA => 'Descuento por Beca',
            self::REVERSO_DESCUENTO_BECA => 'Reverso de Descuento por Beca',
        };
    }
}