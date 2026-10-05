{{-- Input de texto. Hereda wire:model, id, placeholder, etc. vía $attributes. --}}
@props(['type' => 'text'])

<input type="{{ $type }}" {{ $attributes->merge(['class' => 'ui-input']) }}>
