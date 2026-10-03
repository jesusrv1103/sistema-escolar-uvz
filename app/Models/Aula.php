<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use App\Enums\TipoAula;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Aula — catálogo de aulas.
 */
class Aula extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    /** @var list<string> */
    protected $fillable = [
        'clave',
        'nombre',
        'edificio',
        'capacidad',
        'tipo',
        'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capacidad' => 'integer',
            'activo' => 'boolean',
            'tipo' => TipoAula::class,
        ];
    }

    /**
     * Grupos asignados a esta aula.
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'aula_id');
    }

    /**
     * Scope: solo aulas activas.
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}