<div>
    <x-ui.page-header title="Becas, Descuentos y Estado de Cuenta"
        description="Catálogo de becas, asignaciones con aprobación de cuatro ojos, estado de cuenta y carta de no adeudo." />

    {{-- ------------------------------- Pestañas ------------------------------- --}}
    <div class="mt-5 flex flex-wrap gap-1 border-b border-brand-line"
        role="group" aria-label="Secciones de becas">
        <x-ui.button size="sm" :variant="$vista === 'becas' ? 'primary' : 'ghost'" icon="percent"
            wire:click="$set('vista', 'becas')" aria-pressed="{{ $vista === 'becas' ? 'true' : 'false' }}">
            Catálogo de becas
        </x-ui.button>
        <x-ui.button size="sm" :variant="$vista === 'asignaciones' ? 'primary' : 'ghost'" icon="users"
            wire:click="$set('vista', 'asignaciones')" aria-pressed="{{ $vista === 'asignaciones' ? 'true' : 'false' }}">
            Asignaciones
        </x-ui.button>
        <x-ui.button size="sm" :variant="$vista === 'estado-cuenta' ? 'primary' : 'ghost'" icon="credit-card"
            wire:click="$set('vista', 'estado-cuenta')" aria-pressed="{{ $vista === 'estado-cuenta' ? 'true' : 'false' }}">
            Estado de cuenta
        </x-ui.button>
        <x-ui.button size="sm" :variant="$vista === 'carta-no-adeudo' ? 'primary' : 'ghost'" icon="document-text"
            wire:click="$set('vista', 'carta-no-adeudo')" aria-pressed="{{ $vista === 'carta-no-adeudo' ? 'true' : 'false' }}">
            Carta de no adeudo
        </x-ui.button>
    </div>

    <div class="mt-4">
        @if ($vista === 'becas')
            @include('livewire.academico.partials.beca-catalogo')
        @elseif ($vista === 'asignaciones')
            @include('livewire.academico.partials.beca-asignaciones')
        @elseif ($vista === 'estado-cuenta')
            @include('livewire.academico.partials.beca-estado-cuenta')
        @else
            @include('livewire.academico.partials.beca-carta-no-adeudo')
        @endif
    </div>

    @include('livewire.academico.partials.beca-modal')
    @include('livewire.academico.partials.beca-asignacion-modal')
    @include('livewire.academico.partials.beca-estado-cuenta-modal')
    @include('livewire.academico.partials.beca-carta-no-adeudo-modal')
</div>
