<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use App\Enums\EstatusInscripcionMateria;
use App\Enums\TipoPrerrequisito;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Inscripción de una matrícula a una materia (vía grupo).
 * Maneja prerrequisitos, estatus y calificaciones.
 */
class InscripcionMateria extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'inscripciones_materias';

    /** @var list<string> */
    protected $fillable = [
        'matricula_periodo_id',
        'grupo_id',
        'materia_id',
        'tipo_prerrequisito',
        'prerrequisito_cumplido',
        'estatus',
        'calificacion_final',
        'fecha_baja',
        'observaciones',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo_prerrequisito' => TipoPrerrequisito::class,
            'prerrequisito_cumplido' => 'boolean',
            'estatus' => EstatusInscripcionMateria::class,
            'calificacion_final' => 'decimal:2',
            'fecha_baja' => 'date',
        ];
    }

    /**
     * Matrícula del periodo a la que pertenece.
     */
    public function matriculaPeriodo(): BelongsTo
    {
        return $this->belongsTo(MatriculaPeriodo::class, 'matricula_periodo_id');
    }

    /**
     * Grupo al que se inscribe.
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /**
     * Materia inscrita.
     */
    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'materia_id');
    }

    /**
     * Verifica si la inscripción está cursando.
     */
    public function isCursando(): bool
    {
        return $this->estatus === EstatusInscripcionMateria::CURSANDO;
    }

    /**
     * Verifica si la inscripción está aprobada.
     */
    public function isAprobada(): bool
    {
        return $this->estatus === EstatusInscripcionMateria::APROBADA;
    }

    /**
     * Verifica si la inscripción está reprobada.
     */
    public function isReprobada(): bool
    {
        return $this->estatus === EstatusInscripcionMateria::REPROBADA;
    }

    /**
     * Verifica si la inscripción tiene prerrequisito obligatorio no cumplido.
     */
    public function tienePrerrequisitoObligatorioPendiente(): bool
    {
        return $this->tipo_prerrequisito === TipoPrerrequisito::OBLIGATORIO && !$this->prerrequisito_cumplido;
    }

    /**
     * Scope para inscripciones cursando.
     */
    public function scopeCursando($query)
    {
        return $query->where('estatus', EstatusInscripcionMateria::CURSANDO->value);
    }

    /**
     * Scope para inscripciones por matrícula.
     */
    public function scopePorMatricula($query, int $matriculaPeriodoId)
    {
        return $query->where('matricula_periodo_id', $matriculaPeriodoId);
    }

    /**
     * Scope para inscripciones por grupo.
     */
    public function scopePorGrupo($query, int $grupoId)
    {
        return $query->where('grupo_id', $grupoId);
    }

    /**
     * Calificaciones de esta inscripción.
     */
    public function calificaciones(): HasMany
    {
        return $this->hasMany(Calificacion::class, 'inscripcion_materia_id');
    }
}