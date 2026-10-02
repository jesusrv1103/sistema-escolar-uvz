<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-brand-text">Inscripciones</h1>
            <p class="text-sm text-brand-muted">Listado operativo · continúa o revisa los borradores de inscripción</p>
        </div>
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('dashboard') }}" class="font-medium text-brand-primary hover:text-brand-secondary">Dashboard</a>
            @if ($puedeCrear)
                <a href="{{ route('inscripciones.nueva') }}"
                    class="rounded-md bg-brand-primary px-3 py-2 text-sm font-semibold text-white hover:bg-brand-secondary">
                    Nueva inscripción
                </a>
            @endif
        </div>
    </div>

    {{-- Filtros mínimos: alumno, periodo, estatus (§9 / Fase 6) --}}
    <div class="mb-4 rounded-2xl border border-brand-line bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label for="filtro-busqueda" class="block text-sm font-medium text-brand-text">Buscar alumno</label>
                <input id="filtro-busqueda" type="search" wire:model.live.debounce.300ms="busqueda"
                    placeholder="Nombre, apellidos, CURP o número de control"
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
            </div>
            <div>
                <label for="filtro-periodo" class="block text-sm font-medium text-brand-text">Periodo</label>
                <select id="filtro-periodo" wire:model.live="filtroPeriodo"
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                    <option value="">Todos</option>
                    @foreach ($periodos as $periodo)
                        <option value="{{ $periodo->id }}">{{ $periodo->clave }} — {{ $periodo->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtro-estatus" class="block text-sm font-medium text-brand-text">Estatus</label>
                <select id="filtro-estatus" wire:model.live="filtroEstatus"
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                    <option value="">Todos</option>
                    @foreach ($estatusOpciones as $opcion)
                        <option value="{{ $opcion->value }}">{{ $opcion->value }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3 flex items-center justify-between text-xs text-brand-muted">
            <span>Máximo 50 resultados · sin información médica en este listado (RB-18).</span>
            <button type="button" wire:click="limpiarFiltros" class="font-medium text-brand-primary hover:underline">
                Limpiar filtros
            </button>
        </div>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-brand-line bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="border-b border-brand-line bg-brand-surface text-left text-xs uppercase text-brand-muted">
                <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">Alumno</th>
                    <th class="px-4 py-3">Programa</th>
                    <th class="px-4 py-3">Periodo</th>
                    <th class="px-4 py-3">Estatus</th>
                    <th class="px-4 py-3">Creada</th>
                    <th class="px-4 py-3 text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-line">
                @forelse ($inscripciones as $inscripcion)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-brand-primary">{{ $inscripcion->id }}</td>
                        <td class="px-4 py-3">
                            {{ $inscripcion->alumno?->apellidos }}, {{ $inscripcion->alumno?->nombre }}
                            <span class="block font-mono text-xs text-brand-muted">{{ $inscripcion->alumno?->curp }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $inscripcion->programa?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $inscripcion->periodo?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full border px-2 py-0.5 text-xs font-semibold',
                                'border-brand-line bg-brand-surface text-brand-muted' => $inscripcion->estatus === \App\Enums\InscripcionEstatus::DRAFT,
                                'border-brand-primary bg-white text-brand-primary' => $inscripcion->estatus !== \App\Enums\InscripcionEstatus::DRAFT,
                            ])>
                                {{ $inscripcion->estatus->value }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-brand-muted">{{ $inscripcion->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('inscripciones.documentos', $inscripcion) }}" class="font-medium text-brand-primary hover:underline">
                                Documentos
                            </a>
                            <span class="mx-1 text-brand-muted">·</span>
                            <a href="{{ route('inscripciones.observaciones', $inscripcion) }}" class="font-medium text-brand-primary hover:underline">
                                Observaciones
                            </a>
                            <span class="mx-1 text-brand-muted">·</span>
                            <a href="{{ route('inscripciones.revision-final', $inscripcion) }}" class="font-medium text-brand-primary hover:underline">
                                Revisión final
                            </a>
                            <span class="mx-1 text-brand-muted">·</span>
                            <a href="{{ route('inscripciones.ver', $inscripcion) }}" class="font-medium text-brand-primary hover:underline">
                                {{ $inscripcion->estatus === \App\Enums\InscripcionEstatus::DRAFT ? 'Continuar' : 'Ver' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-brand-muted">
                            {{ ($busqueda === '' && $filtroPeriodo === null && $filtroEstatus === '')
                                ? 'Sin inscripciones registradas.'
                                : 'Sin resultados para los filtros seleccionados.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
