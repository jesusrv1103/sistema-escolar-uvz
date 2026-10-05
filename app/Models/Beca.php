<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use App\Enums\TipoBeca;
use App\Enums\AlcanceBeca;
use App\Enums\EstatusBeca;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de becas institucionales.
 */
class Beca extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'becas';

    /** @var list<string> */
    protected $fillable = [
        'clave',
        'nombre',
        'tipo',
        'valor',
        'alcance',
        'periodo_id',
        'activo',
        'fecha_inicio',
        'fecha_fin',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoBeca::class,
            'alcance' => AlcanceBeca::class,
            'valor' => 'decimal:2',
            'activo' => 'boolean',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    /**
     * Periodo al que pertenece la beca.
     */
    public function periodo()
    {
        return $this->belongsTo(Periodo::class, 'periodo_id');
    }

    /**
     * Verifica si la beca está vigente para un periodo.
     */
    public function estaVigenteParaPeriodo(int $periodoId): bool
    {
        if (!$this->activo) {
            return false;
        }

        if ($this->periodo_id !== $periodoId) {
            return false;
        }

        $hoy = now()->toDateString();
        return ($this->fecha_inicio->toDateString() <= $hoy) && ($this->fecha_fin->toDateString() >= $hoy);
    }
}