<?php

namespace App\Livewire\Catalogos;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Base de los CRUD de catálogos (Fase 3). Solo ADMIN (`usuario.manage`).
 * Cada catálogo aporta sus propios campos, reglas y vista; aquí vive el
 * flujo compartido (crear / editar / guardar / activar-desactivar / eliminar).
 */
abstract class CatalogoAdmin extends Component
{
    public bool $formulario = false;

    public ?int $registroId = null;

    /**
     * Búsqueda del listado (UX 2.0-12). Cada subclase la aplica en su render();
     * aquí vive el estado y la limpieza compartida.
     */
    public string $busqueda = '';

    /** Clase del modelo del catálogo. */
    abstract protected function modelo(): string;

    /** @return list<string> propiedades del formulario */
    abstract protected function campos(): array;

    /** @return array<string, mixed> valores por defecto al crear */
    abstract protected function valoresIniciales(): array;

    /** @return array<string, list<string>> validación server-side */
    abstract protected function reglas(): array;

    /** @return array<string, string> */
    protected function mensajes(): array
    {
        return [];
    }

    public function crear(): void
    {
        Gate::authorize('usuario.manage');

        $this->resetErrorBag();
        $this->registroId = null;

        foreach ($this->valoresIniciales() as $campo => $valor) {
            $this->{$campo} = $valor;
        }

        $this->formulario = true;
    }

    public function editar(int $registroId): void
    {
        Gate::authorize('usuario.manage');

        $modelo = $this->modelo()::findOrFail($registroId);

        $this->resetErrorBag();
        $this->registroId = $registroId;

        foreach ($this->campos() as $campo) {
            $this->{$campo} = $this->normalizar($modelo->getAttribute($campo));
        }

        $this->formulario = true;
    }

    public function cancelar(): void
    {
        Gate::authorize('usuario.manage');

        $this->resetErrorBag();
        $this->formulario = false;
        $this->registroId = null;
    }

    /** UX (2.0-12): limpia los filtros del listado. */
    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
    }

    public function guardar(): void
    {
        Gate::authorize('usuario.manage');

        $validados = $this->validate($this->reglas(), $this->mensajes());

        $clase = $this->modelo();

        if ($this->registroId !== null) {
            $clase::findOrFail($this->registroId)->update($validados);
        } else {
            $clase::create($validados);
        }

        session()->flash('catalogo', 'Registro guardado correctamente.');

        $this->formulario = false;
        $this->registroId = null;
        $this->resetErrorBag();
    }

    public function alternarActivo(int $registroId): void
    {
        Gate::authorize('usuario.manage');

        $modelo = $this->modelo()::findOrFail($registroId);
        $modelo->update(['activo' => ! $modelo->activo]);
    }

    public function eliminar(int $registroId): void
    {
        Gate::authorize('usuario.manage');

        $this->modelo()::findOrFail($registroId)->delete();

        if ($this->registroId === $registroId) {
            $this->formulario = false;
            $this->registroId = null;
        }
    }

    /** Convierte valores del modelo al formato de los inputs. */
    protected function normalizar(mixed $valor): mixed
    {
        return match (true) {
            $valor instanceof DateTimeInterface => $valor->format('Y-m-d'),
            $valor instanceof BackedEnum => $valor->value,
            default => $valor,
        };
    }
}
