<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tipo de documento del expediente de inscripción (catálogo, §4.11). */
class TipoDocumento extends Model
{
    use SoftDeletes;

    /** Nombre plural personalizado: `tipos_documento` (§3). */
    protected $table = 'tipos_documento';

    /** @var list<string> */
    protected $fillable = [
        'clave',
        'nombre',
        'obligatorio',
        'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'obligatorio' => 'boolean',
            'activo' => 'boolean',
        ];
    }
}
