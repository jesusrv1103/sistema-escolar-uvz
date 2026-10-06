<?php

namespace App\Livewire\Alumnos;

use App\Models\MatriculaPeriodo;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Portal de alumno — horario de la matrícula activa (2.0-16).
 *
 * Sólo lectura, de los grupos en los que el propio alumno está CURSANDO en la
 * matrícula activa. No genera el formato HORARIO (eso exige
 * `formato_academico.generar`); sólo muestra la información.
 */
#[Layout('layouts.app')]
class AlumnoHorario extends Component
{
    public function mount(): void
    {
        Gate::authorize('portal_alumno.acceder');
    }

    public function render()
    {
        $alumno = auth()->user()->alumno;

        $matricula = $alumno
            ? MatriculaPeriodo::where('alumno_id', $alumno->id)
                ->activas()
                ->with('periodo')
                ->orderByDesc('periodo_id')
                ->first()
            : null;

        $inscripciones = $matricula
            ? $matricula->inscripcionesCursando()
                ->with(['grupo.materia', 'grupo.horarios', 'grupo.aula', 'grupo.periodo'])
                ->get()
            : collect();

        return view('livewire.alumnos.alumno-horario', [
            'alumno' => $alumno,
            'matricula' => $matricula,
            'inscripciones' => $inscripciones,
        ]);
    }
}
