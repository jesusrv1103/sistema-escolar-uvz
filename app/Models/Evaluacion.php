<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Evaluación académica de un grupo.
 *
 * Pertenece a un grupo y tiene ponderación. La suma de ponderaciones
 * de un grupo debe ser 100% para poder cerrar el acta.
 *
 * Sin máquina de estados formal (§10.2 del análisis).
 * La inmutabilidad académica la controla el cierre del ACTA (2.0-08).
 */
class Evaluacion extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'evaluaciones';

    /** @var list<string> */
    protected $fillable = [
        'grupo_id',
        'nombre',
        'tipo',
        'ponderacion',
        'fecha',
        'descripcion',
        'creada_por',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => 'string',
            'ponderacion' => 'integer',
            'fecha' => 'date',
        ];
    }

    /**
     * Grupo al que pertenece la evaluación.
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /**
     * Usuario que creó la evaluación.
     */
    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por');
    }

    /**
     * Calificaciones de esta evaluación.
     */
    public function calificaciones(): HasMany
    {
        return $this->hasMany(Calificacion::class, 'evaluacion_id');
    }

    /**
     * Scope: evaluaciones de un grupo.
     */
    public function scopeDelGrupo($query, int $grupoId)
    {
        return $query->where('grupo_id', $grupoId);
    }

    /**
     * Obtiene la ponderación total del grupo (excluyendo borradores).
     */
    public static function ponderacionTotalGrupo(int $grupoId): int
    {
        return self::where('grupo_id', $grupoId)
            ->sum('ponderacion');
    }
}