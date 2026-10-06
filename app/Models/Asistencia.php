<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asistencia de un alumno a una materia (vía inscripción a materia).
 */
class Asistencia extends Model
{
    // Sin SoftDeletes: la migración `create_asistencias_table` no define
    // `deleted_at`, y el trait añadía `asistencias`.`deleted_at is null` a toda
    // consulta → SQLSTATE 42S22 en cargar/guardar asistencia (nunca probado:
    // no hay ningún test que consultara la tabla).
    use HasFactory, HasAudit;

    protected $table = 'asistencias';

    /** @var list<string> */
    protected $fillable = [
        'inscripcion_materia_id',
        'fecha',
        'presente',
        'registrado_por',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'presente' => 'boolean',
        ];
    }

    /**
     * Inscripción a materia a la que pertenece la asistencia.
     */
    public function inscripcionMateria(): BelongsTo
    {
        return $this->belongsTo(InscripcionMateria::class, 'inscripcion_materia_id');
    }

    /**
     * Usuario que registró la asistencia.
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}