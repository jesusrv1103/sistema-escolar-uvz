<?php

namespace App\Services;

use App\Enums\EstatusDocumento;
use App\Enums\InscripcionEstatus;
use App\Exceptions\DocumentoInvalidoException;
use App\Models\Documento;
use App\Models\Inscripcion;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Único punto de negocio de documentos (Fase 10, RB-7/FC-6).
 *
 * Tres operaciones, cada una en UNA transacción con ROLLBACK TOTAL:
 *  - `subir()` (los 4 roles; operativo ADMISIONES): valida archivo (≤ 10 MB,
 *    PDF/JPG/JPEG/PNG, MIME real con `finfo`), lo guarda en disco PRIVADO y
 *    hace **upsert por `(inscripcion_id, tipo_documento_id)`** — jamás dos
 *    filas por tipo: la recarga reemplaza ESA MISMA fila, la deja `PENDIENTE`
 *    y limpia validación/rechazo. El archivo nuevo se escribe ANTES de la
 *    transacción y el anterior se elimina SOLO tras el COMMIT (nunca se borra
 *    primero); ante cualquier fallo se compensa borrando el archivo nuevo.
 *  - `aceptar()` (CONTROL_ESCOLAR/ADMIN): exige documento `PENDIENTE` e
 *    inscripción en `DOCUMENT_REVIEW`; si todos los obligatorios quedan
 *    `ACEPTADO` ⇒ `FINAL_REVIEW` vía `InscripcionService::transicionar()`.
 *  - `rechazar()` (CONTROL_ESCOLAR/ADMIN): motivo 5–2000; conserva el archivo
 *    rechazado como evidencia y, si la inscripción está en `DOCUMENT_REVIEW`,
 *    retrocede a `PENDING_DOCUMENTS` con el motivo explícito.
 *
 * La UI NUNCA escribe ruta, metadata, estatus, validado_*, motivo ni
 * `inscripciones.estatus` (siempre vía máquina de estados). Sin información
 * médica en ninguna ruta del servicio (RB-18).
 */
class DocumentoService
{
    /** Tamaño máximo: 10 MB (§4.12/§12.8). */
    private const MAX_ARCHIVO_BYTES = 10485760;

    /** MIME real admitido → extensión con la que se ALMACENA (nunca la original). */
    private const MIME_ADMITIDO = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /** Extensión del nombre original admitida (además del MIME real). */
    private const EXTENSIONES_ADMITIDAS = ['pdf', 'jpg', 'jpeg', 'png'];

    /** Disco privado: fuera de `public/` (riesgo R5). */
    private const DISCO = 'private';

    /** Rate limiting de cargas por usuario (§12.11): 30 cargas / 60 s. */
    private const CARGAS_POR_MINUTO = 30;

    /** Etapas en las que el flujo admite cargas (§7.1/FC-6). */
    private const ETAPAS_DE_CARGA = [
        InscripcionEstatus::PENDING_DOCUMENTS,
        InscripcionEstatus::DOCUMENT_REVIEW,
        InscripcionEstatus::FINAL_REVIEW,
    ];

    public function __construct(
        private readonly AuditoriaService $auditoria,
        private readonly InscripcionService $inscripciones,
    ) {
    }

