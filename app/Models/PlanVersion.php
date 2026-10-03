<?php

namespace App\Models;

use App\Enums\EstatusPlanVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Versión de un plan de estudio.
 *
 * Estados: PROPUESTA → VIGENTE → SUPERADA
 * Solo ADMIN puede transicionar vía PlanVersionService::aprobar().
 *
 * Campos $fillable: solo datos de captura (plan_id, version, fecha_vigencia).
 * estatus, aprobado_por, aprobado_at se escriben SOLO en el servicio.
 */
class PlanVersion extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'plan_id',
        'version',
        'fecha_vigencia',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_vigencia' => 'date',
            'estatus' => EstatusPlanVersion::class,
            'aprobado_at' => 'datetime',
        ];
    }

    /**
     * Plan de estudio al que pertenece la versión.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanEstudio::class, 'plan_id');
    }

    /**
     * Usuario que aprobó la versión.
     */
    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /**
     * Materias de esta versión.
     */
    public function materias(): HasMany
    {
        return $this->hasMany(Materia::class, 'plan_version_id');
    }

    /**
     * Scope: solo versiones VIGENTE.
     */
    public function scopeVigente($query)
    {
        return $query->where('estatus', EstatusPlanVersion::VIGENTE->value);
    }

    /**
     * Scope: versiones de un plan específico.
     */
    public function scopePorPlan($query, int $planId)
    {
        return $query->where('plan_id', $planId);
    }

    /**
     * Verifica si la versión está en estado PROPUESTA.
     */
    public function esPropuesta(): bool
    {
        return $this->estatus === EstatusPlanVersion::PROPUESTA;
    }

    /**
     * Verifica si la versión está en estado VIGENTE.
     */
    public function esVigente(): bool
    {
        return $this->estatus === EstatusPlanVersion::VIGENTE;
    }

    /**
     * Verifica si la versión está en estado SUPERADA.
     */
    public function esSuperada(): bool
    {
        return $this->estatus === EstatusPlanVersion::SUPERADA;
    }
}