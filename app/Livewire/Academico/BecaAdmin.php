<?php

namespace App\Livewire\Academico;

use App\Models\Beca;
use App\Models\BecaAlumno;
use App\Models\Alumno;
use App\Models\Periodo;
use App\Models\ConceptoPago;
use App\Models\AjusteFinanciero;
use App\Enums\TipoBeca;
use App\Enums\AlcanceBeca;
use App\Enums\EstatusBeca;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class BecaAdmin extends Component
{
    use WithPagination;

    public string $busqueda = '';
    public string $filtroEstatus = '';
    public string $filtroPeriodo = '';
    public string $vista = 'becas';

    public bool $modalBecaAbierto = false;
    public bool $modalAsignacionAbierto = false;
    public bool $modalEstadoCuentaAbierto = false;
    public bool $modalCartaNoAdeudoAbierto = false;

    public ?Beca $beca = null;
    public string $clave = '';
    public string $nombre = '';
    public TipoBeca $tipo = TipoBeca::PORCENTAJE;
    public float $valor = 0;
    public AlcanceBeca $alcance = AlcanceBeca::INSCRIPCION;
    public ?Periodo $periodo = null;
    public bool $activo = true;
    public string $fecha_inicio = '';
    public string $fecha_fin = '';

    public ?BecaAlumno $asignacion = null;
    public ?Beca $becaAsignar = null;
    public ?Alumno $alumnoAsignar = null;
    public ?Periodo $periodoAsignar = null;
    public string $observacion = '';

    public ?Alumno $alumnoEstadoCuenta = null;
    public ?Alumno $alumnoCarta = null;

    protected $listeners = ['becaRegistrada', 'asignacionRegistrada', 'aprobacionRealizada'];

    public function mount(): void
    {
        $this->fecha_inicio = now()->format('Y-m-d');
        $this->fecha_fin = now()->addYear()->format('Y-m-d');
    }

    public function abrirModalBeca(): void
    {
        Gate::authorize('beca.crear');
        $this->resetFormularioBeca();
        $this->modalBecaAbierto = true;
    }

    public function abrirModalEditar(Beca $beca): void
    {
        Gate::authorize('beca.actualizar', $beca);
        $this->beca = $beca;
        $this->clave = $beca->clave;
        $this->nombre = $beca->nombre;
        $this->tipo = $beca->tipo;
        $this->valor = $beca->valor;
        $this->alcance = $beca->alcance;
        $this->periodo = $beca->periodo;
        $this->activo = $beca->activo;
        $this->fecha_inicio = $beca->fecha_inicio?->format('Y-m-d') ?? '';
        $this->fecha_fin = $beca->fecha_fin?->format('Y-m-d') ?? '';
        $this->modalBecaAbierto = true;
    }

    public function resetFormularioBeca(): void
    {
        $this->beca = null;
        $this->clave = '';
        $this->nombre = '';
        $this->tipo = TipoBeca::PORCENTAJE;
        $this->valor = 0;
        $this->alcance = AlcanceBeca::INSCRIPCION;
        $this->periodo = null;
        $this->activo = true;
        $this->fecha_inicio = now()->format('Y-m-d');
        $this->fecha_fin = now()->addYear()->format('Y-m-d');
        $this->resetErrorBag();
    }

    public function guardarBeca(): void
    {
        $this->validate([
            'clave' => 'required|string|max:20|unique:becas,clave,' . ($this->beca?->id ?? 'NULL'),
            'nombre' => 'required|string|max:150',
            'tipo' => 'required',
            'valor' => 'required|numeric|min:0',
            'alcance' => 'required',
            'periodo' => 'required',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $datos = [
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'valor' => $this->valor,
            'alcance' => $this->alcance,
            'periodo_id' => $this->periodo->id,
            'activo' => $this->activo,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin,
        ];

        try {
            if ($this->beca) {
                $this->beca->update($datos);
                session()->flash('success', 'Beca actualizada correctamente.');
            } else {
                Beca::create($datos);
                session()->flash('success', 'Beca creada correctamente.');
            }

            $this->modalBecaAbierto = false;
            $this->resetFormularioBeca();
            $this->dispatch('becaRegistrada');
        } catch (\Throwable $e) {
            $this->addError('guardarBeca', $e->getMessage());
        }
    }

    public function abrirModalAsignacion(): void
    {
        Gate::authorize('beca_asignar');
        $this->becaAsignar = null;
        $this->alumnoAsignar = null;
        $this->periodoAsignar = null;
        $this->observacion = '';
        $this->modalAsignacionAbierto = true;
    }

    public function abrirModalAsignar(Beca $beca): void
    {
        Gate::authorize('beca_asignar');
        $this->becaAsignar = $beca;
        $this->alumnoAsignar = null;
        $this->periodoAsignar = null;
        $this->observacion = '';
        $this->modalAsignacionAbierto = true;
    }

    public function asignarBeca(): void
    {
        $this->validate([
            'becaAsignar' => 'required',
            'alumnoAsignar' => 'required',
            'periodoAsignar' => 'required',
            'observacion' => 'nullable|string|max:1000',
        ]);

        // Verificar si ya existe asignación para este alumno/beca/periodo
        $existe = BecaAlumno::where('beca_id', $this->becaAsignar->id)
            ->where('alumno_id', $this->alumnoAsignar->id)
            ->where('periodo_id', $this->periodoAsignar->id)
            ->exists();

        if ($existe) {
            $this->addError('asignar', 'Ya existe una asignación de esta beca para este alumno en este periodo.');
            return;
        }

        // Verificar acumulación con convenio si el alcance es INSCRIPCION
        if ($this->becaAsignar->alcance === \App\Enums\AlcanceBeca::INSCRIPCION || $this->becaAsignar->alcance === \App\Enums\AlcanceBeca::AMBOS) {
            $tieneConvenio = \App\Models\ConvenioAlumno::where('alumno_id', $this->alumnoAsignar->id)
                ->where('periodo_id', $this->periodoAsignar->id)
                ->where('estatus', 'APROBADO')
                ->exists();

            if ($tieneConvenio) {
                $this->addError('asignar', 'El alumno ya tiene convenio aprobado para INSCRIPCION en este periodo. No se puede acumular beca de INSCRIPCION con convenio.');
                return;
            }
        }

        try {
            BecaAlumno::create([
                'beca_id' => $this->becaAsignar->id,
                'alumno_id' => $this->alumnoAsignar->id,
                'periodo_id' => $this->periodoAsignar->id,
                'estatus' => \App\Enums\EstatusBeca::PENDIENTE,
                'asignada_por' => auth()->id(),
                'observacion' => $this->observacion,
            ]);

            $this->modalAsignacionAbierto = false;
            session()->flash('success', 'Beca asignada correctamente. Pendiente de aprobación.');
            $this->dispatch('asignacionRegistrada');
        } catch (\Throwable $e) {
            $this->addError('asignar', $e->getMessage());
        }
    }

    public function aprobar(BecaAlumno $asignacion): void
    {
        Gate::authorize('beca.aprobar', $asignacion);

        if (!$asignacion->esPendiente()) {
            $this->addError('aprobar', 'Solo se pueden aprobar asignaciones pendientes.');
            return;
        }

        // Verificar acumulación con convenio antes de aprobar
        $beca = $asignacion->beca;
        if ($beca->alcance === \App\Enums\AlcanceBeca::INSCRIPCION || $beca->alcance === \App\Enums\AlcanceBeca::AMBOS) {
            $tieneConvenio = \App\Models\ConvenioAlumno::where('alumno_id', $asignacion->alumno_id)
                ->where('periodo_id', $asignacion->periodo_id)
                ->where('estatus', 'APROBADO')
                ->exists();

            if ($tieneConvenio) {
                $this->addError('aprobar', 'El alumno tiene convenio aprobado para INSCRIPCION. No se puede acumular beca de INSCRIPCION con convenio.');
                return;
            }
        }

        try {
            \DB::transaction(function () use ($asignacion) {
                $asignacion->update([
                    'estatus' => \App\Enums\EstatusBeca::APROBADA,
                    'aprobada_por' => auth()->id(),
                    'fecha_aprobacion' => now(),
                ]);

                // Crear ajustes financieros
                $this->crearAjustesFinancieros($asignacion);
            });

            session()->flash('success', 'Beca aprobada y ajustes financieros aplicados.');
            $this->dispatch('aprobacionRealizada');
        } catch (\Throwable $e) {
            $this->addError('aprobar', $e->getMessage());
        }
    }

    public function rechazar(BecaAlumno $asignacion): void
    {
        Gate::authorize('beca.rechazar', $asignacion);

        if (!$asignacion->esPendiente()) {
            $this->addError('rechazar', 'Solo se pueden rechazar asignaciones pendientes.');
            return;
        }

        try {
            $asignacion->update([
                'estatus' => \App\Enums\EstatusBeca::RECHAZADA,
                'aprobada_por' => auth()->id(),
                'fecha_aprobacion' => now(),
            ]);

            session()->flash('success', 'Beca rechazada.');
            $this->dispatch('aprobacionRealizada');
        } catch (\Throwable $e) {
            $this->addError('rechazar', $e->getMessage());
        }
    }

    private function crearAjustesFinancieros(BecaAlumno $asignacion): void
    {
        $beca = $asignacion->beca;
        $periodo = $asignacion->periodo;
        $alumno = $asignacion->alumno;

        $conceptos = ConceptoPago::where('inscripcion.matriculaPeriodo.alumno_id', $asignacion->alumno_id)
            ->where('inscripcion.matriculaPeriodo.periodo_id', $asignacion->periodo_id)
            ->whereIn('tipo', ['INSCRIPCION', 'MENSUALIDAD'])
            ->get();

        $aplicarInscripcion = in_array($beca->alcance, [\App\Enums\AlcanceBeca::INSCRIPCION, \App\Enums\AlcanceBeca::AMBOS]);
        $aplicarMensualidades = in_array($beca->alcance, [\App\Enums\AlcanceBeca::MENSUALIDADES, \App\Enums\AlcanceBeca::AMBOS]);

        foreach ($conceptos as $concepto) {
            $aplicar = ($concepto->tipo === 'INSCRIPCION' && $aplicarInscripcion) ||
                       ($concepto->tipo === 'MENSUALIDAD' && $aplicarMensualidades);

            if (!$aplicar) {
                continue;
            }

            $descuento = $asignacion->calcularDescuento($concepto->monto_total);
            if ($descuento <= 0) {
                continue;
            }

            // Verificar idempotencia
            $yaAplicado = \App\Models\AjusteFinanciero::where('concepto_pago_id', $concepto->id)
                ->where('beca_alumno_id', $asignacion->id)
                ->where('tipo', \App\Enums\TipoAjusteFinanciero::DESCUENTO_BECA)
                ->exists();

            if ($yaAplicado) {
                continue;
            }

            \App\Models\AjusteFinanciero::create([
                'alumno_id' => $asignacion->alumno_id,
                'concepto_pago_id' => $concepto->id,
                'beca_alumno_id' => $asignacion->id,
                'tipo' => \App\Enums\TipoAjusteFinanciero::DESCUENTO_BECA,
                'monto' => $descuento,
                'registrado_por' => auth()->id(),
                'registrado_at' => now(),
            ]);
        }
    }

    public function abrirEstadoCuenta(Alumno $alumno): void
    {
        $this->alumnoEstadoCuenta = $alumno;
        $this->modalEstadoCuentaAbierto = true;
    }

    public function abrirCartaNoAdeudo(Alumno $alumno): void
    {
        $this->alumnoCarta = $alumno;
        $this->modalCartaNoAdeudoAbierto = true;
    }

    public function render()
    {
        $periodos = Periodo::orderBy('fecha_inicio', 'desc')->get();

        if ($this->vista === 'becas') {
            $becas = Beca::with('periodo')
                ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%")->orWhere('clave', 'like', "%{$this->busqueda}%"))
                ->when($this->filtroEstatus, fn ($q) => $q->where('activo', $this->filtroEstatus === 'ACTIVA'))
                ->when($this->filtroPeriodo, fn ($q) => $q->where('periodo_id', $this->filtroPeriodo))
                ->orderByDesc('created_at')
                ->paginate(15);

            return view('livewire.academico.beca-admin', [
                'becas' => $becas,
                'periodos' => $periodos,
                'vista' => 'becas',
            ]);
        }

        if ($this->vista === 'asignaciones') {
            $asignaciones = BecaAlumno::with(['beca', 'alumno', 'periodo', 'aprobadaPor'])
                ->when($this->busqueda, fn ($q) => $q->whereHas('alumno', fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%")->orWhere('apellidos', 'like', "%{$this->busqueda}%")))
                ->when($this->filtroEstatus, fn ($q) => $q->where('estatus', $this->filtroEstatus))
                ->when($this->filtroPeriodo, fn ($q) => $q->where('periodo_id', $this->filtroPeriodo))
                ->orderByDesc('created_at')
                ->paginate(15);

            return view('livewire.academico.beca-admin', [
                'asignaciones' => $asignaciones,
                'periodos' => $periodos,
                'vista' => 'asignaciones',
            ]);
        }

        if ($this->vista === 'estado-cuenta') {
            return view('livewire.academico.beca-admin', [
                'vista' => 'estado-cuenta',
            ]);
        }

        return view('livewire.academico.beca-admin', [
            'vista' => 'carta-no-adeudo',
        ]);
    }
}