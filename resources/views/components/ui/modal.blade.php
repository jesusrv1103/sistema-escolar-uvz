{{--
  Modal accesible. Abrir: data-modal-open="id-modal". Cerrar: data-modal-close.

  <x-ui.button data-modal-open="modal-crear">Nuevo plan</x-ui.button>
  <x-ui.modal id="modal-crear" title="Nuevo plan">
      …formulario…
      <x-slot:footer>…botones…</x-slot:footer>
  </x-ui.modal>

  El JS (resources/js/ui.js) gestiona foco, Escape y devolución del foco.
--}}
@props(['id', 'title', 'size' => 'md'])

@php
    $sizeClass = match ($size) {
        'sm' => 'ui-modal-sm',
        'lg' => 'ui-modal-lg',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => 'ui-modal', 'id' => $id]) }} hidden role="dialog" aria-modal="true"
    aria-labelledby="{{ $id }}-title">
    <div class="ui-modal-backdrop" data-modal-close></div>
    <div class="ui-modal-panel {{ $sizeClass }}">
        <div class="ui-modal-header">
            <h2 id="{{ $id }}-title" class="text-base font-semibold text-brand-text">{{ $title }}</h2>
            <button type="button" class="ui-btn ui-btn-icon ui-btn-ghost" data-modal-close aria-label="Cerrar ventana">
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
