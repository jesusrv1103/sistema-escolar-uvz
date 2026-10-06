<?php

namespace App\Livewire\Alumnos;

use App\Models\Documento;
use App\Models\DocumentoGenerado;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Portal de alumno — documentos de su propio expediente (2.0-16).
 *
 * Dos fuentes distintas (igual que en el backoffice):
 * - expediente: `documentos` de sus inscripciones (descarga vía
 *   `DocumentoPolicy::view`, que permite al ALUMNO sólo lo suyo);
 * - formatos emitidos: `documentos_generados` con `alumno_id` propio
 *   (`DocumentoGeneradoPolicy`, rama ALUMNO).
 * Listados acotados por SQL a `auth()->user()->alumno`, nunca por id de ruta.
 */
#[Layout('layouts.app')]
class AlumnoDocumentos extends Component
{
    public function mount(): void
    {
        Gate::authorize('portal_alumno.acceder');
    }

    public function render()
    {
        $alumno = auth()->user()->alumno;

        $expediente = $alumno
            ? Documento::whereHas('inscripcion', fn ($q) => $q->where('alumno_id', $alumno->id))
                ->with(['tipoDocumento', 'inscripcion'])
                ->orderByDesc('created_at')
                ->get()
            : collect();

        $generados = $alumno
            ? DocumentoGenerado::where('alumno_id', $alumno->id)
                ->orderByDesc('emitido_at')
                ->get()
            : collect();

        return view('livewire.alumnos.alumno-documentos', [
            'alumno' => $alumno,
            'expediente' => $expediente,
            'generados' => $generados,
        ]);
    }
}
