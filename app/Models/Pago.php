<?php

namespace App\Models;

use App\Enums\EstatusPago;
use App\Enums\MetodoPago;
use App\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de un concepto (§4.10, Fase 8).
 *
 * - Un concepto se liquida con UN solo pago completo (D5): sin parciales,
 *   sin anticipos. Un intento RECHAZADO se conserva como historial (nunca se
 *   sobrescribe ni elimina); solo puede existir UN intento `PENDIENTE` a la
 *   vez y UN pago `VALIDADO` que liquide el concepto (bloqueo con
 *   `lockForUpdate` en `PagoService::registrar()`).
 * - `estatus`, `folio`, `validado_*` y `motivo_rechazo` NO están en
 *   `$fillable`: los escribe únicamente `PagoService` dentro de su
 *   transacción (folio inmutable una vez asignado, RB-10).
 * - Sin soft delete: registro financiero (riesgo R15).
 * - El comprobante cargado vive en disco PRIVADO (`comprobante_ruta` es una
 *   ruta interna generada por el sistema, nunca el nombre original).
 */
class Pago extends Model
{
    /** @use HasFactory<Pago> */
    use HasAudit, HasFactory;

    /**
     * Campos de negocio de captura. Excluye deliberadamente los campos que
     * solo escribe el servicio: estatus, folio, validado_por/at y motivo.
     *
     * @var list<string>
     */
    protected $fillable = [
        'concepto_pago_id',
        'monto',
        'fecha_pago',
        'metodo',
        'referencia',
        'comprobante_ruta',
        'comprobante_nombre_original',
        'registrado_por',
        'notas',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metodo' => MetodoPago::class,
            'estatus' => EstatusPago::class,
            'monto' => 'decimal:2',
            'fecha_pago' => 'date',
            'validado_at' => 'datetime',
        ];
    }

    public function conceptoPago(): BelongsTo
    {
        return $this->belongsTo(ConceptoPago::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    /** Intención capturada, esperando validación four-eyes. */
    public function scopePendiente($query)
    {
        return $query->where('estatus', EstatusPago::PENDIENTE->value);
    }

    /** Pagos aplicados al concepto (liquidan el monto_total). */
    public function scopeValidado($query)
    {
        return $query->where('estatus', EstatusPago::VALIDADO->value);
    }

    /** Historial de intentos rechazados: se conservan íntegros (RB-11). */
    public function scopeRechazado($query)
    {
        return $query->where('estatus', EstatusPago::RECHAZADO->value);
    }
}
