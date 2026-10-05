{{--
  Modal accesible.

  Apertura: data-modal-open="id-modal" (páginas Blade) o wire:click a la acción
  que ponga $open=true (componentes Livewire). Cierre: data-modal-close.

  Props:
    - open:  bool → controla el atributo hidden (fuente de verdad Livewire/Blade)
    - close: nombre de la acción Livewire a invocar al cerrar (X, fondo y Escape)

  El JS (resources/js/ui.js) gestiona foco, Escape y devolución del foco.
--}}
@props(['id', 'title', 'size' => 'md', 'open' => false, 'close' => null])

@php
    $sizeClass = match ($size) {
        'sm' => 'ui-modal-sm',
        'lg' => 'ui-modal-lg',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => 'ui-modal', 'id' => $id]) }} {{ $open ? '' : 'hidden' }} role="dialog"
    aria-modal="true" aria-labelledby="{{ $id }}-title">
    <div class="ui-modal-backdrop" data-modal-close @if ($close) wire:click="{!! $close !!}" @endif></div>
    <div class="ui-modal-panel {{ $sizeClass }}">
        <div class="ui-modal-header">
            <h2 id="{{ $id }}-title" class="text-base font-semibold text-brand-text">{{ $title }}</h2>
            <button type="button" class="ui-btn ui-btn-icon ui-btn-ghost" data-modal-close
                @if ($close) wire:click="{!! $close !!}" @endif aria-label="Cerrar ventana">
                <x-ui.icon name="x-mark" :size="18" />
            </button>
        </div>
        <div class="ui-modal-body">
            {{ $slot }}
        </div>
        @isset($footer)
            <div class="ui-modal-footer">{{ $footer }}</div>
        @endisset
    </div>
</div>
