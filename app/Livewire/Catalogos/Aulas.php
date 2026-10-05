<?php

namespace App\Livewire\Catalogos;

use App\Enums\TipoAula;
use App\Models\Aula;
use App\Livewire\Catalogos\CatalogoAdmin;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

/**
 * CRUD de Aulas (solo ADMIN).
 */
#[Layout('layouts.app')]
class Aulas extends CatalogoAdmin
{
    use WithPagination;

    public string $busqueda = '';
    public string $filtroActivo = '';

    // Formulario
    public string $clave = '';
    public string $nombre = '';
    public string $edificio = '';
    public int $capacidad = 30;
    public string $tipo = 'TEORICA';
    public bool $activo = true;

    protected function modelo(): string
    {
        return Aula::class;
    }

    protected function campos(): array
    {
        return [
            'clave', 'nombre', 'edificio', 'capacidad', 'tipo', 'activo',
        ];
    }

    protected function valoresIniciales(): array
    {
        return [
            'capacidad' => 30,
            'tipo' => 'TEORICA',
            'activo' => true,
        ];
    }

    protected function reglas(): array
    {
        return [
            'clave' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', 'unique:aulas,clave,' . ($this->registroId ?? 'NULL')],
            'nombre' => ['required', 'string', 'max:100'],
            'edificio' => ['required', 'string', 'max:100'],
            'capacidad' => ['required', 'integer', 'min:1', 'max:500'],
            'tipo' => ['required', 'string', 'in:TEORICA,PRACTICA,MIXTA,LABORATORIO'],
            'activo' => ['boolean'],
        ];
    }

    public function alternarActivo(int $registroId): void
    {
        parent::alternarActivo($registroId);
    }

    /** UX (2.0-12): limpia búsqueda y estado del listado. */
    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->filtroActivo = '';
        $this->resetPage();
    }

    public function render()
    {
        $query = Aula::query()
            ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%")
                ->orWhere('clave', 'like', "%{$this->busqueda}%")
                ->orWhere('edificio', 'like', "%{$this->busqueda}%"))
            ->when($this->filtroActivo !== '', fn ($q) => $q->where('activo', $this->filtroActivo === '1'))
            ->orderBy('edificio')
            ->orderBy('nombre');

        $aulas = $query->paginate(15);

        return view('livewire.catalogos.aulas', [
            'aulas' => $aulas,
        ]);
    }
}