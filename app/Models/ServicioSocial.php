<?php

namespace App\Models;

use App\Enums\EstatusServicioSocial;
use App\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Servicio Social de un alumno (2.0-13).
 *
 * Un único registro por alumno (`UNIQUE(alumno_id)`): la ausencia de fila
 * significa "sin registro". Una vez VALIDADO el registro es inmutable por
 * edición normal (lo garantiza `ServicioSocialService`).
 */
class ServicioSocial extends Model
{
    use HasFactory, HasAudit;

    protected $table = 'servicios_sociales';

    /** @var list<string> */
    protected $fillable = [
        'alumno_id',
        'actividad',
        'dependencia',
        'fecha_inicio',
        'fecha_fin',
        'avance',
        'estatus',
        'validado_por',
        'validado_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'avance' => 'integer',
            'estatus' => EstatusServicioSocial::class,
            'validado_at' => 'datetime',
        ];
    }

    /** Alumno dueño del proceso. */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /** Usuario que validó el proceso. */
    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    public function esValidado(): bool
    {
        return $this->estatus === EstatusServicioSocial::VALIDADO;
    }

    public function enProceso(): bool
    {
        return $this->estatus === EstatusServicioSocial::EN_PROCESO;
    }
}
