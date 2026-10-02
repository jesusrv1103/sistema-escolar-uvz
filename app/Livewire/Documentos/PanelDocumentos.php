<?php

namespace App\Livewire\Documentos;

use App\Enums\InscripcionEstatus;
use App\Exceptions\DocumentoInvalidoException;
use App\Models\Inscripcion;
use App\Models\TipoDocumento;
use App\Services\DocumentoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Panel de documentos del expediente (Fase 10, FC-6/RB-7).
 *
 * - Muestra por tipo: obligatorio/opcional, estatus, nombre original, fecha
 *   de carga, responsable, motivo de rechazo y acciones según permisos.
 * - Carga/reemplazo (los 4 roles; operativo ADMISIONES) y aceptar/rechazar
 *   (CONTROL_ESCOLAR/ADMIN) **siempre** server-side vía `DocumentoService`;
 *   la UI no escribe ruta, metadata, estatus ni estados de inscripción.
 * - Muestra también el histórico de tipos desactivados/eliminados (§20).
 * - Nunca carga ni muestra información médica (RB-18); sin dashboard nuevo.
 */
class PanelDocumentos extends Component
{
    use WithFileUploads;

    public int $inscripcionId;

    /** Tipo seleccionado para la carga/reemplazo en curso (null = sin formulario). */
    public ?int $tipoCarga = null;

    /** @var \Illuminate\Http\UploadedFile|null temporal de Livewire */
    public $archivo = null;

    /** Documento en curso de rechazo (null = sin formulario). */
    public ?int $documentoRechazo = null;

    public string $motivoRechazo = '';

    public function mount(Inscripcion $inscripcion): void
    {
        Gate::authorize('view', $inscripcion);
        $this->inscripcionId = $inscripcion->getKey();
    }

    /** Abre el formulario de carga/reemplazo de un tipo concreto. */
    public function abrirCarga(int $tipoId): void
    {
        Gate::authorize('documento.cargar');
        $this->inscripcionOrFail();
        $this->tipoCarga = $tipoId;
        $this->reset('archivo');
        $this->resetErrorBag();
    }

    public function cerrarCarga(): void
    {
        $this->tipoCarga = null;
        $this->reset('archivo');
        $this->resetErrorBag();
    }

    /** Recibe o reemplaza el archivo del tipo seleccionado (servicio único). */
    public function cargar(): void
    {
        // Autorización server-side (el @can de la vista solo complementa).
        Gate::authorize('documento.cargar');
        $inscripcion = $this->inscripcionOrFail();

        $this->validate([
            'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        if ($this->tipoCarga === null) {
            $this->addError('archivo', 'Selecciona un tipo de documento para cargar.');

            return;
        }

        try {
            app(DocumentoService::class)->subir($inscripcion, $this->tipoCarga, $this->archivo);
        } catch (DocumentoInvalidoException $e) {
            $this->addError('archivo', $e->getMessage());

            return;
        }

        $this->cerrarCarga();
        session()->flash('documentos', 'Documento recibido correctamente.');
    }

    public function aceptar(int $documentoId): void
    {
        Gate::authorize('documento.validar');
        $inscripcion = $this->inscripcionOrFail();

        try {
            app(DocumentoService::class)->aceptar(
                // El documento debe pertenecer a ESTA inscripción (binding
                // horizontal: jamás se opera sobre expedientes ajenos).
                $inscripcion->documentos()->findOrFail($documentoId),
            );
        } catch (DocumentoInvalidoException $e) {
            $this->addError('panel', $e->getMessage());

            return;
        }

        $this->resetErrorBag();
        session()->flash('documentos', 'Documento aceptado.');
    }

    public function abrirRechazo(int $documentoId): void
    {
        Gate::authorize('documento.validar');
        $this->inscripcionOrFail()->documentos()->findOrFail($documentoId);
        $this->documentoRechazo = $documentoId;
        $this->motivoRechazo = '';
        $this->resetErrorBag();
    }

    public function cerrarRechazo(): void
    {
        $this->documentoRechazo = null;
        $this->motivoRechazo = '';
        $this->resetErrorBag();
    }

    public function rechazar(): void
    {
        Gate::authorize('documento.validar');
        $this->inscripcionOrFail();

        $this->validate([
            'motivoRechazo' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        if ($this->documentoRechazo === null) {
            $this->addError('motivoRechazo', 'Selecciona un documento para rechazar.');

            return;
        }

        try {
            app(DocumentoService::class)->rechazar(
                $this->inscripcionOrFail()->documentos()->findOrFail($this->documentoRechazo),
                $this->motivoRechazo,
            );
        } catch (DocumentoInvalidoException $e) {
            $this->addError('motivoRechazo', $e->getMessage());

            return;
        }

        $this->cerrarRechazo();
        session()->flash('documentos', sprintf(
            'Documento rechazado: el expediente regresa a %s.',
            InscripcionEstatus::PENDING_DOCUMENTS->label(),
        ));
    }

    public function render()
    {
        $inscripcion = $this->inscripcionOrFail();

        $documentos = $inscripcion->documentos()
            ->with(['subidoPor:id,name', 'validadoPor:id,name'])
            ->get()
            ->keyBy('tipo_documento_id');

        // Tipos activos (catálogo vigente): un tipo desactivado NO es fila
        // activa (§20) y se resuelve abajo como histórico.
        $tipos = TipoDocumento::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $filas = $tipos->map(fn (TipoDocumento $tipo): array => [
            'tipo' => $tipo,
            'documento' => $documentos->get($tipo->id),
            'historico' => false,
        ])->values();

        // §20: documentos cuyo tipo quedó desactivado o eliminado siguen
        // visibles como histórico (la FK y los datos nunca se borran).
        $idsActivos = $tipos->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach ($documentos as $clave => $documento) {
            if (in_array((int) $clave, $idsActivos, true)) {
                continue;
            }

            $filas->push([
                'tipo' => TipoDocumento::withTrashed()->find($documento->tipo_documento_id),
                'documento' => $documento,
                'historico' => true,
            ]);
        }

        return view('livewire.documentos.panel-documentos', [
            'inscripcion' => $inscripcion,
            'filas' => $filas,
            // Carga permitida solo en las etapas del flujo de documentos.
            'puedeCargar' => Gate::allows('documento.cargar')
                && in_array($inscripcion->estatus, [
                    InscripcionEstatus::PENDING_DOCUMENTS,
                    InscripcionEstatus::DOCUMENT_REVIEW,
                    InscripcionEstatus::FINAL_REVIEW,
                ], true),
            'puedeValidar' => Gate::allows('documento.validar'),
            // Nunca se pasa información médica al view (RB-18).
        ]);
    }

    private function inscripcionOrFail(): Inscripcion
    {
        return Inscripcion::findOrFail($this->inscripcionId);
    }
}
