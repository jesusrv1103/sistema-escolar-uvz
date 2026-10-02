<?php

namespace App\Livewire\Alumnos;

use App\Models\Alumno;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Listado y búsqueda de alumnos (§9). NO carga ni muestra información médica.
 */
class ListaAlumnos extends Component
{
    public string $busqueda = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Alumno::class);
    }

    public function render()
    {
        // select explícito + sin relación informacionMedica: la info médica
        // nunca aparece en listados o búsquedas generales (RB-18).
        return view('livewire.alumnos.lista-alumnos', [
            'alumnos' => Alumno::query()
                ->buscar($this->busqueda)
                ->orderBy('apellidos')
                ->orderBy('nombre')
                ->limit(50)
                ->get(['id', 'numero_control', 'nombre', 'apellidos', 'curp', 'fecha_nacimiento', 'correo', 'telefono']),
            'puedeCrear' => Gate::allows('create', Alumno::class),
        ]);
    }
}
