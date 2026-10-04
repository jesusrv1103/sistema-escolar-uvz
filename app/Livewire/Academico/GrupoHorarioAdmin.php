<?php

namespace App\Livewire\Academico;

use App\Enums\DiaSemana;
use App\Models\Aula;
use App\Models\Docente;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Materia;
use App\Models\Periodo;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD de Horarios de Grupo (solo ADMIN).
 */
#[Layout('layouts.app')]
class GrupoHorarioAdmin extends Component
{
    use WithPagination;

    public string $busqueda = '';
    public string $filtroGrupo = '';
    public string $filtroDia = '';

    public ?Grupo $grupo = null;

    // Modal crear/editar
    public bool $modalAbierto = false;
    public ?GrupoHorario $horarioEnEdicion = null;
    public string $dia = '';
    public string $horaInicio = '';
    public string $horaFin = '';

    protected $listeners = ['horarioCreado', 'horarioActualizado'];

    public function mount(?Grupo $grupo = null): void
    {
        Gate::authorize('grupo_horario.create');
        $this->grupo = $grupo;
    }

    // --- Modal Crear/Editar ---

    public function abrirModalCrear(): void
    {
        $this->resetFormulario();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(GrupoHorario $horario): void
    {
        Gate::authorize('grupo_horario.update', $horario);

        $this->horarioEnEdicion = $horario;
        $this->dia = $horario->dia->value;
        $this->horaInicio = $horario->hora_inicio->format('H:i');
        $this->horaFin = $horario->hora_fin->format('H:i');
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetFormulario();
    }

    private function resetFormulario(): void
    {
        $this->horarioEnEdicion = null;
        $this->dia = '';
        $this->horaInicio = '';
        $this->horaFin = '';
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $this->validate([
            'dia' => ['required', 'string', 'in:LUNES,MARTES,MIERCOLES,JUEVES,VIERNES,SABADO'],
            'horaInicio' => ['required', 'date_format:H:i'],
            'horaFin' => ['required', 'date_format:H:i', 'after:horaInicio'],
        ]);

        if ($this->horarioEnEdicion === null) {
            Gate::authorize('grupo_horario.create');
            $this->crear();
        } else {
            Gate::authorize('grupo_horario.update', $this->horarioEnEdicion);
            $this->actualizar();
        }
    }

    private function crear(): void
    {
        if (!$this->grupo) {
            $this->addError('grupo', 'Debe seleccionar un grupo.');
            return;
        }

        // Validar conflictos de horario
        $conflicto = $this->validarConflictos(null);
        if ($conflicto) {
            $this->addError('horaInicio', $conflicto);
            return;
        }

        $horario = $this->grupo->horarios()->create([
            'dia' => $this->dia,
            'hora_inicio' => $this->horaInicio,
            'hora_fin' => $this->horaFin,
        ]);

        session()->flash('success', "Horario creado correctamente.");
        $this->cerrarModal();
        $this->dispatch('horarioCreado');
    }

    private function actualizar(): void
    {
        // Validar conflictos de horario (excluyendo el actual)
        $conflicto = $this->validarConflictos($this->horarioEnEdicion->id);
        if ($conflicto) {
            $this->addError('horaInicio', $conflicto);
            return;
        }

        $this->horarioEnEdicion->update([
            'dia' => $this->dia,
            'hora_inicio' => $this->horaInicio,
            'hora_fin' => $this->horaFin,
        ]);

        session()->flash('success', "Horario actualizado correctamente.");
        $this->cerrarModal();
        $this->dispatch('horarioActualizado');
    }

    private function validarConflictos(?int $excluirId = null): ?string
    {
        if (!$this->grupo) {
            return null;
        }

        // Validar solapamiento de aula
        if ($this->grupo->aula_id) {
            $conflictoAula = GrupoHorario::whereHas('grupo', fn ($q) => $q->where('aula_id', $this->grupo->aula_id))
                ->where('dia', $this->dia)
                ->where(function ($q) {
                    $q->where('hora_inicio', '<', $this->horaFin)
                        ->where('hora_fin', '>', $this->horaInicio);
                })
                ->when($excluirId ?? null, fn ($q, $id) => $q->where('id', '!=', $id))
                ->exists();

            if ($conflictoAula) {
                return 'Ya existe un horario en la misma aula que se solapa en este horario.';
            }
        }

        // Validar solapamiento de docente
        if ($this->grupo->docente_id) {
            $conflictoDocente = GrupoHorario::whereHas('grupo', fn ($q) => $q->where('docente_id', $this->grupo->docente_id))
                ->where('dia', $this->dia)
                ->where(function ($q) {
                    $q->where('hora_inicio', '<', $this->horaFin)
                        ->where('hora_fin', '>', $this->horaInicio);
                })
                ->when($excluirId ?? null, fn ($q, $id) => $q->where('id', '!=', $id))
                ->exists();

            if ($conflictoDocente) {
                return 'El docente ya tiene un horario que se solapa en este horario.';
            }
        }

        return null;
    }

    public function eliminar(GrupoHorario $horario): void
    {
        Gate::authorize('grupo_horario.delete', $horario);

        $horario->delete();

        session()->flash('success', "Horario eliminado correctamente.");
    }

    public function render()
    {
        $horarios = $this->grupo
            ? $this->grupo->horarios()
                ->when($this->busqueda, fn ($q) => $q->where('dia', 'like', "%{$this->busqueda}%"))
                ->when($this->filtroDia !== '', fn ($q) => $q->where('dia', $this->filtroDia))
                ->when($this->filtroGrupo, fn ($q) => $q->where('grupo_id', $this->filtroGrupo))
                ->orderBy('dia')
                ->orderBy('hora_inicio')
                ->paginate(15)
            : GrupoHorario::query()
                ->when($this->busqueda, fn ($q) => $q->where('dia', 'like', "%{$this->busqueda}%"))
                ->orderBy('grupo_id')
                ->orderBy('dia')
                ->orderBy('hora_inicio')
                ->paginate(15);

        $dias = DiaSemana::cases();
        $grupos = Grupo::with(['materia', 'periodo'])->orderBy('clave')->get();

        return view('livewire.academico.grupo-horario', [
            'grupo' => $this->grupo,
            'horarios' => $horarios,
            'dias' => $dias,
            'grupos' => $grupos,
        ]);
    }
}