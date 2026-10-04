<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use App\Enums\TipoAjusteFinanciero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ajuste financiero append-only (ledger) para becas y ajustes.
 *
 * Nunca se modifica ni elimina. Solo se agregan nuevos registros.
 * Usado para D-N3 becas: aplica descuentos como registros trazables
 * sin modificar destructivamente concepto_pago ni pagos.
 */
class AjusteFinanciero extends Model
{
    use HasFactory, HasAudit;

    protected $table = 'ajustes_financieros';

    /** @var list<string> */
    protected $fillable = [
        'alumno_id',
        'concepto_pago_id',
        'beca_alumno_id',
        'tipo',
        'monto',
        'ajuste_origen_id',
        'registrado_por',
        'registrado_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoAjusteFinanciero::class,
            'monto' => 'decimal:2',
            'registrado_at' => 'datetime',
        ];
    }

    /**
     * Alumno afectado por el ajuste.
     */
    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /**
     * Concepto de pago afectado.
     */
    public function conceptoPago()
    {
        return $this->belongsTo(ConceptoPago::class, 'concepto_pago_id');
    }

    /**
     * BecaAlumno que originó el ajuste (nullable para ajustes manuales).
     */
    public function becaAlumno()
    {
        return $this->belongsTo(BecaAlumno::class, 'beca_alumno_id');
    }

    /**
     * Ajuste original que este revierte (para REVERSO_DESCUENTO_BECA).
     */
    public function ajusteOrigen()
    {
        return $this->belongsTo(AjusteFinanciero::class, 'ajuste_origen_id');
    }

    /**
     * Usuario que registró el ajuste.
     */
    public function registradoPor()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /**
     * Verifica si es un descuento de beca.
     */
    public function esDescuentoBeca(): bool
    {
        return $this->tipo === TipoAjusteFinanciero::DESCUENTO_BECA;
    }

    /**
     * Verifica si es un reverso de descuento de beca.
     */
    public function esReversoDescuentoBeca(): bool
    {
        return $this->tipo === TipoAjusteFinanciero::REVERSO_DESCUENTO_BECA;
    }

    /**
     * Obtiene el efecto del ajuste sobre el saldo (positivo = reduce deuda).
     */
    public function efectoSobreSaldo(): float
    {
        return match ($this->tipo) {
            TipoAjusteFinanciero::DESCUENTO_BECA => $this->monto,
            TipoAjusteFinanciero::REVERSO_DESCUENTO_BECA => -$this->monto,
            default => 0,
        };
    }
}