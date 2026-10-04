<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use App\Enums\EstatusEquivalencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Equivalencia / Revalidación de materias (2.0-11).
 *
 * Registra la homologación de una materia externa como equivalente a una
 * materia del plan de estudios UVZ.
 */
class Equivalencia extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'equivalencias';

    /** @var list<string> */
    protected $fillable = [
        'alumno_id',
        'materia_id',
        'origen',
        'creditos',
        'dictamen',
        'estatus',
        'autorizada_por',
        'fecha_dictamen',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estatus' => EstatusEquivalencia::class,
            'fecha_dictamen' => 'date',
        ];
    }

    /**
     * Alumno beneficiado por la equivalencia.
     */
    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /**
     * Materia UVZ equivalente (nullable si es solo crédito libre).
     */
    public function materia()
    {
        return $this->belongsTo(Materia::class, 'materia_id');
    }

    /**
     * Usuario que autorizó el dictamen.
     */
    public function autorizadaPor()
    {
        return $this->belongsTo(User::class, 'autorizada_por');
    }

    /**
     * Verifica si está aprobada.
     */
    public function esAprobada(): bool
    {
        return $this->estatus === EstatusEquivalencia::APROBADA;
    }

    /**
     * Verifica si está pendiente.
     */
    public function esPendiente(): bool
    {
        return $this->estatus === EstatusEquivalencia::PENDIENTE;
    }

    /**
     * Verifica si fue rechazada.
     */
    public function esRechazada(): bool
    {
        return $this->estatus === EstatusEquivalencia::RECHAZADA;
    }
}