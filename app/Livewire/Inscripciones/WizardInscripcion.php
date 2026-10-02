<?php

namespace App\Livewire\Inscripciones;

use App\Enums\InscripcionEstatus;
use App\Enums\TipoContacto;
use App\Exceptions\CargosInvalidosException;
use App\Exceptions\ConvenioInvalidoException;
use App\Models\Alumno;
use App\Models\Inscripcion;
use App\Models\Periodo;
use App\Models\Programa;
use App\Services\InscripcionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Wizard de inscripción (FC-2, §9): pasos 1–6 → `DATA_COMPLETE`.
 *
 * División aprobada de los 6 pasos (los documentos solo fijan de forma
 * inequívoca el paso 3 = contacto tutor, el paso 4 = contacto de emergencia,
 * que el paso 6 valida y guarda `datos_completados_at`, y que los "pasos 2–6"
 * son pantallas de alumno):
 *
 *   1. Selección y montos → crea la inscripción `DRAFT` (alumno + programa +
 *      periodo + montos snapshot, FC-2).
 *   2. Datos personales (del alumno; se capturan en el expediente Fase 4).
 *   3. Datos de contacto — tutor.
 *   4. Contacto de emergencia.
 *   5. Información médica (solo estado de registro: nunca el contenido).
 *   6. Revisión completa → `InscripcionService::transicionar(DATA_COMPLETE)`.
 *
 * Reglas clave:
 *  - El progreso NO vive en sesión: el borrador está en BD (R8) y el paso
 *    inicial se deriva del primer paso incompleto (reanudación automática).
 *  - Las validaciones de alumno/contactos/médica NO se duplican aquí: los
 *    datos se capturan en los componentes de Fase 4 y este wizard solo
 *    verifica completitud por paso (RB-2).
 *  - RB-13 (periodo `cerrado_at`) bloquea crear y modificar.
 *  - RB-14 (alumno + periodo) se valida antes de guardar; `UNIQUE` de BD
 *    sigue siendo la última línea de defensa.
 */
class WizardInscripcion extends Component
{
    public ?int $inscripcionId = null;

    public int $paso = 1;

    // Paso 1 — selección y montos (snapshots de la inscripción).
    public ?int $alumnoId = null;

    public string $busquedaAlumno = '';

    public ?int $programaId = null;

    public ?int $periodoId = null;

    public ?string $monto_inscripcion = null;

    public ?string $monto_mensualidad = null;

    /** @var array<int, string> */
    private const MENSAJES_PASO = [
        1 => 'Completa la selección de alumno, programa, periodo y montos.',
        2 => 'El alumno no tiene los datos personales completos. Edítalos en el expediente.',
        3 => 'Debe existir exactamente un contacto de tutor. Completa el paso en el expediente.',
        4 => 'Debe existir exactamente un contacto de emergencia. Completa el paso en el expediente.',
        5 => 'Falta la información médica básica del alumno. Completa el paso en el expediente.',
        6 => 'Faltan datos de los pasos anteriores para completar la inscripción.',
    ];

    public function mount(?Inscripcion $inscripcion = null): void
    {
        if ($inscripcion === null) {
            Gate::authorize('create', Inscripcion::class);

            return;
        }

        Gate::authorize('view', $inscripcion);

        $this->inscripcionId = $inscripcion->id;
        $this->alumnoId = $inscripcion->alumno_id;
        $this->programaId = $inscripcion->programa_id;
        $this->periodoId = $inscripcion->periodo_id;
        $this->monto_inscripcion = $inscripcion->monto_inscripcion;
        $this->monto_mensualidad = $inscripcion->monto_mensualidad;
        $this->paso = $this->pasoMaximo();   // reanudación: primer paso incompleto
    }

    /** Navegación visual: retroceder nunca borra datos (todo está en BD). */
    public function atras(): void
    {
        $this->autorizarLectura();
        $this->paso = max(1, $this->paso - 1);
    }

