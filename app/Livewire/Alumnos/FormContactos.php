<?php

namespace App\Livewire\Alumnos;

use App\Enums\TipoContacto;
use App\Models\Alumno;
use App\Models\Contacto;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Contactos del expediente: TUTOR y EMERGENCIA (§4.3, §12.2).
 * Un registro por tipo y alumno (updateOrCreate); personas distintas posibles.
 */
class FormContactos extends Component
{
    public int $alumnoId = 0;

    /** @var array<string, mixed> datos del contacto TUTOR */
    public array $tutor = [];

    /** @var array<string, mixed> datos del contacto EMERGENCIA */
    public array $emergencia = [];

    public function mount(Alumno $alumno): void
    {
        Gate::authorize('view', $alumno);

        $this->alumnoId = $alumno->id;
        $this->tutor = self::camposVacios();
        $this->emergencia = self::camposVacios();

        foreach ([TipoContacto::TUTOR, TipoContacto::EMERGENCIA] as $tipo) {
            $contacto = $alumno->contactos()->where('tipo', $tipo->value)->first();
            if ($contacto !== null) {
                $this->{$tipo->value === 'TUTOR' ? 'tutor' : 'emergencia'} = $this->extraer($contacto);
            }
        }
    }

    /** @return array<string, mixed> */
    private static function camposVacios(): array
    {
        return [
            'id' => null,
            'nombre' => null,
            'parentesco' => null,
            'telefono' => null,
            'telefono_alt' => null,
            'correo' => null,
            'notas' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function extraer(Contacto $contacto): array
    {
        return [
            'id' => $contacto->id,
            'nombre' => $contacto->nombre,
            'parentesco' => $contacto->parentesco,
            'telefono' => $contacto->telefono,
            'telefono_alt' => $contacto->telefono_alt,
            'correo' => $contacto->correo,
            'notas' => $contacto->notas,
        ];
    }

    public function guardarTutor(): void
    {
        $this->guardarTipo(TipoContacto::TUTOR, 'tutor', 'Contacto de tutor guardado.');
    }

    public function guardarEmergencia(): void
    {
        $this->guardarTipo(TipoContacto::EMERGENCIA, 'emergencia', 'Contacto de emergencia guardado.');
    }

    private function guardarTipo(TipoContacto $tipo, string $propiedad, string $mensaje): void
    {
        $alumno = Alumno::findOrFail($this->alumnoId);
        Gate::authorize('update', $alumno);

        $validados = $this->validate(
            $this->reglas($propiedad),
            $this->mensajes($propiedad),
        );

        $datos = $validados[$propiedad];
        foreach ($datos as $clave => $valor) {
            $datos[$clave] = is_string($valor) ? (trim($valor) === '' ? null : trim($valor)) : $valor;
        }
        $datos['correo'] = $datos['correo'] === null ? null : strtolower($datos['correo']);

        Contacto::updateOrCreate(
            ['alumno_id' => $alumno->id, 'tipo' => $tipo->value],
            $datos,
        );

        session()->flash('expediente', $mensaje);
    }

    public function eliminar(int $contactoId): void
    {
        $alumno = Alumno::findOrFail($this->alumnoId);
        Gate::authorize('update', $alumno);

        $contacto = $alumno->contactos()->findOrFail($contactoId);
        $contacto->delete();

        $propiedad = $contacto->tipo === TipoContacto::TUTOR ? 'tutor' : 'emergencia';
        $this->{$propiedad} = self::camposVacios();

        session()->flash('expediente', 'Contacto eliminado.');
    }

    /** @return array<string, list<string>> */
    private function reglas(string $propiedad): array
    {
        return [
            "{$propiedad}.nombre" => ['required', 'string', 'max:150'],
            "{$propiedad}.parentesco" => ['required', 'string', 'max:60'],
            "{$propiedad}.telefono" => ['required', 'regex:/^(\+52[\s-]?)?\d{10}$/', 'max:20'],
            "{$propiedad}.telefono_alt" => ['nullable', 'regex:/^(\+52[\s-]?)?\d{10}$/', 'max:20'],
            "{$propiedad}.correo" => ['nullable', 'email', 'max:150'],
            "{$propiedad}.notas" => ['nullable', 'string'],
        ];
    }

    /** @param array<string, mixed> $datos */
    private function mensajes(string $propiedad): array
    {
        return [
            "{$propiedad}.telefono.required" => 'El teléfono es obligatorio.',
            "{$propiedad}.telefono.regex" => 'El teléfono debe ser a 10 dígitos (opcionalmente con +52).',
            "{$propiedad}.telefono_alt.regex" => 'El teléfono alterno debe ser a 10 dígitos (opcionalmente con +52).',
        ];
    }

    public function render()
    {
        $alumno = Alumno::findOrFail($this->alumnoId);

        return view('livewire.alumnos.form-contactos', [
            'alumno' => $alumno,
            'puedeEditar' => Gate::allows('update', $alumno),
            'tutor' => $this->tutor,
            'emergencia' => $this->emergencia,
            'ids' => [
                'tutor' => $this->tutor['id'] ?? null,
                'emergencia' => $this->emergencia['id'] ?? null,
            ],
        ]);
    }
}
