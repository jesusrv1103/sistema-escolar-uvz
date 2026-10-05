{{--
  Botón reutilizable. Se renderiza como <button> o <a> según `href`.
  Hereda todas las clases adicionales vía $attributes.
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'block' => false,
])

@php
    $classes = 'ui-btn ui-btn-' . $variant;
    if ($size !== 'md') {
        $classes .= ' ui-btn-' . $size;
    }
    if ($block) {
        $classes .= ' ui-btn-block';
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" :size="16" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" :size="16" />@endif
        {{ $slot }}
    </button>
@endif
