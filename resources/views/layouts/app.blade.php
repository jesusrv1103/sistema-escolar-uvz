<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ config('branding.primary_color') }}">
    <title>@yield('title', config('branding.short_name').' · '.config('branding.system_name'))</title>
    <link rel="icon" type="image/png" href="{{ asset(config('branding.favicon')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Única fuente de verdad de los colores: config/branding.php --}}
    <style>
        :root {
            --brand-primary: {{ config('branding.primary_color') }};
            --brand-secondary: {{ config('branding.secondary_color') }};
            --brand-highlight: {{ config('branding.highlight_color') }};
            --brand-accent: {{ config('branding.accent_color') }};
            --brand-background: {{ config('branding.background_color') }};
            --brand-surface: {{ config('branding.surface_color') }};
            --brand-text: {{ config('branding.text_color') }};
            --brand-muted: {{ config('branding.muted_color') }};
            --brand-line: {{ config('branding.border_color') }};
        }
    </style>
</head>

<body class="min-h-screen bg-brand-bg text-brand-text antialiased">
    @if(auth()->check())
        <x-layouts.app-shell>
            <main class="mx-auto w-full min-w-0 max-w-7xl px-4 py-8">
                {{-- Render full-page de Livewire: inyecta el componente como $slot
                     (rama @component con slot "slot"); las páginas clásicas usan
                     @extends + @section('content'). Sin esta rama doble, el contenido
                     full-page quedaba fuera del layout (main vacío). --}}
                @isset($slot){{ $slot }}@else
                    @yield('content')
                @endisset
            </main>
        </x-layouts.app-shell>
    @else
        {{-- Páginas públicas (login, etc.) usan el layout simple --}}
        <main class="min-h-screen bg-brand-bg text-brand-text antialiased">
            @yield('content')
        </main>
    @endif
</body>

</html>