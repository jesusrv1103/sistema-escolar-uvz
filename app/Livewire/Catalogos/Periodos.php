<?php

namespace App\Livewire\Catalogos;

use App\Models\Periodo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class Periodos extends CatalogoAdmin
{
    public ?string $clave = null;

    public ?string $nombre = null;

    public ?string $fecha_inicio = null;

    public ?string $fecha_fin = null;

    public bool $activo = true;

    protected function modelo(): string
    {
        return Periodo::class;
    }

    protected function campos(): array
    {
        return ['clave', 'nombre', 'fecha_inicio', 'fecha_fin', 'activo'];
    }

    protected function valoresIniciales(): array
    {
        return ['clave' => null, 'nombre' => null, 'fecha_inicio' => null, 'fecha_fin' => null, 'activo' => true];
    }

    protected function reglas(): array
    {
        return [
            'clave' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('periodos', 'clave')->ignore($this->registroId)],
            'nombre' => ['required', 'string', 'max:100'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'activo' => ['boolean'],
        ];
    }

    protected function mensajes(): array
    {
        return [
            'clave.unique' => 'Ya existe un periodo con esa clave.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ];
    }

    /**
     * `cerrado_at` bloquea inscripciones (RB-13); no es cierre académico global.
     */
    public function cerrar(int $registroId): void
    {
        Gate::authorize('usuario.manage');

        Periodo::findOrFail($registroId)->update(['cerrado_at' => now()]);
    }

    public function reabrir(int $registroId): void
    {
        Gate::authorize('usuario.manage');

        Periodo::findOrFail($registroId)->update(['cerrado_at' => null]);
    }

    public function render()
    {
        return view('livewire.catalogos.periodos', [
            'periodos' => Periodo::when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%")
                ->orWhere('clave', 'like', "%{$this->busqueda}%"))->orderByDesc('fecha_inicio')->get(),
        ]);
    }
}
