{{-- Select. Las <option> van en el slot. --}}
@props([])

<select {{ $attributes->merge(['class' => 'ui-select']) }}>{{ $slot }}</select>
