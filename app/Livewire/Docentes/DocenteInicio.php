<?php

namespace App\Livewire\Docentes;

use App\Models\Grupo;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Portal de docente — inicio (2.0-15).
 *
 * Sólo muestra los grupos asignados al perfil de docente del usuario
 * (`docentes.user_id`): el DOCENTE nunca ve grupos ajenos ni pantallas
 * administrativas. Sin perfil vinculado se muestra un estado vacío (no se
 * auto-crean perfiles ni usuarios).
 */
#[Layout('layouts.app')]
class DocenteInicio extends Component
{
    public function mount(): void
    {
        Gate::authorize('portal_docente.acceder');
    }

    public function render()
    {
        $usuario = auth()->user();
        $docente = $usuario->docente;

        $grupos = $docente
            ? Grupo::with(['materia', 'periodo', 'aula', 'horarios'])
                ->withCount([
                    'inscripcionesMaterias as alumnos_inscritos' => fn ($q) => $q->where(
                        'estatus',
                        \App\Enums\EstatusInscripcionMateria::CURSANDO->value
                    ),
                ])
                ->where('docente_id', $docente->id)
                ->where('activo', true)
                ->orderByDesc('periodo_id')
                ->orderBy('clave')
                ->get()
            : collect();

        return view('livewire.docentes.docente-inicio', [
            'docente' => $docente,
            'grupos' => $grupos,
        ]);
    }
}
