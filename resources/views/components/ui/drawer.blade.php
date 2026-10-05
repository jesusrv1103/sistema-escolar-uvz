{{--
  Slide-over / panel lateral derecho para formularios medianos.
  Mismos disparadores que el modal: data-drawer-open / data-drawer-close.

  <x-ui.drawer id="drawer-edit" title="Editar registro">
      …formulario…
      <x-slot:footer>…</x-slot:footer>
  </x-ui.drawer>
--}}
@props(['id', 'title'])

<div {{ $attributes->merge(['class' => 'ui-drawer', 'id' => $id]) }} hidden role="dialog" aria-modal="true"
    aria-labelledby="{{ $id }}-title">
    <div class="ui-drawer-backdrop" data-drawer-close></div>
    <div class="ui-drawer-panel">
        <div class="ui-modal-header">
            <h2 id="{{ $id }}-title" class="text-base font-semibold text-brand-text">{{ $title }}</h2>
            <button type="button" class="ui-btn ui-btn-icon ui-btn-ghost" data-drawer-close aria-label="Cerrar panel">
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