    /** Salto a un paso ya alcanzable (no se saltan pasos incompletos). */
    public function irAPaso(int $paso): void
    {
        $this->autorizarLectura();

        if ($paso < 1 || $paso > 6) {
            return;
        }

        if ($paso > $this->pasoMaximo()) {
            $this->addError('paso', self::MENSAJES_PASO[$this->pasoMaximo()]);

            return;
        }

        $this->paso = $paso;
    }

    /** Valida el paso actual (server-side) y persiste el avance. */
    public function avanzar(): void
    {
        if ($this->paso === 1) {
            if (! $this->guardarPaso1()) {
                return;
            }

            $this->paso = 2;

            return;
        }

        $this->autorizarLectura();

        if ($this->paso >= 6) {
            return;
        }

        if (! $this->pasoCompleto($this->paso)) {
            $this->addError('paso', self::MENSAJES_PASO[$this->paso]);

            return;
        }

        $this->paso++;
    }

    /** Paso 6 → RB-2 completo → `DATA_COMPLETE` vía InscripcionService. */
    public function completar(): void
    {
        $inscripcion = $this->inscripcion();

        if ($inscripcion === null) {
            $this->addError('paso', self::MENSAJES_PASO[1]);

            return;
        }

        Gate::authorize('update', $inscripcion);

        if ($inscripcion->estatus !== InscripcionEstatus::DRAFT) {
            $this->addError('paso', 'La inscripción ya no está en borrador; no admite cambios de datos.');

            return;
        }

        if ($inscripcion->periodo?->cerrado_at !== null) {
            $this->addError('paso', 'El periodo está cerrado: no se permite modificar la inscripción (RB-13).');

            return;
        }

        if (! $this->todosCompletos()) {
            $this->addError('paso', self::MENSAJES_PASO[$this->pasoMaximo()]);

            return;
        }

        // Único punto de cambio de estatus (§7.3.6): escribe
        // `datos_completados_at` y la auditoría en la misma transacción.
        app(InscripcionService::class)->transicionar($inscripcion, InscripcionEstatus::DATA_COMPLETE);

        $this->paso = 6;
        session()->flash('wizard', sprintf(
            'Datos completos: la inscripción pasó a %s.',
            InscripcionEstatus::DATA_COMPLETE->label(),
        ));
    }

    /**
     * Paso 1: valida §12.4 + RB-13 + RB-14 y crea/actualiza el borrador.
     * Devuelve `false` cuando hubo errores manejados (ya agregados al bag).
     */
    private function guardarPaso1(): bool
    {
        $inscripcion = $this->inscripcion();

        if ($inscripcion === null) {
            Gate::authorize('create', Inscripcion::class);
        } else {
            Gate::authorize('update', $inscripcion);

            if ($inscripcion->estatus !== InscripcionEstatus::DRAFT) {
                $this->addError('paso', 'La inscripción ya no está en borrador; no admite cambios de datos.');

                return false;
            }

            if ($inscripcion->periodo?->cerrado_at !== null) {
                $this->addError('paso', 'El periodo está cerrado: no se permite modificar la inscripción (RB-13).');

                return false;
            }
        }

        // RB-13 con mensaje claro: periodo cerrado (la regla exists no lo dice).
        $periodo = $this->periodoId !== null ? Periodo::find($this->periodoId) : null;

        if ($periodo !== null && $periodo->cerrado_at !== null) {
            $this->addError('periodoId', 'El periodo está cerrado: no se permiten nuevas inscripciones ni modificaciones (RB-13).');

            return false;
        }

        $this->validate($this->reglasPaso1(), $this->mensajesPaso1());

        // RB-14: duplicidad antes de guardar (el UNIQUE de BD es el respaldo).
        $duplicada = Inscripcion::query()
            ->where('alumno_id', $this->alumnoId)
            ->where('periodo_id', $this->periodoId)
            ->when($this->inscripcionId !== null, fn ($consulta) => $consulta->where('id', '!=', $this->inscripcionId))
            ->exists();

        if ($duplicada) {
            $this->addError('alumnoId', 'Este alumno ya tiene una inscripción en el periodo seleccionado (RB-14).');

            return false;
        }

        $datos = [
            'alumno_id' => $this->alumnoId,
            'periodo_id' => $this->periodoId,
            'programa_id' => $this->programaId,
            'monto_inscripcion' => $this->monto_inscripcion,
            'monto_mensualidad' => $this->monto_mensualidad,
        ];

        if ($inscripcion === null) {
            // FC-2: creación del borrador (snapshots) en transacción + auditoría.
            $inscripcion = app(InscripcionService::class)->crear($datos);
            $this->inscripcionId = $inscripcion->id;
            session()->flash('wizard', 'Borrador de inscripción creado. Continúa con los pasos siguientes.');
        } else {
            // Snapshots actualizables solo mientras es borrador y el periodo sigue abierto.
            $inscripcion->update($datos);
        }

        return true;
    }

