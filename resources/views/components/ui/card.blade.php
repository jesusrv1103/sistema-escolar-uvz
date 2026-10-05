{{-- Contenedor de tarjeta reutilizable. Slots opcionales: $header --}}
@props(['flush' => false])

<div {{ $attributes->class(['ui-card overflow-hidden']) }}>
    @isset($header)
        <div class="ui-card-header">{{ $header }}</div>
    @endisset
    <div @class(['ui-card-body' => !$flush, 'ui-card-body-flush' => $flush])>
        {{ $slot }}
    </div>
</div>
