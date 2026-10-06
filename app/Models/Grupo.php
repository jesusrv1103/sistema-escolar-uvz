<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use App\Enums\EstatusGrupo;
use App\Enums\TurnoGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Grupo académico — sección de una materia en un periodo con docente y aula asignados.
 */
class Grupo extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    /** @var list<string> */
    protected $fillable = [
        'materia_id',
        'periodo_id',
        'docente_id',
        'aula_id',
        'clave',
        'nombre',
        'cupo',
        'turno',
        'estatus',
        'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cupo' => 'integer',
            // Contrato del turno: el BackedEnum aprobado `TurnoGrupo`. Antes la
            // columna llegaba como string y `FormatoService` (HORARIO, LISTA_GRUPO
            // y ACTA) hacía `$grupo->turno?->label()` → 500 sobre un string.
            'turno' => TurnoGrupo::class,
            'estatus' => EstatusGrupo::class,
        ];
    }

    /**
     * Materia del grupo.
     */
    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'materia_id');
    }

    /**
     * Periodo del grupo.
     */
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class, 'periodo_id');
    }

    /**
     * Docente asignado al grupo.
     */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    /**
     * Aula asignada al grupo.
     */
    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class, 'aula_id');
    }

    /**
     * Horarios del grupo.
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(GrupoHorario::class, 'grupo_id');
    }

    /**
     * Inscripciones a materias (carga académica) en este grupo.
     */
    public function inscripcionesMaterias(): HasMany
    {
        return $this->hasMany(InscripcionMateria::class, 'grupo_id');
    }

    /**
     * Scope: solo grupos activos.
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Verifica si el grupo está en estado ABIERTO (permite inscripciones).
     */
    public function estaAbierto(): bool
    {
        return $this->estatus === EstatusGrupo::ABIERTO;
    }

    /**
     * Verifica si el grupo está en estado CREADO (único en que se puede
     * editar la ficha o alternar su activación).
     */
    public function esCreado(): bool
    {
        return $this->estatus === EstatusGrupo::CREADO;
    }

    /**
     * Verifica si hay cupo disponible.
     */
    public function tieneCupo(): bool
    {
        return $this->inscripcionesMaterias()->where('estatus', \App\Enums\EstatusInscripcionMateria::CURSANDO->value)->count() < $this->cupo;
    }

    /**
     * Documentos generados de este grupo.
     */
    public function documentosGenerados(): HasMany
    {
        return $this->hasMany(DocumentoGenerado::class, 'grupo_id');
    }
}