<?php

namespace App\Livewire\Catalogos;

use App\Enums\TipoConvenio;
use App\Models\Convenio;
use Illuminate\Validation\Rule;

class Convenios extends CatalogoAdmin
{
    public ?string $clave = null;

    public ?string $nombre = null;

    public ?string $tipo = null;

    public ?string $valor = null;

    public ?string $fecha_inicio = null;

    public ?string $fecha_fin = null;

    public ?string $notas = null;

    public bool $activo = true;

    protected function modelo(): string
    {
        return Convenio::class;
    }

    protected function campos(): array
    {
        return ['clave', 'nombre', 'tipo', 'valor', 'fecha_inicio', 'fecha_fin', 'notas', 'activo'];
    }

    protected function valoresIniciales(): array
    {
        return [
            'clave' => null,
            'nombre' => null,
            'tipo' => TipoConvenio::PORCENTAJE->value,
            'valor' => null,
            'fecha_inicio' => null,
            'fecha_fin' => null,
            'notas' => null,
            'activo' => true,
        ];
    }

    protected function reglas(): array
    {
        $valor = ['required', 'numeric'];

        // PORCENTAJE: 0 < valor <= 100 · MONTO: valor > 0 (MXN)
        $valor[] = $this->tipo === TipoConvenio::PORCENTAJE->value ? 'lte:100' : 'gt:0';

        if ($this->tipo === TipoConvenio::PORCENTAJE->value) {
            $valor[] = 'gt:0';
        }

        return [
            'clave' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('convenios', 'clave')->ignore($this->registroId)],
            'nombre' => ['required', 'string', 'max:150'],
            'tipo' => ['required', Rule::enum(TipoConvenio::class)],
            'valor' => $valor,
            'fecha_inicio' => ['nullable', 'date', 'required_with:fecha_fin'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'activo' => ['boolean'],
        ];
    }

    protected function mensajes(): array
    {
        return [
            'clave.unique' => 'Ya existe un convenio con esa clave.',
            'valor.lte' => 'El porcentaje debe ser mayor a 0 y máximo 100.',
            'valor.gt' => 'El valor debe ser mayor a 0.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ];
    }

    public function render()
    {
        return view('livewire.catalogos.convenios', [
            'convenios' => Convenio::when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%")
                ->orWhere('clave', 'like', "%{$this->busqueda}%"))->orderBy('nombre')->get(),
        ]);
    }
}
