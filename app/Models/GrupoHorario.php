<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use App\Enums\DiaSemana;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Horario de un grupo (día, hora inicio, hora fin).
 */
class GrupoHorario extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    /** @var list<string> */
    protected $fillable = [
        'grupo_id',
        'dia',
        'hora_inicio',
        'hora_fin',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'dia' => DiaSemana::class,
            'hora_inicio' => 'datetime',
            'hora_fin' => 'datetime',
        ];
    }

    /**
     * Grupo al que pertenece el horario.
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /**
     * Obtiene la hora de inicio formateada como H:i.
     */
    public function getHoraInicioFormateadaAttribute(): string
    {
        return $this->hora_inicio?->format('H:i') ?? '';
    }

    /**
     * Obtiene la hora de fin formateada como H:i.
     */
    public function getHoraFinFormateadaAttribute(): string
    {
        return $this->hora_fin?->format('H:i') ?? '';
    }
}