<?php

namespace App\Models;

use App\Enums\EstatusDocumento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Documento del expediente de una inscripción (§4.12, Fase 10).
 *
 * - UN registro por tipo por inscripción (`UNIQUE(inscripcion_id,
 *   tipo_documento_id)`): una recarga REEMPLAZA el archivo de esta misma fila
 *   (vuelve a `PENDIENTE` y limpia la validación); el histórico de rechazos y
 *   reemplazos vive en `auditoria`, no en filas duplicadas.
 * - `estatus`, `validado_*` y `motivo_rechazo` NO están en `$fillable`: los
 *   escribe únicamente `DocumentoService` dentro de su transacción.
 * - Sin soft delete (supuesto 17): el archivo rechazado permanece como
 *   evidencia hasta ser reemplazado.
 */
class Documento extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'inscripcion_id',
        'tipo_documento_id',
        'ruta',
        'nombre_original',
        'mime',
        'tamano_kb',
        'subido_por',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estatus' => EstatusDocumento::class,
            'tamano_kb' => 'integer',
            'validado_at' => 'datetime',
        ];
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }
}
