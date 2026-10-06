<?php

namespace App\Models;

use App\Models\Concerns\HasAudit;
use App\Enums\TipoFormato;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Trazabilidad de documentos generados (formatos académicos).
 *
 * Registra la emisión de formatos académicos internos UVZ.
 * No almacena el PDF binario, solo la trazabilidad y el snapshot de datos.
 */
class DocumentoGenerado extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'documentos_generados';

    /** @var list<string> */
    protected $fillable = [
        'alumno_id',
        'matricula_periodo_id',
        'grupo_id',
        'tipo',
        'folio',
        'emitido_por',
        'emitido_at',
        'snapshot_datos',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoFormato::class,
            'emitido_at' => 'datetime',
            'snapshot_datos' => 'array',
        ];
    }

    /**
     * Alumno al que se emitió el documento.
     */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /**
     * Matrícula del periodo (si aplica).
     */
    public function matriculaPeriodo(): BelongsTo
    {
        return $this->belongsTo(MatriculaPeriodo::class, 'matricula_periodo_id');
    }

    /**
     * Grupo (si aplica).
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /**
     * Usuario que emitió el documento.
     */
    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    /**
     * Obtiene el tipo de formato como enum.
     */
    public function getTipoEnum(): TipoFormato
    {
        return TipoFormato::from($this->tipo);
    }

    /**
     * Verifica si el documento tiene folio asignado.
     */
    public function tieneFolio(): bool
    {
        return $this->folio !== null;
    }

    /**
     * Ruta del PDF en el disco privado, si está registrada.
     *
     * La tabla guarda trazabilidad y no el binario: `FormatoService::generar()`
     * devuelve la ruta al emitir y ésta se conserva en `snapshot_datos['archivo']`
     * (backfill directo de la columna `path` cuando se apruebe esa migración).
     * `path` se lee como respaldo por si la columna llega a existir.
     */
    public function rutaArchivo(): ?string
    {
        $ruta = $this->snapshot_datos['archivo'] ?? $this->attributes['path'] ?? null;

        return is_string($ruta) && $ruta !== '' ? $ruta : null;
    }

    /**
     * Registra la ruta del PDF emitido junto a la trazabilidad del documento.
     */
    public function registrarRutaArchivo(string $ruta): void
    {
        $datos = is_array($this->snapshot_datos) ? $this->snapshot_datos : [];
        $datos['archivo'] = $ruta;

        $this->forceFill(['snapshot_datos' => $datos])->save();
    }

    /**
     * Vincula el PDF recién generado con su registro de trazabilidad.
     *
     * `FormatoService::generar()` devuelve la ruta pero no el id del registro
     * creado, así que se localiza la fila emitida en esta misma petición
     * (usuario + tipo + alumno + instante de emisión) para asociarle la ruta
     * y habilitar la descarga desde la vista que la solicitó.
     *
     * @param  \Illuminate\Support\Carbon|\Carbon\CarbonInterface|null  $desde
     */
    public static function vincularArchivoEmitido(
        string $ruta,
        TipoFormato $tipo,
        ?int $alumnoId = null,
        $desde = null,
    ): ?self {
        $registro = self::query()
            ->where('emitido_por', auth()->id())
            ->where('tipo', $tipo->value)
            ->when($alumnoId !== null, fn ($q) => $q->where('alumno_id', $alumnoId))
            ->when($desde !== null, fn ($q) => $q->where('emitido_at', '>=', $desde))
            ->latest('id')
            ->first();

        $registro?->registrarRutaArchivo($ruta);

        return $registro;
    }
}