<?php

namespace App\Models;

use App\Enums\TipoPrerrequisito;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Materia de una versión de plan de estudio.
 */
class Materia extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'plan_version_id',
        'clave',
        'nombre',
        'creditos',
        'horas_teoricas',
        'horas_practicas',
        'periodo_orden',
        'bloque',
        'obligatoria',
        'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'creditos' => 'integer',
            'horas_teoricas' => 'integer',
            'horas_practicas' => 'integer',
            'periodo_orden' => 'integer',
            'obligatoria' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * Versión de plan a la que pertenece la materia.
     */
    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class, 'plan_version_id');
    }

    /**
     * Prerrequisitos de esta materia (materias que se requieren ANTES).
     */
    public function prerrequisitos(): BelongsToMany
    {
        return $this->belongsToMany(
            Materia::class,
            'materia_prerrequisitos',
            'materia_id',
            'prerrequisito_id'
        )
            ->withPivot('tipo')
            ->withTimestamps();
    }

    /**
     * Materias que tienen a esta como prerrequisito (dependientes).
     */
    public function dependientes(): BelongsToMany
    {
        return $this->belongsToMany(
            Materia::class,
            'materia_prerrequisitos',
            'prerrequisito_id',
            'materia_id'
        )
            ->withPivot('tipo')
            ->withTimestamps();
    }

    /**
     * Scope: materias activas.
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: materias obligatorias.
     */
    public function scopeObligatorias($query)
    {
        return $query->where('obligatoria', true);
    }

    /**
     * Scope: materias de un periodo_orden específico.
     */
    public function scopeDelPeriodoOrden($query, int $orden)
    {
        return $query->where('periodo_orden', $orden);
    }

    /**
     * Scope: materias de un bloque específico.
     */
    public function scopeDelBloque($query, string $bloque)
    {
        return $query->where('bloque', $bloque);
    }

    /**
     * Obtiene los prerrequisitos obligatorios.
     */
    public function prerrequisitosObligatorios()
    {
        return $this->prerrequisitos()->wherePivot('tipo', 'OBLIGATORIO');
    }

    /**
     * Obtiene los prerrequisitos recomendados.
     */
    public function prerrequisitosRecomendados()
    {
        return $this->prerrequisitos()->wherePivot('tipo', 'RECOMENDADO');
    }
}