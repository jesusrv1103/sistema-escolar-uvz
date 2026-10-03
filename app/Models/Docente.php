<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Docente — catálogo de docentes.
 *
 * Puede vincularse opcionalmente a un usuario (para portal de docente en 2.0-06).
 */
class Docente extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'clave',
        'nombre',
        'apellidos',
        'curp',
        'rfc',
        'correo',
        'telefono',
        'especialidad',
        'grado_estudios',
        'cedula_profesional',
        'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Usuario vinculado (opcional, para portal de docente).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Grupos asignados a este docente.
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'docente_id');
    }

    /**
     * Nombre completo del docente.
     */
    public function nombreCompleto(): string
    {
        return "{$this->nombre} {$this->apellidos}";
    }

    /**
     * Scope: solo docentes activos.
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}