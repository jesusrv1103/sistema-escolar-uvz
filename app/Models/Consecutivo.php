<?php

namespace App\Models;

use App\Enums\TipoConsecutivo;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila de secuencial por (tipo, año) (§4.15, P2 aprobado).
 *
 * - `UNIQUE (tipo, anio)` es la defensa de concurrencia junto con
 *   `lockForUpdate()` dentro de la transacción de finalización.
 * - Solo la escribe `NumeroControlService`: NO expone CRUD ni UI, y
 *   `ultimo_valor` nunca se decrementa (los números emitidos son permanentes).
 * - `ultimo_valor` en 9999 agota el año: el servicio lo rechaza de forma
 *   explícita (sin comportamiento silencioso).
 */
class Consecutivo extends Model
{
    /** Nombre de tabla personalizado (plural literal: consecutivos). */
    protected $table = 'consecutivos';

    /**
     * Campos del secuencial. Ningún componente Livewire lo rellena: la
     * escritura vive únicamente en `NumeroControlService`.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tipo',
        'anio',
        'ultimo_valor',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoConsecutivo::class,
            'anio' => 'integer',
            'ultimo_valor' => 'integer',
        ];
    }
}
