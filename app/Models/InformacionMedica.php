<?php

namespace App\Models;

use App\Enums\TipoSangre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Información médica del alumno (§4.4): relación 1:1 garantizada por
 * `alumno_id` unique. INFORMACIÓN SENSIBLE (RB-18): nunca en listados
 * generales, búsquedas, dashboard ni logs.
 */
class InformacionMedica extends Model
{
    /** Nombre de tabla personalizado (pluralización literal). */
    protected $table = 'informacion_medicas';

    /** @var list<string> */
    protected $fillable = [
        'alumno_id',
        'tipo_sangre',
        'alergias',
        'condiciones',
        'medicamentos',
        'tiene_discapacidad',
        'detalle_discapacidad',
        'actualizado_por',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo_sangre' => TipoSangre::class,
            'tiene_discapacidad' => 'boolean',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }
}
