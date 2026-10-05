{{--
  Indicador de carga para wire:loading (se muestra mientras Livewire procesa).

  <span wire:loading.class="hidden" wire:target="guardar">
      <x-ui.loading label="Guardando…" />
  </span>
--}}
@props(['label' => 'Cargando…'])

<span {{ $attributes->class(['inline-flex items-center gap-2 text-sm text-brand-muted']) }} role="status">
    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.25" stroke-width="3" />
        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
    </svg>
    {{ $label }}
</span>
