<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Periodo académico (catálogo administrable, Fase 3).
 *
 * `cerrado_at` (RB-13) bloquea operaciones de INSCRIPCIÓN: NO es un cierre
 * académico global para módulos futuros (calificaciones, asistencia, grupos).
 */
class Periodo extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'clave',
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'activo',
        'cerrado_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'activo' => 'boolean',
            'cerrado_at' => 'datetime',
        ];
    }
}
