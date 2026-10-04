<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use App\Enums\TipoBaja;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Baja académica (temporal, definitiva o de materia).
 *
 * Registra la suspensión o cierre del expediente académico de un alumno
 * o de una materia específica.
 */
class Baja extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'bajas';

    /** @var list<string> */
    protected $fillable = [
        'alumno_id',
        'inscripcion_materia_id',
        'tipo',
        'motivo',
        'fecha',
        'reingreso_at',
        'registrado_por',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoBaja::class,
            'fecha' => 'date',
            'reingreso_at' => 'datetime',
        ];
    }

    /**
     * Alumno afectado por la baja.
     */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /**
     * Inscripción a materia (para bajas de materia).
     */
    public function inscripcionMateria(): BelongsTo
    {
        return $this->belongsTo(InscripcionMateria::class, 'inscripcion_materia_id');
    }

    /**
     * Usuario que registró la baja.
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /**
     * Verifica si es baja temporal.
     */
    public function esTemporal(): bool
    {
        return $this->tipo === TipoBaja::TEMPORAL;
    }

    /**
     * Verifica si es baja definitiva.
     */
    public function esDefinitiva(): bool
    {
        return $this->tipo === TipoBaja::DEFINITIVA;
    }

    /**
     * Verifica si es baja de materia.
     */
    public function esDeMateria(): bool
    {
        return $this->tipo === TipoBaja::MATERIA;
    }

    /**
     * Verifica si la baja temporal tiene reingreso autorizado.
     */
    public function tieneReingreso(): bool
    {
        return $this->reingreso_at !== null;
    }

    /**
     * Verifica si la baja temporal está vigente (sin reingreso).
     */
    public function estaVigente(): bool
    {
        return $this->esTemporal() && !$this->tieneReingreso();
    }
}