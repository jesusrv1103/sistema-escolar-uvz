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
    <header class="border-b border-brand-line bg-white">
        <nav class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3"
            aria-label="Navegación principal">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3" aria-label="{{ config('branding.name') }}">
                <img src="{{ asset(config('branding.logo')) }}" alt="" class="h-9 w-auto sm:h-10"
                    width="1193" height="338">
                <span class="leading-tight">
                    <span class="hidden text-sm font-semibold text-brand-text sm:block">
                        {{ config('branding.name') }}
                    </span>
                    <span class="block text-sm font-bold tracking-wide text-brand-primary sm:hidden">
                        {{ config('branding.short_name') }}
                    </span>
                    <span class="block text-xs text-brand-muted">{{ config('branding.system_name') }}</span>
                </span>
            </a>

            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('alumnos.lista') }}"
                    class="hidden font-medium text-brand-primary hover:text-brand-secondary sm:block">Alumnos</a>
                <a href="{{ route('inscripciones.lista') }}"
                    class="hidden font-medium text-brand-primary hover:text-brand-secondary sm:block">Inscripciones</a>
                @can('usuario.manage')
                    <a href="{{ route('admin.programas') }}"
                        class="hidden font-medium text-brand-primary hover:text-brand-secondary sm:block">Catálogos</a>
                    <a href="{{ route('admin.planes-estudio') }}"
                        class="hidden font-medium text-brand-primary hover:text-brand-secondary sm:block">Estructura académica</a>
                @endcan
                @auth
                    <span class="hidden text-brand-muted sm:block">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="rounded-md border border-brand-line px-3 py-1.5 font-medium text-brand-text hover:bg-brand-surface">
                            Cerrar sesión
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="font-medium text-brand-primary hover:text-brand-secondary">
                        Iniciar sesión
                    </a>
                @endauth
            </div>
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        {{-- Render full-page de Livewire: inyecta el componente como $slot
             (rama @component con slot "slot"); las páginas clásicas usan
             @extends + @section('content'). Sin esta rama doble, el contenido
             full-page quedaba fuera del layout (main vacío). --}}
        @isset($slot){{ $slot }}@else
            @yield('content')
        @endisset
    </main>
</body>

</html>
