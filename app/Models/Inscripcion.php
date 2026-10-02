<?php

namespace App\Models;

use App\Enums\EstatusConvenio;
use App\Enums\EstatusObservacion;
use App\Enums\InscripcionEstatus;
use App\Enums\TipoConcepto;
use App\Enums\TipoObservacion;
use App\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Inscripción de un alumno en un periodo (§4.8). Entidad núcleo del flujo.
 *
 * - `estatus` solo cambia vía `InscripcionService` (§7.3.6): por eso NO está
 *   en `$fillable` (ni los hitos ni los responsables: los escribe el servicio).
 * - `UNIQUE(alumno_id, periodo_id)` (D2): un alumno no puede tener 2
 *   inscripciones en el mismo periodo.
 * - El convenio es un sub-proceso (`convenio_estatus`), nunca un estado (§7.3.8).
 * - No hay folio de inscripción (D7); `numero_control` pertenece al alumno.
 */
class Inscripcion extends Model
{
    /** @use HasFactory<Inscripcion> */
    use HasAudit, HasFactory, SoftDeletes;

    /** Nombre de tabla personalizado (pluralización aprobada: inscripciones). */
    protected $table = 'inscripciones';

    /**
     * Datos de creación/negocio. Excluye deliberadamente: `estatus`,
     * hitos (`*_at` de progreso) y responsables (salvo `creado_por`).
     *
     * @var list<string>
     */
    protected $fillable = [
        'alumno_id',
        'periodo_id',
        'programa_id',
        'monto_inscripcion',
        'monto_mensualidad',
        'convenio_id',
        'convenio_estatus',
        'convenio_motivo',
        'monto_descuento',
        'creado_por',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estatus' => InscripcionEstatus::class,
            'convenio_estatus' => EstatusConvenio::class,
            'monto_inscripcion' => 'decimal:2',
            'monto_mensualidad' => 'decimal:2',
            'monto_descuento' => 'decimal:2',
            'convenio_aplicado_at' => 'datetime',
            'convenio_aprobado_at' => 'datetime',
            'datos_completados_at' => 'datetime',
            'pagos_validados_at' => 'datetime',
            'documentos_validados_at' => 'datetime',
            'finalizada_at' => 'datetime',
            'cancelada_at' => 'datetime',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }

    /** Cargos generados (§4.9): 1 INSCRIPCION + 4 MENSUALIDADES (Fase 7). */
    public function conceptosPago(): HasMany
    {
        return $this->hasMany(ConceptoPago::class);
    }

    /** Documentos del expediente (§4.12): uno por tipo, reemplazables. */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    /** Observaciones / faltantes (§4.13): append-only, se crean y se resuelven. */
    public function observaciones(): HasMany
    {
        return $this->hasMany(Observacion::class);
    }

    /**
     * Relación de dominio (§6): el concepto `INSCRIPCION` de esta inscripción
     * (usado por las guardas de finalización RB-8.1 en la Fase 12).
     */
    public function conceptoInscripcion(): HasOne
    {
        return $this->hasOne(ConceptoPago::class)->where('tipo', TipoConcepto::INSCRIPCION->value);
    }

    /**
     * ¿Tiene alguna observación `FALTANTE` en estatus `ABIERTA`? (RB-8 punto 3)
     *
     * Única consulta reutilizable del bloqueo de finalización: la guardará
     * `InscripcionService::finalizar()` en la Fase 12. Solo FALTANTE + ABIERTA
     * bloquea; DOCUMENTO/PAGO/GENERAL abiertos y FALTANTE resueltos no.
     */
    public function tieneFaltantesAbiertos(): bool
    {
        return $this->observaciones()
            ->where('tipo', TipoObservacion::FALTANTE->value)
            ->where('estatus', EstatusObservacion::ABIERTA->value)
            ->exists();
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function validadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validada_por');
    }

    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelada_por');
    }

    public function convenioAplicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'convenio_aplicado_por');
    }

    public function convenioAprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'convenio_aprobado_por');
    }
}