    /**
     * Recepción de un documento (FC-6): validación → archivo privado →
     * transacción con upsert + auditoría + evaluación de la transición
     * `PENDING_DOCUMENTS → DOCUMENT_REVIEW`.
     *
     * @throws DocumentoInvalidoException si la etapa, el tipo o el archivo no
     *                                    cumplen las reglas de la Fase 10.
     */
    public function subir(Inscripcion $inscripcion, int $tipoDocumentoId, UploadedFile $archivo, ?User $usuario = null): Documento
    {
        Gate::authorize('documento.cargar');
        $usuario ??= auth()->user();

        $this->limitarCarga($usuario);
        $this->validarArchivo($archivo);

        // El archivo se escribe ANTES de la transacción (el filesystem no
        // participa de ella); si algo falla, se compensa abajo.
        [$rutaNueva, $nombreOriginal, $mime, $tamanoKb] = $this->almacenar($archivo);
        $rutaAnterior = null;

        try {
            $documento = DB::transaction(function () use ($inscripcion, $tipoDocumentoId, $usuario, $rutaNueva, $nombreOriginal, $mime, $tamanoKb, &$rutaAnterior): Documento {
                // Lock de la inscripción: serializa cargas concurrentes del expediente.
                $inscripcion = Inscripcion::query()->lockForUpdate()->findOrFail($inscripcion->getKey());
                $this->validarEtapa($inscripcion);

                $tipo = $this->tipoValido($tipoDocumentoId);

                $existente = Documento::query()
                    ->where('inscripcion_id', $inscripcion->getKey())
                    ->where('tipo_documento_id', $tipo->getKey())
                    ->lockForUpdate()
                    ->first();

                $accion = 'documento_subido';
                $antes = [];

                if ($existente !== null) {
                    // Reemplazo: MISMA fila (UNIQUE), trazabilidad en auditoría.
                    $documento = $existente;
                    $rutaAnterior = $existente->ruta;
                    $accion = 'documento_reemplazado';
                    $antes = ['estatus' => $existente->estatus->value];
                } else {
                    $documento = new Documento();
                }

                $documento->forceFill([
                    'inscripcion_id' => $inscripcion->getKey(),
                    'tipo_documento_id' => $tipo->getKey(),
                    'ruta' => $rutaNueva,
                    'nombre_original' => $nombreOriginal,
                    'mime' => $mime,
                    'tamano_kb' => $tamanoKb,
                    'estatus' => EstatusDocumento::PENDIENTE->value,  // el (re)emplazo siempre revalida
                    'validado_por' => null,
                    'validado_at' => null,
                    'motivo_rechazo' => null,
                    'subido_por' => $usuario?->getKey(),
                ])->save();

                $this->auditoria->registrar(
                    $documento,
                    $accion,
                    $antes,
                    ['estatus' => EstatusDocumento::PENDIENTE->value],
                    [
                        'inscripcion_id' => $inscripcion->getKey(),
                        'tipo_documento_id' => $tipo->getKey(),
                        'nombre_original' => $nombreOriginal,
                        'mime' => $mime,
                        'tamano_kb' => $tamanoKb,
                    ],
                    $usuario,
                );

                $this->avanzarSiEstanCargados($inscripcion, $usuario);

                return $documento->refresh();
            });
        } catch (Throwable $e) {
            // Compensación: sin archivos huérfanos; el archivo ANTERIOR no se toca.
            Storage::disk(self::DISCO)->delete($rutaNueva);

            throw $e;
        }

        // COMMIT exitoso: recién ahora se retira el archivo reemplazado.
        if ($rutaAnterior !== null && $rutaAnterior !== $rutaNueva) {
            Storage::disk(self::DISCO)->delete($rutaAnterior);
        }

        return $documento;
    }

    /**
     * Acepta un documento `PENDIENTE` (solo CONTROL_ESCOLAR/ADMIN) mientras la
     * inscripción está en `DOCUMENT_REVIEW`. Si con ello todos los tipos
     * obligatorios quedan `ACEPTADO` ⇒ `DOCUMENT_REVIEW → FINAL_REVIEW` vía
     * `InscripcionService::transicionar()` (nunca asignando estatus).
     */
    public function aceptar(Documento $documento, ?User $usuario = null): Documento
    {
        Gate::authorize('documento.validar');
        $usuario ??= auth()->user();

        return DB::transaction(function () use ($documento, $usuario): Documento {
            $documento = Documento::query()->lockForUpdate()->findOrFail($documento->getKey());
            $inscripcion = Inscripcion::query()->lockForUpdate()->findOrFail($documento->inscripcion_id);

            if ($documento->estatus !== EstatusDocumento::PENDIENTE) {
                throw new DocumentoInvalidoException(sprintf(
                    'Solo se aceptan documentos %s (estatus actual: %s).',
                    EstatusDocumento::PENDIENTE->label(),
                    $documento->estatus->label(),
                ));
            }

            if ($inscripcion->estatus !== InscripcionEstatus::DOCUMENT_REVIEW) {
                throw new DocumentoInvalidoException(sprintf(
                    'La inscripción debe estar en %s para validar documentos (estatus actual: %s).',
                    InscripcionEstatus::DOCUMENT_REVIEW->label(),
                    $inscripcion->estatus->label(),
                ));
            }

            if (! Storage::disk(self::DISCO)->exists($documento->ruta)) {
                throw new DocumentoInvalidoException('El archivo del documento no está disponible en el almacenamiento privado.');
            }

            $documento->forceFill([
                'estatus' => EstatusDocumento::ACEPTADO->value,
                'validado_por' => $usuario?->getKey(),
                'validado_at' => now(),
                'motivo_rechazo' => null,
            ])->save();

            $this->auditoria->registrar(
                $documento,
                'documento_aceptado',
                ['estatus' => EstatusDocumento::PENDIENTE->value],
                ['estatus' => EstatusDocumento::ACEPTADO->value],
                ['inscripcion_id' => $inscripcion->getKey(), 'tipo_documento_id' => $documento->tipo_documento_id],
                $usuario,
            );

            if ($this->obligatoriosTodosAceptados($inscripcion)) {
                $this->inscripciones->transicionar($inscripcion, InscripcionEstatus::FINAL_REVIEW, null, $usuario);
            }

            return $documento->refresh();
        });
    }

