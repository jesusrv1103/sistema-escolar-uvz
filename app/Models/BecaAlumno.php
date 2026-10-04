<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use App\Enums\EstatusBeca;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Asignación de beca a un alumno (2.0-12, D-N3).
 *
 * Four-eyes: CONTROL_ESCOLAR solicita/asigna, ADMINISTRACION aprueba/rechaza.
 */
class BecaAlumno extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'becas_alumnos';

    /** @var list<string> */
    protected $fillable = [
        'beca_id',
        'alumno_id',
        'periodo_id',
        'estatus',
        'asignada_por',
        'aprobada_por',
        'fecha_aprobacion',
        'observacion',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estatus' => EstatusBeca::class,
            'fecha_aprobacion' => 'datetime',
        ];
    }

    /**
     * Beca del catálogo.
     */
    public function beca()
    {
        return $this->belongsTo(Beca::class, 'beca_id');
    }

    /**
     * Alumno beneficiario.
     */
    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /**
     * Periodo de la asignación.
     */
    public function periodo()
    {
        return $this->belongsTo(Periodo::class, 'periodo_id');
    }

    /**
     * Usuario que realizó la asignación inicial.
     */
    public function asignadaPor()
    {
        return $this->belongsTo(User::class, 'asignada_por');
    }

    /**
     * Usuario que aprobó/rechazó.
     */
    public function aprobadaPor()
    {
        return $this->belongsTo(User::class, 'aprobada_por');
    }

    /**
     * Verifica si está aprobada.
     */
    public function esAprobada(): bool
    {
        return $this->estatus === EstatusBeca::APROBADA;
    }

    /**
     * Verifica si está pendiente.
     */
    public function esPendiente(): bool
    {
        return $this->estatus === EstatusBeca::PENDIENTE;
    }

    /**
     * Verifica si fue rechazada.
     */
    public function esRechazada(): bool
    {
        return $this->estatus === EstatusBeca::RECHAZADA;
    }

    /**
     * Calcula el monto del descuento según el tipo de beca.
     */
    public function calcularDescuento(float $montoConcepto): float
    {
        if (!$this->beca) {
            return 0;
        }

        $descuento = match ($this->beca->tipo) {
            \App\Enums\TipoBeca::PORCENTAJE => round($montoConcepto * ($this->beca->valor / 100), 2),
            \App\Enums\TipoBeca::MONTO => min($this->beca->valor, $montoConcepto),
            default => 0,
        };

        // Nunca exceder el monto del concepto
        return min($descuento, $montoConcepto);
    }
}