<?php

namespace App\Models;

use App\Concerns\HasAudit;
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
            'hora_inicio' => 'time',
            'hora_fin' => 'time',
        ];
    }

    /**
     * Grupo al que pertenece el horario.
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }
}