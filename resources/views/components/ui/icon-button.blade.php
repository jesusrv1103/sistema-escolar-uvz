{{--
  Botón de solo icono. `label` es obligatorio: se usa como aria-label y title,
  para que la acción siga siendo accesible por teclado y lector de pantalla.
--}}
@props([
    'label',
    'href' => null,
    'type' => 'button',
    'variant' => 'ghost',
    'icon' => null,
    'iconSize' => 18,
])

@php
    $classes = 'ui-btn ui-btn-icon ui-btn-' . $variant;
@endphp

@if ($href)
    <a href="{{ $href }}" aria-label="{{ $label }}" title="{{ $label }}"
        {{ $attributes->merge(['class' => $classes]) }}>
        <x-ui.icon :name="$icon ?? 'cog'" :size="$iconSize" />
    </a>
@else
    <button type="{{ $type }}" aria-label="{{ $label }}" title="{{ $label }}"
        {{ $attributes->merge(['class' => $classes]) }}>
        <x-ui.icon :name="$icon ?? 'cog'" :size="$iconSize" />
    </button>
@endif
