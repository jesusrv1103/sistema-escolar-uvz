<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Acta de calificaciones de un grupo.
 *
 * Four-eyes: DOCENTE captura, CONTROL_ESCOLAR cierra.
 * Folio: ACT-YYYY-######## (consecutivo con lockForUpdate).
 */
class Acta extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'actas';

    /** @var list<string> */
    protected $fillable = [
        'grupo_id',
        'periodo_id',
        'folio',
        'estatus',
        'cerrada_por',
        'cerrada_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estatus' => 'string',
            'cerrada_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $acta) {
            if (!$acta->folio) {
                $acta->folio = resolve(\App\Services\ConsecutivoService::class)->generar(\App\Enums\TipoConsecutivo::ACTA_CALIFICACIONES);
            }
        });
    }

    /**
     * Grupo al que pertenece el acta.
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /**
     * Periodo del acta.
     */
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class, 'periodo_id');
    }

    /**
     * Usuario que cerró el acta.
     */
    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    /**
     * Verifica si el acta está abierta.
     */
    public function esAbierta(): bool
    {
        return $this->estatus === 'ABIERTA';
    }

    /**
     * Verifica si el acta está cerrada.
     */
    public function esCerrada(): bool
    {
        return $this->estatus === 'CERRADA';
    }

    /**
     * Scope: actas de un grupo.
     */
    public function scopeDelGrupo($query, int $grupoId)
    {
        return $query->where('grupo_id', $grupoId);
    }

    /**
     * Scope: actas de un periodo.
     */
    public function scopeDelPeriodo($query, int $periodoId)
    {
        return $query->where('periodo_id', $periodoId);
    }

    /**
     * Scope: actas abiertas.
     */
    public function scopeAbiertas($query)
    {
        return $query->where('estatus', 'ABIERTA');
    }

    /**
     * Scope: actas cerradas.
     */
    public function scopeCerradas($query)
    {
        return $query->where('estatus', 'CERRADA');
    }
}