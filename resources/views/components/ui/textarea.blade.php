{{-- Textarea. Hereda rows, placeholder, wire:model, etc. vía $attributes. --}}
@props(['rows' => 3])

<textarea rows="{{ $rows }}" {{ $attributes->merge(['class' => 'ui-textarea']) }}>{{ $slot }}</textarea>
