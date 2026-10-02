<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tipo de documento del expediente de inscripción (catálogo, §4.11). */
class TipoDocumento extends Model
{
    use HasFactory, SoftDeletes;

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

    /** Registros de este tipo en los expedientes (uno por inscripción). */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }
}
