<?php

namespace App\Livewire\Academico;

use App\Models\Acta;
use App\Models\Alumno;
use App\Models\DocumentoGenerado;
use App\Models\Grupo;
use App\Services\HistorialAcademicoService;
use App\Services\FormatoService;
use App\Services\ConsecutivoService;
use App\Services\ActaService;
use App\Enums\TipoFormato;
use App\Enums\TipoConsecutivo;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FormatoAcademico extends Component
{
    public string $tipo = 'kardex';
    public ?Alumno $alumno = null;
    public ?Acta $acta = null;
    public string $busqueda = '';
    /** Registro de trazabilidad del PDF recién emitido (habilita la descarga). */
    public ?int $ultimoDocumentoId = null;

    protected $listeners = ['generarFormato'];

    public function mount(): void
    {
        // Aislamiento de portales (2.0-15): el menú ya usaba esta capacidad;
        // la pantalla no la validaba server-side (DOCENTE/ADMISIONES la veían).
        Gate::authorize('formato_academico.generar');
    }

    public function generarFormato(string $tipo, ?Alumno $alumno = null, ?Acta $acta = null): void
    {
        Gate::authorize('documento_generado.crear');

        $this->tipo = $tipo;
        $this->alumno = $alumno;
        $this->acta = $acta;

        try {
            $emitidoDesde = now();
            $service = app(FormatoService::class);

            // La vista trabaja con claves en minúsculas ('kardex') y el enum sólo
            // admite su backing value ('KARDEX'): `TipoFormato::from($tipo)` lanzaba
            // ValueError y el formato nunca se llegaba a generar.
            $formato = match ($tipo) {
                'kardex' => TipoFormato::KARDEX,
                'boleta' => TipoFormato::BOLETA,
                'historial' => TipoFormato::HISTORIAL,
                'tira' => TipoFormato::TIRA,
                'avance' => TipoFormato::AVANCE,
                'acta' => TipoFormato::ACTA,
                'constancia-terminacion' => TipoFormato::CONSTANCIA_TERMINACION,
                default => throw new \InvalidArgumentException("Tipo de formato no válido: {$tipo}"),
            };

            if ($formato === TipoFormato::ACTA && $acta === null) {
                throw new \InvalidArgumentException('Selecciona el acta de calificaciones para generar el formato.');
            }

            $pdfPath = $formato === TipoFormato::ACTA
                ? $service->generar(
                    $formato,
                    null,
                    null,
                    $acta->grupo,
                    ['acta_id' => $acta->id],
                    auth()->user()
                )
                : $service->generar(
                    $formato,
                    $alumno,
                    null,
                    null,
                    [],
                    auth()->user()
                );

            $this->ultimoDocumentoId = DocumentoGenerado::vincularArchivoEmitido(
                $pdfPath,
                $formato,
                $alumno?->getKey(),
                $emitidoDesde,
            )?->getKey();

            $this->dispatch('formatoGenerado', pdfPath: $pdfPath);
            session()->flash('success', "Formato {$tipo} generado correctamente.");
        } catch (\Throwable $e) {
            $this->addError('generar', $e->getMessage());
        }
    }

    /**
     * Selección desde los combos del formulario.
     *
     * No puede ser `wire:model`: `alumno` y `acta` son modelos tipados y
     * Livewire recibe el id como texto (TypeError "Cannot assign string"). El id
     * llega a la acción y Blade marca la opción con `@selected`.
     */
    public function seleccionarAlumno(string $alumnoId): void
    {
        $this->alumno = $alumnoId !== '' ? Alumno::find($alumnoId) : null;
        $this->resetErrorBag(['alumno']);
    }

    public function seleccionarActa(string $actaId): void
    {
        $this->acta = $actaId !== '' ? Acta::find($actaId) : null;
        $this->resetErrorBag(['acta']);
    }

    public function render()
    {
        return view('livewire.academico.formato-academico', [
            'tipos' => [
                'kardex' => 'Kardex',
                'boleta' => 'Boleta de Calificaciones',
                'historial' => 'Historial Académico',
                'acta' => 'Acta de Calificaciones',
                'tira' => 'Carga/Tira de Materias',
                'avance' => 'Reporte de Avance Académico',
                'constancia-terminacion' => 'Constancia de Terminación',
            ],
        ]);
    }
}