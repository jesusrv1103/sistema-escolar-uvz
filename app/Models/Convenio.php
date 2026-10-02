<?php

namespace App\Models;

use App\Enums\TipoConvenio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Convenio del catálogo (§4.7). La aprobación NO vive aquí: cada inscripción
 * conserva su estatus de convenio (§4.8).
 */
class Convenio extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'clave',
        'nombre',
        'tipo',
        'valor',
        'fecha_inicio',
        'fecha_fin',
        'activo',
        'notas',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoConvenio::class,
            'valor' => 'decimal:2',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'activo' => 'boolean',
        ];
    }
}
