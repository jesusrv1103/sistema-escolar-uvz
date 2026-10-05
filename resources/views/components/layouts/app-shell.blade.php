{{--
  App shell administrativo (WAVE UI/UX 2.0-12).

  Estructura: sidebar colapsable (desktop) / drawer (mobile) + topbar con
  breadcrumb, usuario y cierre de sesión.

  IMPORTANTE — solo UX: la visibilidad de cada enlace usa @can, que en este
  proyecto es la MISMA capacidad que ya validan Policies/Gates server-side.
  Aquí no se decide autorización, solo se ocultan atajos a los que el usuario
  no tendría acceso. La autorización real sigue intacta.

  El estado (sidebar colapsado / drawer abierto) se maneja con JS vanilla
  embebido: sin dependencias nuevas, sin framework UI externo.
  Las reglas de colapso viven en resources/css/app.css (.sidebar-label, etc.).
--}}
@php
    /**
     * Navegación. Cada item:
     *  - label / icon
     *  - route: nombre de ruta a enlazar
     *  - match: patrón(es) de ruta para detectar el item activo
     *  - can:   capacidad Gate para mostrarlo (null/ausente = autenticado)
     *  - items: subitems (solo grupos)
     */
    $nav = [
        ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard', 'match' => 'dashboard'],
        ['label' => 'Alumnos', 'icon' => 'users', 'route' => 'alumnos.lista', 'match' => 'alumnos.*'],
        ['label' => 'Inscripciones', 'icon' => 'clipboard', 'route' => 'inscripciones.lista', 'match' => 'inscripciones.*'],

        [
            'label' => 'Académico',
            'icon' => 'academic-cap',
            'items' => [
                ['label' => 'Planes de estudio', 'icon' => 'folder', 'route' => 'admin.planes-estudio', 'match' => ['admin.planes-estudio', 'admin.versiones', 'admin.materias'], 'can' => 'usuario.manage'],
                ['label' => 'Evaluaciones', 'icon' => 'clipboard', 'route' => 'admin.evaluaciones', 'match' => 'admin.evaluaciones', 'can' => 'usuario.manage'],
                ['label' => 'Calificaciones', 'icon' => 'chart-bar', 'route' => 'admin.calificaciones', 'match' => 'admin.calificaciones', 'can' => 'usuario.manage'],
                ['label' => 'Asistencia', 'icon' => 'check-circle', 'route' => 'admin.asistencia', 'match' => 'admin.asistencia', 'can' => 'usuario.manage'],
                ['label' => 'Actas', 'icon' => 'document-text', 'route' => 'admin.actas', 'match' => 'admin.actas', 'can' => 'usuario.manage'],
                ['label' => 'Historial y avance', 'icon' => 'arrow-trending-up', 'route' => 'historial.index', 'match' => 'historial.*', 'can' => 'historial.ver'],
                ['label' => 'Formatos académicos', 'icon' => 'receipt', 'route' => 'formatos-academicos.index', 'match' => 'formatos-academicos.*', 'can' => 'formato_academico.generar'],
                ['label' => 'Bajas', 'icon' => 'no-symbol', 'route' => 'bajas-equivalencias.bajas', 'match' => 'bajas-equivalencias.*', 'can' => 'baja.ver'],
                ['label' => 'Equivalencias', 'icon' => 'scale', 'route' => 'bajas-equivalencias.equivalencias', 'match' => 'bajas-equivalencias.equivalencias', 'can' => 'equivalencia.ver'],
            ],
        ],

        [
            'label' => 'Finanzas',
            'icon' => 'banknotes',
            'items' => [
                ['label' => 'Becas y descuentos', 'icon' => 'percent', 'route' => 'becas-descuentos.index', 'match' => ['becas-descuentos.index', 'becas-descuentos.becas'], 'can' => 'beca.ver'],
                ['label' => 'Estado de cuenta', 'icon' => 'credit-card', 'route' => 'becas-descuentos.estado-cuenta', 'match' => 'becas-descuentos.estado-cuenta', 'can' => 'estado_cuenta.ver'],
                ['label' => 'Carta de no adeudo', 'icon' => 'document-text', 'route' => 'becas-descuentos.carta-no-adeudo', 'match' => 'becas-descuentos.carta-no-adeudo', 'can' => 'carta_no_adeudo.generar'],
                ['label' => 'Convenios', 'icon' => 'receipt', 'route' => 'admin.convenios', 'match' => 'admin.convenios', 'can' => 'usuario.manage'],
            ],
        ],

        [
            'label' => 'Catálogos',
            'icon' => 'squares',
            'items' => [
                ['label' => 'Programas', 'icon' => 'squares', 'route' => 'admin.programas', 'match' => 'admin.programas', 'can' => 'usuario.manage'],
                ['label' => 'Periodos', 'icon' => 'calendar', 'route' => 'admin.periodos', 'match' => 'admin.periodos', 'can' => 'usuario.manage'],
                ['label' => 'Docentes', 'icon' => 'user', 'route' => 'admin.docentes', 'match' => 'admin.docentes', 'can' => 'usuario.manage'],
                ['label' => 'Aulas', 'icon' => 'squares', 'route' => 'admin.aulas', 'match' => 'admin.aulas', 'can' => 'usuario.manage'],
                ['label' => 'Grupos', 'icon' => 'users', 'route' => 'admin.grupos', 'match' => 'admin.grupos*', 'can' => 'usuario.manage'],
            ],
        ],

        [
            'label' => 'Administración',
            'icon' => 'cog',
            'items' => [
                ['label' => 'Tipos de documento', 'icon' => 'document-text', 'route' => 'admin.tipos-documento', 'match' => 'admin.tipos-documento', 'can' => 'usuario.manage'],
            ],
        ],
    ];

    /** ¿Coincide la ruta actual con el patrón (string o lista)? */
    $matches = function ($patterns): bool {
        foreach ((array) $patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    };

    /** ¿Debe mostrarse este item/segmento según su capacidad Gate? */
    $visible = fn (?string $ability): bool => blank($ability) || auth()->user()->can($ability);

    // Filtrado de la navegación (solo visibilidad; la autorización real está
    // en Policies/Gates y en los middlewares de ruta).
    $menu = [];
    foreach ($nav as $node) {
        if (isset($node['items'])) {
            $children = array_values(array_filter($node['items'], fn ($child) => $visible($child['can'] ?? null)));
            if ($children !== []) {
                $node['items'] = $children;
                $menu[] = $node;
            }

            continue;
        }

        if ($visible($node['can'] ?? null)) {
            $menu[] = $node;
        }
    }

    // Breadcrumb / título de página derivados de la ruta activa.
    $title = 'Inicio';
    $group = null;
    foreach ($menu as $node) {
        if (isset($node['items'])) {
            foreach ($node['items'] as $child) {
                if ($matches($child['match'] ?? $child['route'])) {
                    $title = $child['label'];
                    $group = $node['label'];
                    break 2;
                }
            }
        } elseif ($matches($node['match'] ?? $node['route'])) {
            $title = $node['label'];
            break;
        }
    }

    $user = auth()->user();
@endphp

<div id="app-shell" data-sidebar="expanded" class="min-h-screen">

    {{-- Overlay del drawer (móvil / tablet) --}}
    <div id="sidebar-overlay" data-sidebar-close
        class="fixed inset-0 z-40 hidden bg-black/40 lg:hidden" aria-hidden="true"></div>

    {{-- ============================== SIDEBAR ============================== --}}
    <aside id="app-sidebar"
        class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-brand-line bg-white
               transition-[transform,width] duration-200 ease-out lg:w-64 lg:translate-x-0"
        aria-label="Navegación principal">

        {{-- Marca --}}
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-brand-line px-4">
            <a href="{{ route('dashboard') }}"
                class="flex min-w-0 items-center gap-3 rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
                aria-label="{{ config('branding.name') }}">
                <img src="{{ asset(config('branding.logo')) }}" alt="" width="1193" height="338"
                    class="h-8 w-auto shrink-0 lg:h-9">
                <span class="sidebar-label min-w-0 leading-tight">
                    <span class="block truncate text-sm font-semibold text-brand-text">{{ config('branding.name') }}</span>
                    <span class="block truncate text-xs text-brand-muted">{{ config('branding.system_name') }}</span>
                </span>
            </a>
        </div>

        {{-- Enlaces --}}
        <nav class="flex-1 overflow-y-auto px-3 py-4">
            <ul class="space-y-1">
                @foreach ($menu as $node)
                    @if (isset($node['items']))
                        @php
                            $isActive = collect($node['items'])->contains(
                                fn ($child) => $matches($child['match'] ?? $child['route'])
                            );
                        @endphp
                        <li>
                            <details {{ $isActive ? 'open' : '' }}>
                                <summary
                                    class="flex cursor-pointer list-none items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-brand-muted
                                           transition-colors hover:bg-brand-surface hover:text-brand-text
                                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary
                                           [&::-webkit-details-marker]:hidden">
                                    <x-ui.icon :name="$node['icon']" :size="18" />
                                    <span class="sidebar-label flex-1">{{ $node['label'] }}</span>
                                    <x-ui.icon name="chevron-down" :size="16" class="sidebar-chevron transition-transform duration-150" />
                                </summary>
                                <ul class="nav-children mt-1 space-y-1 pl-3">
                                    @foreach ($node['items'] as $child)
                                        @php $active = $matches($child['match'] ?? $child['route']); @endphp
                                        <li>
                                            <a href="{{ route($child['route']) }}"
                                                @if ($active) aria-current="page" @endif
                                                title="{{ $child['label'] }}"
                                                @class([
                                                    'nav-child flex items-center gap-3 rounded-lg py-2 pl-6 pr-3 text-sm transition-colors',
                                                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary',
                                                    'bg-brand-surface font-semibold text-brand-primary' => $active,
                                                    'text-brand-muted hover:bg-brand-surface hover:text-brand-text' => !$active,
                                                ])>
                                                <x-ui.icon :name="$child['icon']" :size="16" />
                                                <span class="sidebar-label truncate">{{ $child['label'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        </li>
                    @else
                        @php $active = $matches($node['match'] ?? $node['route']); @endphp
                        <li>
                            <a href="{{ route($node['route']) }}"
                                @if ($active) aria-current="page" @endif
                                title="{{ $node['label'] }}"
                                @class([
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
                                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary',
                                    'bg-brand-surface font-semibold text-brand-primary' => $active,
                                    'text-brand-muted hover:bg-brand-surface hover:text-brand-text' => !$active,
                                ])>
                                <x-ui.icon :name="$node['icon']" :size="18" />
                                <span class="sidebar-label truncate">{{ $node['label'] }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        {{-- Pie del sidebar --}}
        <div class="shrink-0 border-t border-brand-line px-3 py-3">
            <p class="sidebar-label px-1 text-[11px] leading-relaxed text-brand-muted">
                {{ config('branding.slogan') }} · {{ config('branding.hashtag') }}
            </p>
        </div>
    </aside>

    {{-- ============================= CONTENIDO ============================= --}}
    <div id="app-content" class="flex min-h-screen flex-col transition-[padding] duration-200 lg:pl-64">

        {{-- TOPBAR --}}
        <header
            class="sticky top-0 z-30 border-b border-brand-line bg-white/90 backdrop-blur supports-[backdrop-filter]:bg-white/75">
            <div class="flex h-16 items-center gap-3 px-4 sm:px-6">

                {{-- Abrir drawer (móvil) --}}
                <button type="button" data-sidebar-open
                    class="rounded-lg p-2 text-brand-muted transition-colors hover:bg-brand-surface hover:text-brand-text
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary lg:hidden"
                    aria-label="Abrir navegación" aria-controls="app-sidebar">
                    <x-ui.icon name="bars-3" :size="22" />
                </button>

                {{-- Colapsar sidebar (escritorio) --}}
                <button type="button" data-sidebar-toggle
                    class="hidden rounded-lg p-2 text-brand-muted transition-colors hover:bg-brand-surface hover:text-brand-text
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary lg:block"
                    aria-label="Colapsar barra lateral" aria-controls="app-sidebar">
                    <x-ui.icon name="chevron-left" :size="20" />
                </button>

                {{-- Breadcrumb / título --}}
                <div class="min-w-0 flex-1">
                    <nav aria-label="Ruta de navegación" class="hidden text-xs text-brand-muted sm:block">
                        <ol class="flex items-center gap-1.5">
                            <li>
                                <a href="{{ route('dashboard') }}"
                                    class="rounded transition-colors hover:text-brand-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary">Inicio</a>
                            </li>
                            @if ($group)
                                <li aria-hidden="true"><x-ui.icon name="chevron-right" :size="12" /></li>
                                <li>{{ $group }}</li>
                            @endif
                            <li aria-hidden="true"><x-ui.icon name="chevron-right" :size="12" /></li>
                            <li class="font-medium text-brand-text" aria-current="page">{{ $title }}</li>
                        </ol>
                    </nav>
                    <p class="truncate text-sm font-semibold text-brand-text sm:hidden">{{ $title }}</p>
                </div>

                {{-- Usuario --}}
                <div class="flex items-center gap-3">
                    <span class="hidden items-center gap-2 md:flex">
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-surface text-xs font-semibold text-brand-primary">
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) }}
                        </span>
                        <span class="leading-tight">
                            <span class="block max-w-[11rem] truncate text-sm font-medium text-brand-text">{{ $user->name }}</span>
                            <span class="block text-[11px] text-brand-muted">{{ $user->rol->label() }}</span>
                        </span>
                    </span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg border border-brand-line px-3 py-2 text-sm font-medium text-brand-text
                                   transition-colors hover:bg-brand-surface
                                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
                            title="Cerrar sesión">
                            <x-ui.icon name="log-out" :size="16" />
                            <span class="hidden sm:inline">Cerrar sesión</span>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        {{-- Slot: el layout inyecta aquí el <main> con el contenido de la página --}}
        {{ $slot }}
    </div>
</div>

<script>
    (function () {
        var KEY = 'uvz.sidebar';
        var shell = document.getElementById('app-shell');
        var sidebar = document.getElementById('app-sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        var content = document.getElementById('app-content');
        if (!shell || !sidebar || !content) return;

        var desktop = function () { return window.matchMedia('(min-width: 1024px)').matches; };
        var stored = null;
        try { stored = localStorage.getItem(KEY); } catch (e) {}

        function apply() {
            var collapsed = stored === 'collapsed' && desktop();
            shell.setAttribute('data-sidebar', collapsed ? 'collapsed' : 'expanded');
            sidebar.classList.toggle('lg:w-20', collapsed);
            sidebar.classList.toggle('lg:w-64', !collapsed);
            content.classList.toggle('lg:pl-20', collapsed);
            content.classList.toggle('lg:pl-64', !collapsed);
            var btn = shell.querySelector('[data-sidebar-toggle]');
            if (btn) btn.setAttribute('aria-expanded', String(!collapsed));
        }

        function openDrawer() {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            var first = sidebar.querySelector('a, summary, button');
            if (first) first.focus();
        }

        function closeDrawer() {
            if (desktop()) return;
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-sidebar-toggle]')) {
                stored = (shell.getAttribute('data-sidebar') === 'collapsed') ? 'expanded' : 'collapsed';
                try { localStorage.setItem(KEY, stored); } catch (err) {}
                apply();
                return;
            }
            if (e.target.closest('[data-sidebar-open]')) { openDrawer(); return; }
            if (e.target.closest('[data-sidebar-close]')) { closeDrawer(); return; }
            if (e.target.closest('#app-sidebar a') && !desktop()) closeDrawer();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeDrawer();
        });

        window.addEventListener('resize', function () {
            if (desktop()) {
                overlay.classList.add('hidden');
                sidebar.classList.remove('-translate-x-full');
                document.body.classList.remove('overflow-hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
            }
            apply();
        });

        if (!desktop()) sidebar.classList.add('-translate-x-full');
        apply();
    })();
</script>
