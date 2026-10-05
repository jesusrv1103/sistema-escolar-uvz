{{--
  Encabezado de página: título + descripción + acción primaria a la derecha.

  <x-ui.page-header title="Planes de estudio" description="Administra…">
      <x-slot:actions><x-ui.button wire:click="crear">+ Nuevo plan</x-ui.button></x-slot:actions>
  </x-ui.page-header>
--}}
@props(['title', 'description' => null])

<div {{ $attributes->class(['flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="min-w-0">
        <h1 class="text-xl font-semibold tracking-tight text-brand-text">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 max-w-2xl text-sm text-brand-muted">{{ $description }}</p>
        @endif
        @isset($meta)
            <div class="mt-2">{{ $meta }}</div>
        @endisset
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
