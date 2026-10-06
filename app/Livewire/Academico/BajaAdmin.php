<?php

namespace App\Livewire\Academico;

use App\Models\Baja;
use App\Models\Equivalencia;
use App\Models\Alumno;
use App\Models\InscripcionMateria;
use App\Enums\TipoBaja;
use App\Enums\EstatusEquivalencia;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class BajaAdmin extends Component
{
    use WithPagination;

    public string $busqueda = '';
    public string $filtroTipo = '';
    public string $filtroEstatus = '';
    public string $filtroPeriodo = '';

    public bool $modalBajaAbierto = false;
    public bool $modalReingresoAbierto = false;
    public string $tipoBaja = '';
    public ?Alumno $alumno = null;
    public ?InscripcionMateria $inscripcion = null;
    public string $motivo = '';
    public string $fecha = '';
    public string $reingresoAt = '';
    public ?Baja $baja = null;

    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->filtroTipo = '';
        $this->filtroEstatus = '';
        $this->filtroPeriodo = '';
        $this->resetPage();
    }

    /**
     * Selección desde los combos del modal de baja.
     *
     * No puede ser `wire:model`: `alumno` e `inscripcion` son modelos tipados y
     * Livewire recibe el id como texto (TypeError "Cannot assign string"). El id
     * llega a la acción y Blade marca la opción con `@selected`.
     */
    public function seleccionarAlumno(string $alumnoId): void
    {
        $this->alumno = $alumnoId !== '' ? Alumno::find($alumnoId) : null;
        $this->resetErrorBag(['alumno']);
    }

    public function seleccionarInscripcion(string $inscripcionId): void
    {
        $this->inscripcion = $inscripcionId !== '' ? InscripcionMateria::find($inscripcionId) : null;
        $this->resetErrorBag(['inscripcion']);
    }

    public function abrirModalBaja(string $tipo): void
    {
        Gate::authorize('baja.crear');
        $this->tipoBaja = $tipo;
        $this->alumno = null;
        $this->inscripcion = null;
        $this->motivo = '';
        $this->fecha = now()->format('Y-m-d');
        $this->modalBajaAbierto = true;
    }

    public function abrirModalReingreso(Baja $baja): void
    {
        Gate::authorize('baja.reingreso', $baja);

        if (!$baja->esTemporal() || $baja->tieneReingreso()) {
            $this->addError('reingreso', 'Solo bajas temporales sin reingreso pueden reingresar.');
            return;
        }

        $this->modalReingresoAbierto = true;
        $this->baja = $baja;
        $this->reingresoAt = now()->format('Y-m-d H:i');
    }

    public function registrarBaja(): void
    {
        $this->validate([
            'tipoBaja' => 'required|in:TEMPORAL,DEFINITIVA,MATERIA',
            'alumno' => 'required',
            'motivo' => 'required|string|min:5|max:2000',
            'fecha' => 'required|date',
            'inscripcion' => $this->tipoBaja === 'MATERIA' ? 'required' : 'nullable',
        ]);

        try {
            // Lógica de registro delegada a servicio
            // $service = app(BajaService::class);
            // $service->registrar(...);

            $this->modalBajaAbierto = false;
            $this->reset(['tipoBaja', 'alumno', 'inscripcion', 'motivo', 'fecha']);
            $this->dispatch('bajaRegistrada');
        } catch (\Throwable $e) {
            $this->addError('registrar', $e->getMessage());
        }
    }

    public function autorizarReingreso(): void
    {
        $this->validate([
            'reingresoAt' => 'required|date',
        ]);

        try {
            // $service = app(BajaService::class);
            // $service->reingresar($this->baja, $this->reingresoAt);

            $this->modalReingresoAbierto = false;
            $this->dispatch('reingresoAutorizado');
        } catch (\Throwable $e) {
            $this->addError('reingreso', $e->getMessage());
        }
    }

    public function render()
    {
        $bajas = Baja::with(['alumno', 'inscripcionMateria.grupo.materia', 'registradoPor'])
            ->when($this->busqueda, fn ($q) => $q->whereHas('alumno', fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%")->orWhere('apellidos', 'like', "%{$this->busqueda}%")))
            ->when($this->filtroTipo, fn ($q) => $q->where('tipo', $this->filtroTipo))
            ->when($this->filtroPeriodo, fn ($q) => $q->whereHas('alumno.matriculas', fn ($q) => $q->where('periodo_id', $this->filtroPeriodo)))
            ->orderByDesc('fecha')
            ->paginate(15);

        return view('livewire.academico.baja-admin', [
            'tipos' => TipoBaja::cases(),
            'bajas' => $bajas,
        ]);
    }
}