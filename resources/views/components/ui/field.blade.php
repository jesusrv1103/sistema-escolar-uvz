{{--
  Campo de formulario: label + slot (input/select/textarea) + hint + error.

  <x-ui.field label="Clave" for="clave" hint="Identificador interno." :error="$errors->first('clave')">
      <x-ui.input wire:model="form.clave" id="clave" />
  </x-ui.field>
--}}
@props(['label', 'for' => null, 'hint' => null, 'required' => false, 'error' => null])

<div {{ $attributes->class(['ui-field']) }}>
    <label @if ($for) for="{{ $for }}" @endif class="ui-label">
        {{ $label }}
        @if ($required)
            <span class="text-red-600" aria-hidden="true">*</span>
        @endif
    </label>

    {{ $slot }}

    @if ($hint)
        <p class="ui-hint">{{ $hint }}</p>
    @endif

    @if ($error)
        <p class="ui-error" role="alert">{{ $error }}</p>
    @endif
</div>
