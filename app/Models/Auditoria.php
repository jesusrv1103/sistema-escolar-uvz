<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Registro de auditoría (§4.14). APPEND-ONLY: solo inserción; `update()` y
 * `delete()` están bloqueados a nivel de aplicación y la tabla no tiene
 * `updated_at` ni soft delete.
 *
 * Único punto de escritura: `App\Services\AuditoriaService`.
 */
class Auditoria extends Model
{
    /** Tabla singular aprobada (§4.14). */
    protected $table = 'auditoria';

    /** Sin `updated_at` (append-only). `created_at` sí se gestiona. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'user_id',
        'accion',
        'estado_anterior',
        'estado_nuevo',
        'datos',
        'ip',
        'user_agent',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'datos' => 'array',
        ];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Etiqueta en español de `accion` PARA PRESENTACIÓN únicamente.
     *
     * En BD se conserva el valor técnico (`estado_cambiado`, …); aquí solo
     * se traduce lo que se muestra al usuario (regla permanente:
     * docs/reglas-desarrollo.md). Una acción desconocida regresa tal cual.
     */
    public function accionLabel(): string
    {
        return match ($this->accion) {
            'inscripcion_creada' => 'Inscripción creada',
            'estado_cambiado' => 'Estado cambiado',
            'cargos_generados' => 'Cargos generados',
            'numero_control_asignado' => 'Número de control asignado',
            'inscripcion_cancelada' => 'Inscripción cancelada',
            'pago_registrado' => 'Pago registrado',
            'pago_validado' => 'Pago validado',
            'pago_rechazado' => 'Pago rechazado',
            'documento_subido' => 'Documento subido',
            'documento_reemplazado' => 'Documento reemplazado',
            'documento_aceptado' => 'Documento aceptado',
            'documento_rechazado' => 'Documento rechazado',
            'observacion_creada' => 'Observación creada',
            'observacion_resuelta' => 'Observación resuelta',
            'convenio_aplicado' => 'Convenio aplicado',
            'convenio_aprobado' => 'Convenio aprobado',
            'convenio_rechazado' => 'Convenio rechazado',
            default => (string) $this->accion,
        };
    }

    /** Append-only: la auditoría nunca se edita (§10 regla crítica). */
    public function update(array $attributes = [], $options = [])
    {
        throw new LogicException('La auditoría es append-only: los registros no se modifican.');
    }

    /** Append-only: la auditoría nunca se elimina (§10 regla crítica). */
    public function delete($options = [])
    {
        throw new LogicException('La auditoría es append-only: los registros no se eliminan.');
    }

    public function forceDelete()
    {
        throw new LogicException('La auditoría es append-only: los registros no se eliminan.');
    }

    public function restore()
    {
        throw new LogicException('La auditoría es append-only: los registros no se restauran.');
    }
}
