<?php

namespace App\Livewire\Academico;

use App\Enums\TipoFormato;
use App\Models\Alumno;
use App\Models\DocumentoGenerado;
use App\Models\Grupo;
use App\Models\MatriculaPeriodo;
use App\Services\FormatoService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GenerarFormato extends Component
{
    public string $tipoFormato = '';
    public string $busquedaAlumno = '';
    public string $busquedaGrupo = '';
    public ?Alumno $alumnoSeleccionado = null;
    public ?MatriculaPeriodo $matriculaSeleccionada = null;
    public ?Grupo $grupoSeleccionado = null;
    public bool $modalAlumnoAbierto = false;
    public bool $modalGrupoAbierto = false;
    public bool $generando = false;
    /** Registro de trazabilidad del PDF recién emitido (habilita la descarga). */
    public ?int $ultimoDocumentoId = null;

    // Los nombres de evento se mantienen por compatibilidad; las acciones se
    // nombran distinto porque la propiedad pública `$alumnoSeleccionado`
    // sombrearía la acción en el proxy JS de Livewire (`$wire.alumnoSeleccionado`
    // resolvería la propiedad y la selección desde el modal no se ejecutaría).
    protected $listeners = [
        'alumnoSeleccionado' => 'seleccionarAlumno',
        'grupoSeleccionado' => 'seleccionarGrupo',
    ];

    public function mount(): void
    {
        Gate::authorize('documento_generado.crear');
    }

    public function seleccionarAlumno(Alumno $alumno): void
    {
        $this->alumnoSeleccionado = $alumno;
        $this->matriculaSeleccionada = null;
        $this->modalAlumnoAbierto = false;

        // Cargar matrícula activa si existe
        $matriculaActiva = $alumno->matriculas()
            ->where('estatus', \App\Enums\EstatusMatriculaPeriodo::ACTIVA)
            ->with(['periodo', 'programa', 'planVersion'])
            ->latest('fecha_matricula')
            ->first();

        if ($matriculaActiva) {
            $this->matriculaSeleccionada = $matriculaActiva;
        }
    }

    public function seleccionarGrupo(Grupo $grupo): void
    {
        $this->grupoSeleccionado = $grupo;
        $this->modalGrupoAbierto = false;
    }

    public function abrirModalAlumno(): void
    {
        $this->modalAlumnoAbierto = true;
    }

    public function abrirModalGrupo(): void
    {
        $this->modalGrupoAbierto = true;
    }

    public function limpiarSeleccion(): void
    {
        $this->alumnoSeleccionado = null;
        $this->matriculaSeleccionada = null;
        $this->grupoSeleccionado = null;
    }

    public function generar(): void
    {
        $this->validate([
            'tipoFormato' => ['required', 'string', Rule::in(array_column(TipoFormato::cases(), 'value'))],
            'alumnoSeleccionado' => ['required'],
        ]);

        // Validaciones específicas por tipo
        if (in_array($this->tipoFormato, [TipoFormato::HORARIO->value, TipoFormato::LISTA_GRUPO->value])) {
            $this->validate([
                'grupoSeleccionado' => ['required'],
            ]);
        }

        $this->generando = true;

        try {
            $tipo = TipoFormato::from($this->tipoFormato);
            $matricula = $this->matriculaSeleccionada;
            $grupo = $this->grupoSeleccionado;

            // Validar que la matrícula pertenezca al alumno
            if ($matricula && $matricula->alumno_id !== $this->alumnoSeleccionado->id) {
                $this->addError('matriculaSeleccionada', 'La matrícula no pertenece al alumno seleccionado.');
                $this->generando = false;
                return;
            }

            // Validar que el grupo pertenezca a la matrícula (si hay matrícula)
            if ($matricula && $grupo && $grupo->periodo_id !== $matricula->periodo_id) {
                $this->addError('grupoSeleccionado', 'El grupo no pertenece al periodo de la matrícula seleccionada.');
                $this->generando = false;
                return;
            }

            // Para horarios y lista de grupo, el alumno debe estar inscrito en el grupo
            if (in_array($this->tipoFormato, [TipoFormato::HORARIO->value, TipoFormato::LISTA_GRUPO->value])) {
                $inscrito = $this->alumnoSeleccionado->matriculas()
                    ->where('estatus', \App\Enums\EstatusMatriculaPeriodo::ACTIVA)
                    ->whereHas('inscripciones', fn ($q) => $q->where('grupo_id', $this->grupoSeleccionado->id)
                        ->where('estatus', \App\Enums\EstatusInscripcionMateria::CURSANDO))
                    ->exists();

                if (!$inscrito) {
                    $this->addError('grupoSeleccionado', 'El alumno no está inscrito en este grupo.');
                    $this->generando = false;
                    return;
                }
            }

            $emitidoDesde = now();
            $service = app(\App\Services\FormatoService::class);
            $pdfPath = $service->generar(
                TipoFormato::from($this->tipoFormato),
                $this->alumnoSeleccionado,
                $matricula,
                $grupo,
                [],
                auth()->user()
            );

            $this->ultimoDocumentoId = DocumentoGenerado::vincularArchivoEmitido(
                $pdfPath,
                TipoFormato::from($this->tipoFormato),
                $this->alumnoSeleccionado?->getKey(),
                $emitidoDesde,
            )?->getKey();

            session()->flash('success', "Formato generado correctamente.");
            $this->dispatch('pdf-generado', path: $pdfPath);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->addError('generar', 'Error al generar el formato: ' . $e->getMessage());
        } finally {
            $this->generando = false;
        }
    }

    public function descargarPdf(string $path): void
    {
        // Método no usado directamente; la descarga se maneja via JS en el frontend
    }

    public function render()
    {
        $alumnos = Alumno::query()
            ->when($this->busquedaAlumno, fn ($q) => $q->where(function ($q) {
                $q->where('nombre', 'like', "%{$this->busquedaAlumno}%")
                    ->orWhere('apellidos', 'like', "%{$this->busquedaAlumno}%")
                    ->orWhere('curp', 'like', "%{$this->busquedaAlumno}%")
                    ->orWhere('numero_control', 'like', "%{$this->busquedaAlumno}%");
            }))
            ->orderBy('apellidos')
            ->orderBy('nombre')
            ->limit(20)
            ->get();

        $grupos = Grupo::query()
            ->with(['materia', 'periodo', 'docente', 'aula'])
            ->when($this->busquedaGrupo, fn ($q) => $q->where(function ($q) {
                $q->where('clave', 'like', "%{$this->busquedaGrupo}%")
                    ->orWhereHas('materia', fn ($q) => $q->where('clave', 'like', "%{$this->busquedaGrupo}%")
                        ->orWhere('nombre', 'like', "%{$this->busquedaGrupo}%"));
            }))
            ->when($this->matriculaSeleccionada, fn ($q) => $q->where('periodo_id', $this->matriculaSeleccionada->periodo_id))
            ->orderBy('clave')
            ->limit(20)
            ->get();

        return view('livewire.academico.generar-formato', [
            'tiposFormato' => TipoFormato::cases(),
            'alumnos' => $alumnos,
            'grupos' => $grupos,
        ]);
    }
}