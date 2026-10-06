<?php

namespace App\Livewire\Academico;

use App\Models\Equivalencia;
use App\Models\Alumno;
use App\Models\Materia;
use App\Enums\EstatusEquivalencia;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class EquivalenciaAdmin extends Component
{
    use WithPagination;

    public string $busqueda = '';
    public string $filtroEstatus = '';

    public bool $modalAbierto = false;
    public ?Equivalencia $equivalencia = null;
    public ?Alumno $alumno = null;
    public ?Materia $materia = null;
    public string $origen = '';
    public int $creditos = 0;
    public string $dictamen = '';
    public EstatusEquivalencia $estatus = EstatusEquivalencia::PENDIENTE;

    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->filtroEstatus = '';
        $this->resetPage();
    }

    /**
     * Selección desde los combos del formulario.
     *
     * No puede ser `wire:model`: `alumno` y `materia` son modelos tipados y
     * Livewire recibe el id como texto (TypeError "Cannot assign string"). El id
     * llega a la acción y Blade marca la opción con `@selected`.
     */
    public function seleccionarAlumno(string $alumnoId): void
    {
        $this->alumno = $alumnoId !== '' ? Alumno::find($alumnoId) : null;
        $this->resetErrorBag(['alumno']);
    }

    public function seleccionarMateria(string $materiaId): void
    {
        $this->materia = $materiaId !== '' ? Materia::find($materiaId) : null;
        $this->resetErrorBag(['materia']);
    }

    public function abrirModalCrear(): void
    {
        Gate::authorize('equivalencia.crear');
        $this->resetFormulario();
        $this->estatus = EstatusEquivalencia::PENDIENTE;
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(Equivalencia $equivalencia): void
    {
        Gate::authorize('equivalencia.actualizar', $equivalencia);
        $this->equivalencia = $equivalencia;
        $this->alumno = $equivalencia->alumno;
        $this->materia = $equivalencia->materia;
        $this->origen = $equivalencia->origen;
        $this->creditos = $equivalencia->creditos;
        $this->dictamen = $equivalencia->dictamen;
        $this->estatus = $equivalencia->estatus;
        $this->modalAbierto = true;
    }

    public function resetFormulario(): void
    {
        $this->equivalencia = null;
        $this->alumno = null;
        $this->materia = null;
        $this->origen = '';
        $this->creditos = 0;
        $this->dictamen = '';
        $this->estatus = EstatusEquivalencia::PENDIENTE;
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $this->validate([
            'alumno' => 'required',
            'materia' => 'nullable',
            'origen' => 'required|string|max:500',
            'creditos' => 'required|integer|min:0|max:20',
            'dictamen' => 'nullable|string|max:2000',
            'estatus' => ['required', Rule::enum(EstatusEquivalencia::class)],
        ]);

        try {
            if ($this->equivalencia) {
                $this->equivalencia->update([
                    'alumno_id' => $this->alumno->id,
                    'materia_id' => $this->materia?->id,
                    'origen' => $this->origen,
                    'creditos' => $this->creditos,
                    'dictamen' => $this->dictamen,
                    'estatus' => $this->estatus,
                    'autorizada_por' => auth()->id(),
                    'fecha_dictamen' => now()->format('Y-m-d'),
                ]);

                session()->flash('success', 'Equivalencia actualizada correctamente.');
            } else {
                Equivalencia::create([
                    'alumno_id' => $this->alumno->id,
                    'materia_id' => $this->materia?->id,
                    'origen' => $this->origen,
                    'creditos' => $this->creditos,
                    'dictamen' => $this->dictamen,
                    'estatus' => $this->estatus,
                    'autorizada_por' => auth()->id(),
                    'fecha_dictamen' => now()->format('Y-m-d'),
                ]);

                session()->flash('success', 'Equivalencia registrada correctamente.');
            }

            $this->modalAbierto = false;
            $this->resetFormulario();
            $this->dispatch('equivalenciaRegistrada');
        } catch (\Throwable $e) {
            $this->addError('guardar', $e->getMessage());
        }
    }

    public function render()
    {
        $equivalencias = Equivalencia::with(['alumno', 'materia', 'autorizadaPor'])
            ->when($this->busqueda, fn ($q) => $q->whereHas('alumno', fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%")->orWhere('apellidos', 'like', "%{$this->busqueda}%")))
            ->when($this->filtroEstatus, fn ($q) => $q->where('estatus', $this->filtroEstatus))
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.academico.equivalencia-admin', [
            'equivalencias' => $equivalencias,
        ]);
    }
}