    /** @return array<string, list<string>> */
    private function reglasPaso1(): array
    {
        return [
            'alumnoId' => ['required', 'integer', Rule::exists('alumnos', 'id')->whereNull('deleted_at')],
            'programaId' => [
                'required',
                'integer',
                Rule::exists('programas', 'id')->whereNull('deleted_at')->where('activo', true),
            ],
            'periodoId' => [
                'required',
                'integer',
                Rule::exists('periodos', 'id')->whereNull('deleted_at')->where('activo', true)->whereNull('cerrado_at'),
            ],
            'monto_inscripcion' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'monto_mensualidad' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    /** @return array<string, string> */
    private function mensajesPaso1(): array
    {
        return [
            'alumnoId.required' => 'Selecciona un alumno (o regístralo primero en el expediente).',
            'alumnoId.exists' => 'El alumno seleccionado no existe o fue eliminado.',
            'programaId.required' => 'Selecciona un programa.',
            'programaId.exists' => 'El programa seleccionado no está activo o no existe.',
            'periodoId.required' => 'Selecciona un periodo.',
            'periodoId.exists' => 'El periodo seleccionado no está disponible (inactivo, cerrado o inexistente).',
            'monto_inscripcion.required' => 'Captura el monto de inscripción.',
            'monto_inscripcion.numeric' => 'El monto de inscripción debe ser un número.',
            'monto_inscripcion.min' => 'El monto de inscripción no puede ser negativo.',
            'monto_inscripcion.decimal' => 'El monto de inscripción admite máximo 2 decimales.',
            'monto_mensualidad.required' => 'Captura el monto de mensualidad.',
            'monto_mensualidad.numeric' => 'El monto de mensualidad debe ser un número.',
            'monto_mensualidad.min' => 'El monto de mensualidad no puede ser negativo.',
            'monto_mensualidad.decimal' => 'El monto de mensualidad admite máximo 2 decimales.',
        ];
    }

    /** Completitud por paso (RB-2) contra BD: sin duplicar validaciones. */
    private function pasoCompleto(int $paso): bool
    {
        $inscripcion = $this->inscripcion();
        $alumno = $inscripcion?->alumno;

        return match ($paso) {
            1 => $inscripcion !== null,
            2 => $alumno !== null
                && filled($alumno->nombre)
                && filled($alumno->apellidos)
                && filled($alumno->curp)
                && $alumno->fecha_nacimiento !== null
                && $alumno->sexo !== null,
            3 => $alumno !== null && $alumno->contactos()->where('tipo', TipoContacto::TUTOR->value)->count() === 1,
            4 => $alumno !== null && $alumno->contactos()->where('tipo', TipoContacto::EMERGENCIA->value)->count() === 1,
            5 => $alumno !== null && $alumno->informacionMedica()->exists(),
            default => $this->todosCompletos(),
        };
    }

    private function todosCompletos(): bool
    {
        foreach ([1, 2, 3, 4, 5] as $paso) {
            if (! $this->pasoCompleto($paso)) {
                return false;
            }
        }

        return true;
    }

    /** Primer paso incompleto (= hasta dónde se puede navegar). */
    private function pasoMaximo(): int
    {
        foreach ([1, 2, 3, 4, 5, 6] as $paso) {
            if (! $this->pasoCompleto($paso)) {
                return $paso;
            }
        }

        return 6;
    }

    private function autorizarLectura(): void
    {
        $inscripcion = $this->inscripcion();

        $inscripcion === null
            ? Gate::authorize('viewAny', Inscripcion::class)
            : Gate::authorize('view', $inscripcion);
    }

    /**
     * Fase 7: genera los cargos (1 INSCRIPCION + 4 MENSUALIDADES) y pasa a
     * `PENDING_PAYMENT` (RB-3/FC-4). Idempotente: una segunda ejecución se
     * rechaza sin crear duplicados. La autorización es server-side.
     */
    public function generarCargos(): void
    {
        $inscripcion = $this->inscripcion();

        if ($inscripcion === null) {
            $this->addError('cargos', 'No hay una inscripción seleccionada.');

            return;
        }

        Gate::authorize('generarCargos', $inscripcion);

        try {
            app(InscripcionService::class)->generarCargos($inscripcion);
            session()->flash('wizard', sprintf(
                'Cargos generados (1 inscripción + 4 mensualidades): la inscripción pasó a %s.',
                InscripcionEstatus::PENDING_PAYMENT->label(),
            ));
        } catch (ConvenioInvalidoException|CargosInvalidosException $e) {
            $this->addError('cargos', $e->getMessage());
        }
    }

    private function inscripcion(): ?Inscripcion
    {
        return $this->inscripcionId !== null ? Inscripcion::find($this->inscripcionId) : null;
    }

    public function render()
    {
        $inscripcion = $this->inscripcion();
        $alumno = $inscripcion?->alumno;

        $puedeEditar = $inscripcion === null
            ? Gate::allows('create', Inscripcion::class)
            : Gate::allows('update', $inscripcion)
                && $inscripcion->estatus === InscripcionEstatus::DRAFT
                && $inscripcion->periodo?->cerrado_at === null;

        // Candidatos a alumno (búsqueda de Fase 4, sin tocar la info médica).
        $candidatos = Alumno::query()
            ->buscar($this->busquedaAlumno)
            ->orderBy('apellidos')
            ->orderBy('nombre')
            ->limit(15)
            ->get(['id', 'numero_control', 'nombre', 'apellidos', 'curp']);

        if ($this->alumnoId !== null && ! $candidatos->contains('id', $this->alumnoId)) {
            $seleccionado = Alumno::find($this->alumnoId);

            if ($seleccionado !== null) {
                $candidatos->prepend($seleccionado);
            }
        }

        return view('livewire.inscripciones.wizard-inscripcion', [
            'inscripcion' => $inscripcion,
            'alumno' => $alumno,
            'candidatos' => $candidatos,
            'programas' => Programa::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'clave', 'nombre']),
            'periodos' => Periodo::query()
                ->where('activo', true)
                ->whereNull('cerrado_at')
                ->orderByDesc('fecha_inicio')
                ->get(['id', 'clave', 'nombre', 'fecha_inicio', 'fecha_fin']),
            'contactoTutor' => $alumno?->contactos()->where('tipo', TipoContacto::TUTOR->value)->first(),
            'contactoEmergencia' => $alumno?->contactos()->where('tipo', TipoContacto::EMERGENCIA->value)->first(),
            // Nunca se pasa el modelo médico al view: solo su existencia (RB-18).
            'infoMedicaCompleta' => $alumno !== null && $alumno->informacionMedica()->exists(),
            'pasoMaximo' => $this->pasoMaximo(),
            'puedeEditar' => $puedeEditar,
            // Cargos (Fase 7): 1 INSCRIPCION + 4 MENSUALIDADES cuando existen.
            'conceptosPago' => $inscripcion === null
                ? collect()
                : $inscripcion->conceptosPago()->orderBy('tipo')->orderBy('numero')->get(),
        ]);
    }
}
