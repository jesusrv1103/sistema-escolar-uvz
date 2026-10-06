<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Calificación de una evaluación para una inscripción a materia.
 */
class Calificacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'calificaciones';

    /** @var list<string> */
    protected $fillable = [
        'inscripcion_materia_id',
        'evaluacion_id',
        'valor',
        'capturada_por',
        'capturada_at',
        'observacion',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'capturada_at' => 'datetime',
        ];
    }

    /**
     * Inscripción a materia a la que pertenece la calificación.
     */
    public function inscripcionMateria(): BelongsTo
    {
        return $this->belongsTo(InscripcionMateria::class, 'inscripcion_materia_id');
    }

    /**
     * Evaluación a la que pertenece la calificación.
     */
    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class, 'evaluacion_id');
    }

    /**
     * Usuario que capturó la calificación.
     */
    public function capturadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'capturada_por');
    }
}