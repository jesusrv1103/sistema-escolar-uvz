<?php

namespace App\Livewire\Catalogos;

use App\Enums\TipoAula;
use App\Models\Docente;
use App\Livewire\Catalogos\CatalogoAdmin;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

/**
 * CRUD de Docentes (solo ADMIN).
 */
#[Layout('layouts.app')]
class Docentes extends CatalogoAdmin
{
    use WithPagination;

    public string $busqueda = '';
    public string $filtroActivo = '';

    // Formulario
    public string $clave = '';
    public string $nombre = '';
    public string $apellidos = '';
    public string $curp = '';
    public ?string $rfc = null;
    public ?string $correo = null;
    public ?string $telefono = null;
    public ?string $especialidad = null;
    public ?string $grado_estudios = null;
    public ?string $cedula_profesional = null;
    public bool $activo = true;

    protected function modelo(): string
    {
        return Docente::class;
    }

    protected function campos(): array
    {
        return [
            'clave', 'nombre', 'apellidos', 'curp', 'rfc', 'correo', 'telefono',
            'especialidad', 'grado_estudios', 'cedula_profesional', 'activo',
        ];
    }

    protected function valoresIniciales(): array
    {
        return [
            'activo' => true,
        ];
    }

    protected function reglas(): array
    {
        return [
            'clave' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', 'unique:docentes,clave,' . ($this->registroId ?? 'NULL')],
            'nombre' => ['required', 'string', 'max:150'],
            'apellidos' => ['required', 'string', 'max:150'],
            'curp' => ['required', 'string', 'size:18', 'unique:docentes,curp,' . ($this->registroId ?? 'NULL')],
            'rfc' => ['nullable', 'string', 'max:13', 'regex:/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/', 'unique:docentes,rfc,' . ($this->registroId ?? 'NULL')],
            'correo' => ['nullable', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'especialidad' => ['nullable', 'string', 'max:150'],
            'grado_estudios' => ['nullable', 'string', 'max:100'],
            'cedula_profesional' => ['nullable', 'string', 'max:50'],
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
        $query = Docente::query()
            ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%")
                ->orWhere('apellidos', 'like', "%{$this->busqueda}%")
                ->orWhere('clave', 'like', "%{$this->busqueda}%"))
            ->when($this->filtroActivo !== '', fn ($q) => $q->where('activo', $this->filtroActivo === '1'))
            ->orderBy('apellidos')
            ->orderBy('nombre');

        $docentes = $query->paginate(15);

        return view('livewire.catalogos.docentes', [
            'docentes' => $docentes,
        ]);
    }
}