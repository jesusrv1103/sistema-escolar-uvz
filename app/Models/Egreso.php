<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Egreso académico interno (2.0-14).
 *
 * La existencia del registro es el estado "EGRESÓ": sólo se crea cuando la
 * verificación de requisitos pasó completa (`EgresoService::verificar`).
 * Guarda el snapshot de la verificación para trazabilidad.
 */
class Egreso extends Model
{
    use HasFactory, HasAudit;

    protected $table = 'egresos';

    /** @var list<string> */
    protected $fillable = [
        'alumno_id',
        'plan_version_id',
        'creditos_requeridos',
        'creditos_acumulados',
        'requisitos',
        'registrado_por',
        'registrado_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requisitos' => 'array',
            'registrado_at' => 'datetime',
        ];
    }

    /** Alumno egresado. */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /** Versión del plan con la que se verificó. */
    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class, 'plan_version_id');
    }

    /** Usuario que registró el egreso. */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /** Expediente de titulación asociado (si existe). */
    public function titulacion(): HasOne
    {
        return $this->hasOne(Titulacion::class, 'egreso_id');
    }
}
