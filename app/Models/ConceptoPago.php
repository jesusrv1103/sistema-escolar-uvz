<?php

namespace App\Models;

use App\Enums\EstatusConceptoPago;
use App\Enums\TipoConcepto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Concepto de pago (cargo) generado automáticamente (§4.9, RB-3).
 *
 * - Lo genera únicamente `InscripcionService::generarCargos()` en una sola
 *   transacción (1 INSCRIPCION + 4 MENSUALIDADES, cuatrimestre UVZ).
 * - Sin soft delete: registro financiero (riesgo R15).
 * - El descuento (solo convenio APROBADO) se aplica únicamente al concepto
 *   `INSCRIPCION`; las mensualidades nacen con descuento 0 (decisión UVZ).
 */
class ConceptoPago extends Model
{
    /** @use HasFactory<ConceptoPago> */
    use HasFactory;

    /** Nombre real de tabla (plural literal aprobado: conceptos_pago). */
    protected $table = 'conceptos_pago';

    /**
     * Campos persistibles. `estatus` y `pagado_at` los escribe el servicio
     * dentro de la transacción de generación.
     *
     * @var list<string>
     */
    protected $fillable = [
        'inscripcion_id',
        'tipo',
        'numero',
        'concepto',
        'monto',
        'descuento',
        'monto_total',
        'fecha_vencimiento',
        'estatus',
        'pagado_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoConcepto::class,
            'estatus' => EstatusConceptoPago::class,
            'monto' => 'decimal:2',
            'descuento' => 'decimal:2',
            'monto_total' => 'decimal:2',
            'fecha_vencimiento' => 'date',
            'pagado_at' => 'datetime',
        ];
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }
}
