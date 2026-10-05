{{--
  Alerta / feedback de estado.

  <x-ui.alert tone="success">Guardado correctamente.</x-ui.alert>

  tonos: info | success | warning | danger | neutral
--}}
@props(['tone' => 'info'])

@php
    $map = [
        'info' => ['icon' => 'information-circle', 'classes' => 'border-blue-200 bg-blue-50 text-blue-900'],
        'success' => ['icon' => 'check-circle', 'classes' => 'border-emerald-200 bg-emerald-50 text-emerald-900'],
        'warning' => ['icon' => 'exclamation-triangle', 'classes' => 'border-amber-200 bg-amber-50 text-amber-900'],
        'danger' => ['icon' => 'exclamation-triangle', 'classes' => 'border-red-200 bg-red-50 text-red-900'],
        'neutral' => ['icon' => 'information-circle', 'classes' => 'border-brand-line bg-brand-surface text-brand-text'],
    ];
    $current = $map[$tone] ?? $map['info'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm ' . $current['classes']]) }}
    role="alert">
    <x-ui.icon :name="$current['icon']" :size="18" class="mt-0.5 shrink-0" />
    <div class="min-w-0 flex-1">{{ $slot }}</div>
    @isset($action)
        <div class="shrink-0">{{ $action }}</div>
    @endisset
</div>
