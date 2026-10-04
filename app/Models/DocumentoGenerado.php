<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use App\Enums\TipoFormato;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Trazabilidad de documentos generados (formatos académicos).
 *
 * Registra la emisión de formatos académicos internos UVZ.
 * No almacena el PDF binario, solo la trazabilidad y el snapshot de datos.
 */
class DocumentoGenerado extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'documentos_generados';

    /** @var list<string> */
    protected $fillable = [
        'alumno_id',
        'matricula_periodo_id',
        'grupo_id',
        'tipo',
        'folio',
        'emitido_por',
        'emitido_at',
        'snapshot_datos',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoFormato::class,
            'emitido_at' => 'datetime',
            'snapshot_datos' => 'array',
        ];
    }

    /**
     * Alumno al que se emitió el documento.
     */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /**
     * Matrícula del periodo (si aplica).
     */
    public function matriculaPeriodo(): BelongsTo
    {
        return $this->belongsTo(MatriculaPeriodo::class, 'matricula_periodo_id');
    }

    /**
     * Grupo (si aplica).
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /**
     * Usuario que emitió el documento.
     */
    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    /**
     * Obtiene el tipo de formato como enum.
     */
    public function getTipoEnum(): TipoFormato
    {
        return TipoFormato::from($this->tipo);
    }

    /**
     * Verifica si el documento tiene folio asignado.
     */
    public function tieneFolio(): bool
    {
        return $this->folio !== null;
    }
}