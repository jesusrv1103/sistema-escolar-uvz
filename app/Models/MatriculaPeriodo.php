<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use App\Enums\EstatusMatriculaPeriodo;
use App\Enums\OrigenMatricula;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Matrícula de un alumno en un periodo (vincula alumno-periodo-programa).
 * Es distinta a la inscripción a materias (InscripcionMateria).
 */
class MatriculaPeriodo extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'matriculas_periodo';

    /** @var list<string> */
    protected $fillable = [
        'alumno_id',
        'periodo_id',
        'programa_id',
        'plan_version_id',
        'origen',
        'fecha_matricula',
        'estatus',
        'observaciones',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_matricula' => 'date',
            'origen' => OrigenMatricula::class,
            'estatus' => EstatusMatriculaPeriodo::class,
        ];
    }

    /**
     * Alumno matriculado.
     */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /**
     * Periodo de la matrícula.
     */
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class, 'periodo_id');
    }

    /**
     * Programa académico de la matrícula.
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class, 'programa_id');
    }

    /**
     * Versión del plan de estudios (opcional, se resuelve si no se envía).
     */
    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class, 'plan_version_id');
    }

    /**
     * Inscripciones a materias de esta matrícula.
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(InscripcionMateria::class, 'matricula_periodo_id');
    }

    /**
     * Inscripciones con estatus CURSANDO.
     */
    public function inscripcionesCursando(): HasMany
    {
        return $this->inscripciones()->where('estatus', 'CURSANDO');
    }

    /**
     * Inscripciones aprobadas.
     */
    public function inscripcionesAprobadas(): HasMany
    {
        return $this->inscripciones()->where('estatus', 'APROBADA');
    }

    /**
     * Verifica si la matrícula está activa.
     */
    public function isActiva(): bool
    {
        return $this->estatus === EstatusMatriculaPeriodo::ACTIVA;
    }

    /**
     * Scope para matrículas activas.
     */
    public function scopeActivas($query)
    {
        return $query->where('estatus', EstatusMatriculaPeriodo::ACTIVA->value);
    }

    /**
     * Scope para matrícula por alumno y periodo.
     */
    public function scopeParaAlumnoPeriodo($query, int $alumnoId, int $periodoId)
    {
        return $query->where('alumno_id', $alumnoId)->where('periodo_id', $periodoId);
    }

    /**
     * Documentos generados de esta matrícula.
     */
    public function documentosGenerados(): HasMany
    {
        return $this->hasMany(DocumentoGenerado::class, 'matricula_periodo_id');
    }
}