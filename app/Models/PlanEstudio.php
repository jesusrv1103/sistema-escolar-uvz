<?php

namespace App\Models;

use App\Enums\EstatusPlanVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plan de estudio de un programa académico.
 *
 * Un programa tiene N planes de estudio.
 * Cada plan tiene N versiones (plan_versiones).
 */
class PlanEstudio extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'programa_id',
        'clave',
        'nombre',
        'resolucion_rvoe',
        'fecha_resolucion',
        'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_resolucion' => 'date',
            'activo' => 'boolean',
        ];
    }

    /**
     * Programa al que pertenece el plan.
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    /**
     * Versiones del plan.
     */
    public function versiones(): HasMany
    {
        return $this->hasMany(PlanVersion::class, 'plan_id');
    }

    /**
     * Materias del plan a través de sus versiones (hasManyThrough).
     */
    public function materias(): HasManyThrough
    {
        return $this->hasManyThrough(Materia::class, PlanVersion::class, 'plan_id', 'plan_version_id');
    }

    /**
     * Obtiene la versión vigente del plan (scope).
     */
    public function versionVigente()
    {
        return $this->hasOne(PlanVersion::class, 'plan_id')
            ->where('estatus', EstatusPlanVersion::VIGENTE->value)
            ->latest('fecha_vigencia');
    }

    /**
     * Scope: planes activos.
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: planes que tienen una versión vigente.
     */
    public function scopeConVersionVigente($query)
    {
        return $query->whereHas('versiones', fn ($q) => $q->where('estatus', EstatusPlanVersion::VIGENTE->value));
    }
}