    /**
     * Rechaza un documento `PENDIENTE` con motivo (5–2000). El archivo NO se
     * borra (evidencia histórica hasta el reemplazo) y, si la inscripción
     * está en `DOCUMENT_REVIEW`, retrocede a `PENDING_DOCUMENTS` usando el
     * motivo como motivo explícito de la transición (§7.3.2).
     */
    public function rechazar(Documento $documento, string $motivo, ?User $usuario = null): Documento
    {
        Gate::authorize('documento.validar');
        $usuario ??= auth()->user();

        $motivo = trim($motivo);

        if ($motivo === '' || mb_strlen($motivo) < 5 || mb_strlen($motivo) > 2000) {
            throw new DocumentoInvalidoException('El motivo de rechazo es obligatorio y debe tener entre 5 y 2000 caracteres.');
        }

        return DB::transaction(function () use ($documento, $motivo, $usuario): Documento {
            $documento = Documento::query()->lockForUpdate()->findOrFail($documento->getKey());
            $inscripcion = Inscripcion::query()->lockForUpdate()->findOrFail($documento->inscripcion_id);

            if ($documento->estatus !== EstatusDocumento::PENDIENTE) {
                throw new DocumentoInvalidoException(sprintf(
                    'Solo se rechazan documentos %s (estatus actual: %s).',
                    EstatusDocumento::PENDIENTE->label(),
                    $documento->estatus->label(),
                ));
            }

            if (in_array($inscripcion->estatus, [InscripcionEstatus::ENROLLED, InscripcionEstatus::CANCELLED], true)) {
                throw new DocumentoInvalidoException('No se validan documentos de una inscripción finalizada o cancelada.');
            }

            $documento->forceFill([
                'estatus' => EstatusDocumento::RECHAZADO->value,
                'motivo_rechazo' => $motivo,
                'validado_por' => $usuario?->getKey(),
                'validado_at' => now(),
            ])->save();

            $this->auditoria->registrar(
                $documento,
                'documento_rechazado',
                ['estatus' => EstatusDocumento::PENDIENTE->value],
                ['estatus' => EstatusDocumento::RECHAZADO->value],
                [
                    'inscripcion_id' => $inscripcion->getKey(),
                    'tipo_documento_id' => $documento->tipo_documento_id,
                    'motivo' => $motivo,
                ],
                $usuario,
            );

            if ($inscripcion->estatus === InscripcionEstatus::DOCUMENT_REVIEW) {
                // Retroceso con motivo explícito: ADMISIONES podrá reemplazarlo.
                $this->inscripciones->transicionar($inscripcion, InscripcionEstatus::PENDING_DOCUMENTS, $motivo, $usuario);
            }

            return $documento->refresh();
        });
    }

