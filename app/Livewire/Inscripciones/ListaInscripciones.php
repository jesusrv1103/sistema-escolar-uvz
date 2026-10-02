<?php

namespace App\Livewire\Inscripciones;

use App\Enums\InscripcionEstatus;
use App\Models\Inscripcion;
use App\Models\Periodo;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Listado operativo de inscripciones con filtros (§9, Fase 6).
 *
 * - Solo datos operativos: alumno, programa, periodo, estatus, fecha y
 *   acción de continuar/ver. NO incluye información médica (RB-18).
 * - Filtros mínimos aprobados: búsqueda de alumno, periodo y estatus;
 *  persistidos en la URL (`#[Url]`) para no perder contexto.
 * - NO es el dashboard de la Fase 14: solo lista (máx. 50 filas).
 */
class ListaInscripciones extends Component
{
    #[Url]
    public string $busqueda = '';

    #[Url]
    public ?int $filtroPeriodo = null;

    #[Url]
    public string $filtroEstatus = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Inscripcion::class);
    }

    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->filtroPeriodo = null;
        $this->filtroEstatus = '';
    }

    public function render()
    {
        // estatus inválido en la URL → se ignora (sin romper el listado).
        $estatus = InscripcionEstatus::tryFrom($this->filtroEstatus);

        return view('livewire.inscripciones.lista-inscripciones', [
            'inscripciones' => Inscripcion::query()
                ->with([
                    'alumno:id,numero_control,nombre,apellidos,curp',
                    'programa:id,clave,nombre',
                    'periodo:id,clave,nombre',
                ])
                ->when(
                    $this->busqueda !== '',
                    fn ($consulta) => $consulta->whereHas('alumno', fn ($alumnos) => $alumnos->buscar($this->busqueda)),
                )
                ->when($this->filtroPeriodo !== null, fn ($consulta) => $consulta->where('periodo_id', $this->filtroPeriodo))
                ->when($estatus !== null, fn ($consulta) => $consulta->where('estatus', $estatus->value))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
            'periodos' => Periodo::query()->orderByDesc('fecha_inicio')->get(['id', 'clave', 'nombre']),
            'estatusOpciones' => InscripcionEstatus::cases(),
            'puedeCrear' => Gate::allows('create', Inscripcion::class),
        ]);
    }
}
