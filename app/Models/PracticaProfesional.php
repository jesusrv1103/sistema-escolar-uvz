<?php

namespace App\Models;

use App\Enums\EstatusPracticaProfesional;
use App\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prácticas Profesionales de un alumno (2.0-13).
 *
 * Un único registro por alumno (`UNIQUE(alumno_id)`). `calificacion` es texto
 * libre: el sistema no infiere ninguna escala (pendiente institucional).
 * Una vez VALIDADO el registro es inmutable por edición normal.
 */
class PracticaProfesional extends Model
{
    use HasFactory, HasAudit;

    protected $table = 'practicas_profesionales';

    /** @var list<string> */
    protected $fillable = [
        'alumno_id',
        'empresa',
        'fecha_inicio',
        'fecha_fin',
        'asesor',
        'calificacion',
        'estatus',
        'validado_por',
        'validado_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'estatus' => EstatusPracticaProfesional::class,
            'validado_at' => 'datetime',
        ];
    }

    /** Alumno dueño del proceso. */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /** Usuario que validó el proceso. */
    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    public function esValidado(): bool
    {
        return $this->estatus === EstatusPracticaProfesional::VALIDADO;
    }

    public function enProceso(): bool
    {
        return $this->estatus === EstatusPracticaProfesional::EN_PROCESO;
    }
}
