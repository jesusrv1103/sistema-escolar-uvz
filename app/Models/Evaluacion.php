<?php

namespace App\Models;

use App\Concerns\HasAudit;
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
 * Estados permitidos (sin máquina de estados formal, §10.2 del análisis):
 * - BORRADOR: en edición, no visible para docentes/alumnos
 * - PUBLICADA: visible para captura de calificaciones
 * - CERRADA: no se permiten modificaciones (acta cerrada en 2.0-08)
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
        'estatus',
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
            'estatus' => 'string',
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
     * Verifica si la evaluación está en borrador.
     */
    public function esBorrador(): bool
    {
        return $this->estatus === 'BORRADOR';
    }

    /**
     * Verifica si la evaluación está publicada.
     */
    public function esPublicada(): bool
    {
        return $this->estatus === 'PUBLICADA';
    }

    /**
     * Verifica si la evaluación está cerrada.
     */
    public function esCerrada(): bool
    {
        return $this->estatus === 'CERRADA';
    }

    /**
     * Scope: evaluaciones de un grupo.
     */
    public function scopeDelGrupo($query, int $grupoId)
    {
        return $query->where('grupo_id', $grupoId);
    }

    /**
     * Scope: evaluaciones publicadas.
     */
    public function scopePublicadas($query)
    {
        return $query->where('estatus', 'PUBLICADA');
    }

    /**
     * Scope: evaluaciones cerradas.
     */
    public function scopeCerradas($query)
    {
        return $query->where('estatus', 'CERRADA');
    }

    /**
     * Obtiene la ponderación total del grupo (excluyendo borradores).
     */
    public static function ponderacionTotalGrupo(int $grupoId): int
    {
        return self::where('grupo_id', $grupoId)
            ->where('estatus', '!=', 'BORRADOR')
            ->sum('ponderacion');
    }
}