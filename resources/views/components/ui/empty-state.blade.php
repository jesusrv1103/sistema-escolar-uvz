{{--
  Estado vacío centrado: icono discreto + título + mensaje + acción.

  <x-ui.empty-state title="No hay planes de estudio" message="…">
      <x-slot:action><x-ui.button wire:click="crear">Crear plan</x-ui.button></x-slot:action>
  </x-ui.empty-state>
--}}
@props(['title', 'message' => null, 'icon' => 'inbox-stack'])

<div {{ $attributes->class(['ui-empty']) }} role="status">
    <span class="ui-empty-icon"><x-ui.icon :name="$icon" :size="22" /></span>
    <p class="text-sm font-semibold text-brand-text">{{ $title }}</p>
    @if ($message)
        <p class="max-w-md text-sm text-brand-muted">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-3">{{ $action }}</div>
    @endisset
</div>
