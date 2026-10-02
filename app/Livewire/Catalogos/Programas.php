<?php

namespace App\Livewire\Catalogos;

use App\Models\Programa;
use Illuminate\Validation\Rule;

class Programas extends CatalogoAdmin
{
    public ?string $clave = null;

    public ?string $nombre = null;

    public ?int $duracion_periodos = null;

    public bool $activo = true;

    protected function modelo(): string
    {
        return Programa::class;
    }

    protected function campos(): array
    {
        return ['clave', 'nombre', 'duracion_periodos', 'activo'];
    }

    protected function valoresIniciales(): array
    {
        return ['clave' => null, 'nombre' => null, 'duracion_periodos' => null, 'activo' => true];
    }

    protected function reglas(): array
    {
        return [
            'clave' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('programas', 'clave')->ignore($this->registroId)],
            'nombre' => ['required', 'string', 'max:150'],
            'duracion_periodos' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'activo' => ['boolean'],
        ];
    }

    protected function mensajes(): array
    {
        return [
            'clave.regex' => 'La clave solo puede contener letras, números, guiones o guiones bajos.',
            'clave.unique' => 'Ya existe un programa con esa clave.',
            'duracion_periodos.min' => 'La duración debe ser al menos 1 periodo.',
        ];
    }

    public function render()
    {
        return view('livewire.catalogos.programas', [
            'programas' => Programa::orderBy('nombre')->get(),
        ]);
    }
}
