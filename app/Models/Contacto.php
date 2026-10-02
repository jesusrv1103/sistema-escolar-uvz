<?php

namespace App\Models;

use App\Enums\TipoContacto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contacto del expediente (§4.3): TUTOR y EMERGENCIA en una sola tabla.
 * Sin soft delete: se gestionan junto con el alumno (cascade).
 */
class Contacto extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'alumno_id',
        'tipo',
        'nombre',
        'parentesco',
        'telefono',
        'telefono_alt',
        'correo',
        'notas',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoContacto::class,
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }
}
