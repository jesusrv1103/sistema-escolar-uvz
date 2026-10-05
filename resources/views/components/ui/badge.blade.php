{{--
  Badge de estado semántico.

  <x-ui.badge tone="success">Activo</x-ui.badge>

  tonos: neutral | primary | success | warning | danger | info
--}}
@props(['tone' => 'neutral'])

<span {{ $attributes->merge(['class' => 'ui-badge ui-badge-' . $tone]) }}>{{ $slot }}</span>
