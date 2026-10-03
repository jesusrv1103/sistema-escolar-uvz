<?php

namespace App\Models;

use App\Enums\TipoPrerrequisito;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prerrequisito entre materias (pivote explícito con lógica de negocio).
 *
 * No es un pivote anónimo: contiene reglas de negocio y timestamps normales.
 */
class MateriaPrerrequisito extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'materia_id',
        'prerrequisito_id',
        'tipo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoPrerrequisito::class,
        ];
    }

    /**
     * Materia que requiere el prerrequisito.
     */
    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'materia_id');
    }

    /**
     * Materia que actúa como prerrequisito.
     */
    public function prerrequisito(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'prerrequisito_id');
    }

    /**
     * Verifica si el prerrequisito es obligatorio.
     */
    public function esObligatorio(): bool
    {
        return $this->tipo === \App\Enums\TipoPrerrequisito::OBLIGATORIO;
    }

    /**
     * Verifica si el prerrequisito es recomendado.
     */
    public function esRecomendado(): bool
    {
        return $this->tipo === \App\Enums\TipoPrerrequisito::RECOMENDADO;
    }
}