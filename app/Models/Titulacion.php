<?php

namespace App\Models;

use App\Enums\EstatusTitulacion;
use App\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Titulación interna (2.0-14).
 *
 * Requiere egreso registrado. `modalidad` y `folio_interno` son captura libre
 * (sin catálogo ni folio automático: no hay fuente que los defina).
 */
class Titulacion extends Model
{
    use HasFactory, HasAudit;

    protected $table = 'titulaciones';

    /** @var list<string> */
    protected $fillable = [
        'alumno_id',
        'egreso_id',
        'modalidad',
        'folio_interno',
        'estatus',
        'registrado_por',
        'registrado_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estatus' => EstatusTitulacion::class,
            'registrado_at' => 'datetime',
        ];
    }

    /** Alumno en proceso de titulación. */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /** Egreso asociado (obligatorio). */
    public function egreso(): BelongsTo
    {
        return $this->belongsTo(Egreso::class, 'egreso_id');
    }

    /** Usuario que registró el expediente. */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function esTitulado(): bool
    {
        return $this->estatus === EstatusTitulacion::TITULADO;
    }

    public function enTramite(): bool
    {
        return $this->estatus === EstatusTitulacion::EN_TRAMITE;
    }
}