    /**
     * Guarda el archivo en el disco PRIVADO con ruta generada por el sistema
     * (UUID + extensión derivada del MIME real). El nombre original se
     * conserva SOLO como metadato, jamás como ruta.
     *
     * @return array{0: string, 1: string, 2: string, 3: int} [ruta, nombre original, mime, KB]
     */
    private function almacenar(UploadedFile $archivo): array
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $archivo->getRealPath()) : false;

        if ($finfo !== false) {
            finfo_close($finfo);
        }

        $extension = is_string($mime) ? (self::MIME_ADMITIDO[$mime] ?? null) : null;

        if ($extension === null) {
            throw new DocumentoInvalidoException('El contenido del archivo no corresponde a un PDF, JPG o PNG válido.');
        }

        $ruta = $archivo->storeAs(
            'documentos',
            Str::uuid()->toString().'.'.$extension,
            self::DISCO,
        );

        if ($ruta === false) {
            throw new DocumentoInvalidoException('No fue posible almacenar el documento.');
        }

        return [
            $ruta,
            Str::limit(basename($archivo->getClientOriginalName()), 255, ''),
            $mime,
            (int) ceil($archivo->getSize() / 1024),
        ];
    }

    /**
     * Valida el archivo ANTES de tocar nada: recepción correcta, ≤ 10 MB,
     * extensión admitida y **MIME real con `finfo`** (nunca solo la extensión).
     */
    private function validarArchivo(UploadedFile $archivo): void
    {
        if (! $archivo->isValid()) {
            throw new DocumentoInvalidoException('El archivo no se pudo recibir correctamente.');
        }

        if ($archivo->getSize() > self::MAX_ARCHIVO_BYTES) {
            throw new DocumentoInvalidoException('El archivo excede el tamaño máximo de 10 MB (10240 KB).');
        }

        // La extensión se toma del nombre original declarado (el nombre del
        // disco/tmp no es confiable); el MIME real se valida aparte con finfo.
        $extension = strtolower(pathinfo($archivo->getClientOriginalName(), PATHINFO_EXTENSION));

        if (! in_array($extension, self::EXTENSIONES_ADMITIDAS, true)) {
            throw new DocumentoInvalidoException('Formato de documento no admitido: usa PDF, JPG, JPEG o PNG.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $archivo->getRealPath()) : false;

        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (! is_string($mime) || ! isset(self::MIME_ADMITIDO[$mime])) {
            throw new DocumentoInvalidoException('El contenido del archivo no corresponde a un PDF, JPG o PNG válido.');
        }
    }

    /** Etapa del flujo compatible con la recepción de documentos (FC-6). */
    private function validarEtapa(Inscripcion $inscripcion): void
    {
        if (! in_array($inscripcion->estatus, self::ETAPAS_DE_CARGA, true)) {
            throw new DocumentoInvalidoException(sprintf(
                'La inscripción está en %s: los documentos solo se cargan en %s, %s o %s.',
                $inscripcion->estatus->label(),
                InscripcionEstatus::PENDING_DOCUMENTS->label(),
                InscripcionEstatus::DOCUMENT_REVIEW->label(),
                InscripcionEstatus::FINAL_REVIEW->label(),
            ));
        }
    }

    /** El tipo debe existir, estar activo y no estar eliminado (soft delete). */
    private function tipoValido(int $tipoDocumentoId): TipoDocumento
    {
        $tipo = TipoDocumento::query()->find($tipoDocumentoId);

        if ($tipo === null) {
            throw new DocumentoInvalidoException('El tipo de documento no existe o fue eliminado del catálogo.');
        }

        if (! $tipo->activo) {
            throw new DocumentoInvalidoException(sprintf(
                'El tipo de documento "%s" no está activo: no admite cargas nuevas.',
                $tipo->nombre,
            ));
        }

        return $tipo;
    }

    /**
     * Tras cada carga: si la inscripción está en `PENDING_DOCUMENTS` y TODOS
     * los tipos obligatorios activos tienen documento cargado ⇒
     * `DOCUMENT_REVIEW` vía `InscripcionService::transicionar()` (RB-7).
     */
    private function avanzarSiEstanCargados(Inscripcion $inscripcion, ?User $usuario): void
    {
        if ($inscripcion->estatus !== InscripcionEstatus::PENDING_DOCUMENTS) {
            return;
        }

        if (! $this->obligatoriosTodosCargados($inscripcion)) {
            return;
        }

        $this->inscripciones->transicionar($inscripcion, InscripcionEstatus::DOCUMENT_REVIEW, null, $usuario);
    }

    /**
     * Tipos que cuentan como requisito: `obligatorio = true`, activos y no
     * eliminados (un tipo desactivado deja de ser requisito; su histórico se
     * conserva).
     *
     * @return list<int>
     */
    private function requisitos(): array
    {
        return TipoDocumento::query()
            ->where('obligatorio', true)
            ->where('activo', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** ¿Todos los obligatorios tienen un documento cargado? */
    private function obligatoriosTodosCargados(Inscripcion $inscripcion): bool
    {
        $requisitos = $this->requisitos();

        if ($requisitos === []) {
            return true;
        }

        $cargados = Documento::query()
            ->where('inscripcion_id', $inscripcion->getKey())
            ->whereIn('tipo_documento_id', $requisitos)
            ->pluck('tipo_documento_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_diff($requisitos, $cargados) === [];
    }

    /**
     * ¿Todos los obligatorios están `ACEPTADO`? (guarda de FINAL_REVIEW)
     *
     * Reutilizada por `InscripcionService::requisitosPendientes()` (guarda
     * RB-8.2, Fase 12): una única definición de "obligatorio aplicable"
     * (`obligatorio = true`, activo y no eliminado) para todo el sistema.
     */
    public function obligatoriosTodosAceptados(Inscripcion $inscripcion): bool
    {
        $requisitos = $this->requisitos();

        if ($requisitos === []) {
            return true;
        }

        $aceptados = Documento::query()
            ->where('inscripcion_id', $inscripcion->getKey())
            ->where('estatus', EstatusDocumento::ACEPTADO->value)
            ->whereIn('tipo_documento_id', $requisitos)
            ->pluck('tipo_documento_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_diff($requisitos, $aceptados) === [];
    }

    /** Rate limiting de cargas de archivos (§12.11), server-side. */
    private function limitarCarga(?User $usuario): void
    {
        $llave = 'documentos.cargar.'.($usuario?->getKey() ?? 'anonimo');

        if (RateLimiter::tooManyAttempts($llave, self::CARGAS_POR_MINUTO)) {
            throw new DocumentoInvalidoException('Demasiadas cargas de documentos: intenta de nuevo en unos segundos.');
        }

        RateLimiter::hit($llave, 60);
    }
}
