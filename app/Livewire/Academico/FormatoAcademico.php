<?php

namespace App\Livewire\Academico;

use App\Models\Acta;
use App\Models\Alumno;
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

    protected $listeners = ['generarFormato'];

    public function generarFormato(string $tipo, ?Alumno $alumno = null, ?Acta $acta = null): void
    {
        Gate::authorize('documento_generado.crear');

        $this->tipo = $tipo;
        $this->alumno = $alumno;
        $this->acta = $acta;

        try {
            $service = app(FormatoService::class);
            $pdfPath = match ($tipo) {
                'kardex', 'boleta', 'historial', 'tira', 'avance' => $service->generar(
                    TipoFormato::from($tipo),
                    $alumno,
                    null,
                    null,
                    [],
                    auth()->user()
                ),
                'acta' => $service->generar(
                    TipoFormato::ACTA,
                    null,
                    null,
                    $acta->grupo,
                    ['acta_id' => $acta->id],
                    auth()->user()
                ),
                default => throw new \InvalidArgumentException("Tipo de formato no válido: {$tipo}"),
            };

            $this->dispatch('formatoGenerado', pdfPath: $pdfPath);
            session()->flash('success', "Formato {$tipo} generado correctamente.");
        } catch (\Throwable $e) {
            $this->addError('generar', $e->getMessage());
        }
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
            ],
        ]);
    }
}