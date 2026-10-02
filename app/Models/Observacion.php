<?php

namespace App\Models;

use App\Enums\EstatusObservacion;
use App\Enums\TipoObservacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Observación / faltante sobre una inscripción (§4.13, Fase 11).
 *
 * - APPEND-ONLY (RB-15): se CREA y se RESUELVE; nunca se edita ni se
 *   borra. El `detalle` y el `tipo` son inmutables: una observación
 *   incorrecta se conserva como histórico y puede resolverse.
 * - `estatus`, `resuelta_por` y `resuelta_at` NO están en `$fillable`:
 *   los escribe únicamente `ObservacionService` dentro de su transacción.
 * - Solo `FALTANTE` + `ABIERTA` bloquea la finalización (RB-8): consultar
 *   `Inscripcion::tieneFaltantesAbiertos()` (guarda que usará la Fase 12).
 * - Sin soft delete (supuesto 17): las observaciones no se eliminan.
 */
class Observacion extends Model
{
    /** Tabla en español (§4.13): el pluralizador de Eloquent no aplica. */
    protected $table = 'observaciones';

    /**
     * Datos de creación. Excluye deliberadamente `estatus` y `resuelta_*`
     * (los escribe el servicio) — nada de esto es mas-asignable desde UI.
     *
     * @var list<string>
     */
    protected $fillable = [
        'inscripcion_id',
        'tipo',
        'detalle',
        'creada_por',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoObservacion::class,
            'estatus' => EstatusObservacion::class,
            'resuelta_at' => 'datetime',
        ];
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }

    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por');
    }

    public function resueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }

    /**
     * APPEND-ONLY: `detalle` y `tipo` jamás se editan (ni por la UI ni por
     * ningún servicio); el estatus solo cambia vía
     * `ObservacionService::resolver()` (forceFill + save dentro de su
     * transacción), nunca por actualización masiva.
     */
    public function update(array $attributes = [], $options = [])
    {
        throw new LogicException('Las observaciones son inmutables: el detalle y el tipo no se editan; el estatus solo cambia vía ObservacionService::resolver().');
    }

    /** APPEND-ONLY: nunca se borran (RB-15); solo se resuelven. */
    public function delete($options = [])
    {
        throw new LogicException('Las observaciones son append-only: no se eliminan; solo se resuelven.');
    }

    /** APPEND-ONLY: nunca se borran (RB-15); solo se resuelven. */
    public function forceDelete()
    {
        throw new LogicException('Las observaciones son append-only: no se eliminan; solo se resuelven.');
    }

    /** APPEND-ONLY: sin soft delete, no hay nada que restaurar. */
    public function restore()
    {
        throw new LogicException('Las observaciones son append-only: no se eliminan; solo se resuelven.');
    }
}
