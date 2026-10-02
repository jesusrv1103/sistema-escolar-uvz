<?php

namespace App\Livewire\Alumnos;

use App\Enums\TipoSangre;
use App\Models\Alumno;
use App\Models\InformacionMedica;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Información médica del alumno (§4.4, §12.3) — INFORMACIÓN SENSIBLE (RB-18).
 *
 * Autorización: gate `informacionMedica.edit` (ADMIN vía Gate::before y
 * ADMISIONES). ADMINISTRACION queda siempre fuera; CONTROL_ESCOLAR conserva su
 * capacidad de lectura (`informacionMedica.view`) para la futura revisión final,
 * pero EN ESTA FASE no tiene pantalla general: su lectura contextual se
 * habilitará cuando exista ese flujo.
 */
class FormInfoMedica extends Component
{
    public int $alumnoId = 0;

    public ?string $tipo_sangre = null;
    public ?string $alergias = null;
    public ?string $condiciones = null;
    public ?string $medicamentos = null;
    public bool $tiene_discapacidad = false;
    public ?string $detalle_discapacidad = null;

    public function mount(Alumno $alumno): void
    {
        // Server-side, en el punto de acceso a la pantalla (no solo @can en Blade).
        Gate::authorize('informacionMedica.edit');

        $this->alumnoId = $alumno->id;

        $info = $alumno->informacionMedica;

        if ($info !== null) {
            $this->tipo_sangre = $info->tipo_sangre?->value;
            $this->alergias = $info->alergias;
            $this->condiciones = $info->condiciones;
            $this->medicamentos = $info->medicamentos;
            $this->tiene_discapacidad = $info->tiene_discapacidad;
            $this->detalle_discapacidad = $info->detalle_discapacidad;
        }
    }

    public function guardar(): void
    {
        $alumno = Alumno::findOrFail($this->alumnoId);
        Gate::authorize('informacionMedica.edit');

        $this->normalizar();
        $datos = $this->validate($this->reglas(), $this->mensajes());

        InformacionMedica::updateOrCreate(
            ['alumno_id' => $alumno->id],
            $datos + ['actualizado_por' => auth()->id()],
        );

        session()->flash('expediente', 'Información médica guardada.');
    }

    private function normalizar(): void
    {
        foreach (['alergias', 'condiciones', 'medicamentos', 'detalle_discapacidad'] as $campo) {
            $valor = $this->{$campo};
            $this->{$campo} = is_string($valor) ? trim($valor) : $valor;
        }

        foreach (['alergias', 'condiciones', 'medicamentos', 'detalle_discapacidad'] as $campo) {
            if ($this->{$campo} === '') {
                $this->{$campo} = null;
            }
        }

        if (! $this->tiene_discapacidad) {
            $this->detalle_discapacidad = null;
        }
    }

    /** @return array<string, list<string>> */
    private function reglas(): array
    {
        return [
            'tipo_sangre' => ['nullable', Rule::enum(TipoSangre::class)],
            'alergias' => ['nullable', 'string', 'max:2000'],
            'condiciones' => ['nullable', 'string', 'max:2000'],
            'medicamentos' => ['nullable', 'string', 'max:2000'],
            'tiene_discapacidad' => ['required', 'boolean'],
            'detalle_discapacidad' => ['required_if:tiene_discapacidad,true', 'nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    private function mensajes(): array
    {
        return [
            'detalle_discapacidad.required_if' => 'Describe la discapacidad o desmarca la casilla.',
        ];
    }

    public function render()
    {
        return view('livewire.alumnos.form-info-medica', [
            'alumno' => Alumno::findOrFail($this->alumnoId),
            'tiposSangre' => TipoSangre::etiquetas(),
        ]);
    }
}
