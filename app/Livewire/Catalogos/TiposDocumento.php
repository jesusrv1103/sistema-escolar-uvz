<?php

namespace App\Livewire\Catalogos;

use App\Models\TipoDocumento;
use Illuminate\Validation\Rule;

class TiposDocumento extends CatalogoAdmin
{
    public ?string $clave = null;

    public ?string $nombre = null;

    public bool $obligatorio = true;

    public bool $activo = true;

    protected function modelo(): string
    {
        return TipoDocumento::class;
    }

    protected function campos(): array
    {
        return ['clave', 'nombre', 'obligatorio', 'activo'];
    }

    protected function valoresIniciales(): array
    {
        return ['clave' => null, 'nombre' => null, 'obligatorio' => true, 'activo' => true];
    }

    protected function reglas(): array
    {
        return [
            'clave' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('tipos_documento', 'clave')->ignore($this->registroId)],
            'nombre' => ['required', 'string', 'max:120'],
            'obligatorio' => ['boolean'],
            'activo' => ['boolean'],
        ];
    }

    protected function mensajes(): array
    {
        return [
            'clave.unique' => 'Ya existe un tipo de documento con esa clave.',
        ];
    }

    public function render()
    {
        return view('livewire.catalogos.tipos-documento', [
            'tipos' => TipoDocumento::when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%")
                ->orWhere('clave', 'like', "%{$this->busqueda}%"))->orderBy('nombre')->get(),
        ]);
    }
}
