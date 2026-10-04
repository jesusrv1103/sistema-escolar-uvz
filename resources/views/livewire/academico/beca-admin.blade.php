<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">Becas, Descuentos y Estado de Cuenta</h1>
    </div>

    <!-- Pestañas -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $vista === 'becas' ? 'active' : '' }}" wire:click="$set('vista', 'becas')" role="tab">
                <i class="bi bi-award me-1"></i> Catálogo de Becas
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $vista === 'asignaciones' ? 'active' : '' }}" wire:click="$set('vista', 'asignaciones')" role="tab">
                <i class="bi bi-person-check me-1"></i> Asignaciones
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $vista === 'estado-cuenta' ? 'active' : '' }}" wire:click="$set('vista', 'estado-cuenta')" role="tab">
                <i class="bi bi-credit-card-2-front me-1"></i> Estado de Cuenta
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $vista === 'carta-no-adeudo' ? 'active' : '' }}" wire:click="$set('vista', 'carta-no-adeudo')" role="tab">
                <i class="bi bi-file-earmark-check me-1"></i> Carta de No Adeudo
            </button>
        </li>
    </ul>

    @if($vista === 'becas')
        @include('livewire.academico.partials.beca-catalogo')
    @elseif($vista === 'asignaciones')
        @include('livewire.academico.partials.beca-asignaciones')
    @elseif($vista === 'estado-cuenta')
        @include('livewire.academico.partials.beca-estado-cuenta')
    @else
        @include('livewire.academico.partials.beca-carta-no-adeudo')
    @endif

    @include('livewire.academico.partials.beca-modal')
    @include('livewire.academico.partials.beca-asignacion-modal')
    @include('livewire.academico.partials.beca-estado-cuenta-modal')
    @include('livewire.academico.partials.beca-carta-no-adeudo-modal')
</div>