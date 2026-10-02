<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Programa académico (catálogo administrable, Fase 3).
 *
 * `clave` es un identificador INTERNO del sistema (ej. DER), no un RVOE.
 * `duracion_periodos` es la duración total en periodos académicos y se captura
 * desde el administrador mientras no exista confirmación institucional.
 */
class Programa extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'clave',
        'nombre',
        'duracion_periodos',
        'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'duracion_periodos' => 'integer',
            'activo' => 'boolean',
        ];
    }
}
