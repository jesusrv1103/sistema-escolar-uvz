<?php

namespace App\Livewire\Alumnos;

use App\Enums\Sexo;
use App\Models\Alumno;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Expediente del alumno — datos personales (§4.2, §12.1).
 *
 * - `/alumnos/nuevo` → requiere `alumno.create` (ADMIN/ADMISIONES).
 * - `/alumnos/{alumno}` → requiere `alumno.view` (los 4 roles leen expediente);
 *   la edición está servida por `alumno.update` en cada acción y oculta en Blade.
 * - `numero_control` jamás aparece en el formulario (se asigna en Fase 12).
 */
class FormAlumno extends Component
{
    public ?int $alumnoId = null;

    public ?string $nombre = null;
    public ?string $apellidos = null;
    public ?string $curp = null;
    public ?string $fecha_nacimiento = null;
    public ?string $sexo = null;
    public ?string $nacionalidad = 'Mexicana';
    public ?string $correo = null;
    public ?string $telefono = null;
    public ?string $calle = null;
    public ?string $colonia = null;
    public ?string $cp = null;
    public ?string $municipio = null;
    public ?string $estado = null;
    public ?string $pais = 'México';

    public function mount(?Alumno $alumno = null): void
    {
        if ($alumno === null) {
            Gate::authorize('create', Alumno::class);

            return;
        }

        Gate::authorize('view', $alumno);

        $this->alumnoId = $alumno->id;
        $this->nombre = $alumno->nombre;
        $this->apellidos = $alumno->apellidos;
        $this->curp = $alumno->curp;
        $this->fecha_nacimiento = $alumno->fecha_nacimiento?->format('Y-m-d');
        $this->sexo = $alumno->sexo?->value;
        $this->nacionalidad = $alumno->nacionalidad;
        $this->correo = $alumno->correo;
        $this->telefono = $alumno->telefono;
        $this->calle = $alumno->calle;
        $this->colonia = $alumno->colonia;
        $this->cp = $alumno->cp;
        $this->municipio = $alumno->municipio;
        $this->estado = $alumno->estado;
        $this->pais = $alumno->pais;
    }

    public function guardar(): void
    {
        $alumno = $this->alumnoId !== null ? Alumno::findOrFail($this->alumnoId) : null;

        $alumno === null
            ? Gate::authorize('create', Alumno::class)
            : Gate::authorize('update', $alumno);

        $this->normalizar();
        $datos = $this->validate($this->reglas(), $this->mensajes());

        if ($alumno === null) {
            $alumno = Alumno::create($datos + ['creado_por' => auth()->id()]);
            session()->flash('expediente', 'Alumno creado correctamente. Continúa con los contactos.');
            $this->redirect(route('alumnos.ver', $alumno));

            return;
        }

        $alumno->update($datos);
        session()->flash('expediente', 'Datos personales guardados.');
    }

    /** Trim general + CURP en mayúsculas + correo en minúsculas (§5 de reglas). */
    private function normalizar(): void
    {
        foreach (['nombre', 'apellidos', 'fecha_nacimiento', 'sexo', 'nacionalidad', 'telefono', 'calle', 'colonia', 'cp', 'municipio', 'estado', 'pais'] as $campo) {
            $valor = $this->{$campo};
            $this->{$campo} = is_string($valor) ? trim($valor) : $valor;
        }

        $this->curp = $this->curp === null ? null : preg_replace('/\s+/', '', strtoupper(trim($this->curp)));
        $this->correo = $this->correo === null || trim($this->correo) === '' ? null : strtolower(trim($this->correo));

        foreach (['correo', 'telefono', 'calle', 'colonia', 'cp', 'municipio', 'estado'] as $campo) {
            if ($this->{$campo} === '') {
                $this->{$campo} = null;
            }
        }
    }

    /** @return array<string, list<string>> */
    private function reglas(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'curp' => [
                'required',
                'string',
                'size:18',
                'regex:'.Alumno::REGEX_CURP,
                Rule::unique('alumnos', 'curp')->ignore($this->alumnoId),
            ],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'sexo' => ['required', Rule::enum(Sexo::class)],
            'nacionalidad' => ['required', 'string', 'max:60'],
            'correo' => ['nullable', 'email', 'max:150'],
            'telefono' => ['nullable', 'regex:/^(\+52[\s-]?)?\d{10}$/', 'max:20'],
            'calle' => ['nullable', 'string', 'max:150'],
            'colonia' => ['nullable', 'string', 'max:120'],
            'cp' => ['nullable', 'regex:/^\d{5}$/', 'max:10'],
            'municipio' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'max:120'],
            'pais' => ['required', 'string', 'max:80'],
        ];
    }

    /** @return array<string, string> */
    private function mensajes(): array
    {
        return [
            'curp.regex' => 'La CURP debe tener 18 caracteres con estructura válida (sintaxis local, sin consulta RENAPO).',
            'curp.size' => 'La CURP debe tener exactamente 18 caracteres.',
            'curp.unique' => 'Ya existe un alumno con esa CURP.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'telefono.regex' => 'El teléfono debe ser a 10 dígitos (opcionalmente con +52).',
            'cp.regex' => 'El código postal debe tener 5 dígitos.',
        ];
    }

    public function render()
    {
        $alumno = $this->alumnoId !== null ? Alumno::findOrFail($this->alumnoId) : null;

        return view('livewire.alumnos.form-alumno', [
            'alumno' => $alumno,
            'sexos' => Sexo::etiquetas(),
            'puedeEditar' => $alumno === null
                ? Gate::allows('create', Alumno::class)
                : Gate::allows('update', $alumno),
        ]);
    }
}
