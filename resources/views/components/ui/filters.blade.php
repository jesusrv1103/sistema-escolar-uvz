{{--
  Card compacta de filtros. Slot para el grid de campos; `actions` para el
  botón "Limpiar filtros" (a la derecha del título).

  <x-ui.filters>
      <x-slot:actions><x-ui.button variant="ghost" wire:click="limpiar">Limpiar</x-ui.button></x-slot:actions>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">…</div>
  </x-ui.filters>
--}}
@props(['title' => 'Filtros'])

<div {{ $attributes->class(['ui-card']) }}>
    <div class="ui-card-body">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="ui-section-title">{{ $title }}</p>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
        {{ $slot }}
    </div>
</div>
