<div>
    <x-ui.page-header title="Inscripciones"
        description="Listado operativo: continúa o revisa los borradores de inscripción.">
        <x-slot:actions>
            @if ($puedeCrear)
                <x-ui.button href="{{ route('inscripciones.nueva') }}" icon="plus">Nueva inscripción</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Filtros mínimos: alumno, periodo, estatus (§9 / Fase 6) --}}
    <div class="mt-5">
        <x-ui.filters>
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" wire:click="limpiarFiltros">Limpiar filtros</x-ui.button>
            </x-slot:actions>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field label="Buscar alumno" class="lg:col-span-2"
                    hint="Máximo 50 resultados · sin información médica en este listado (RB-18).">
                    <x-ui.input id="filtro-busqueda" type="search" wire:model.live.debounce.300ms="busqueda"
                        placeholder="Nombre, apellidos, CURP o número de control" aria-label="Buscar alumno" />
                </x-ui.field>

                <x-ui.field label="Periodo">
                    <x-ui.select id="filtro-periodo" wire:model.live="filtroPeriodo" aria-label="Filtrar por periodo">
                        <option value="">Todos</option>
                        @foreach ($periodos as $periodo)
                            <option value="{{ $periodo->id }}">{{ $periodo->clave }} — {{ $periodo->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Estatus">
                    <x-ui.select id="filtro-estatus" wire:model.live="filtroEstatus" aria-label="Filtrar por estatus">
                        <option value="">Todos</option>
                        @foreach ($estatusOpciones as $opcion)
                            {{-- value técnico (query/BD) · texto visible en español --}}
                            <option value="{{ $opcion->value }}">{{ $opcion->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            </div>
        </x-ui.filters>
    </div>

    <div class="mt-4">
        <x-ui.card :flush="count($inscripciones) > 0">
            @if (count($inscripciones) === 0)
                <x-ui.empty-state icon="clipboard"
                    :title="($busqueda === '' && $filtroPeriodo === null && $filtroEstatus === '')
                        ? 'Sin inscripciones registradas'
                        : 'Sin resultados para los filtros seleccionados'"
                    message="Ajusta los filtros o inicia una nueva inscripción desde el botón superior.">
                    <x-slot:action>
                        @if ($busqueda !== '' || $filtroPeriodo !== null || $filtroEstatus !== '')
                            <x-ui.button variant="secondary" wire:click="limpiarFiltros">Limpiar filtros</x-ui.button>
                        @endif
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Listado de inscripciones</caption>
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Alumno</th>
                                <th scope="col">Programa</th>
                                <th scope="col">Periodo</th>
                                <th scope="col">Estatus</th>
                                <th scope="col">Creada</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inscripciones as $inscripcion)
                                <tr wire:key="inscripcion-{{ $inscripcion->id }}">
                                    <td class="font-semibold text-brand-primary">{{ $inscripcion->id }}</td>
                                    <td>
                                        <span class="block font-medium text-brand-text">
                                            {{ $inscripcion->alumno?->apellidos }}, {{ $inscripcion->alumno?->nombre }}
                                        </span>
                                        <span class="block font-mono text-xs text-brand-muted">
                                            {{ $inscripcion->alumno?->numero_control ?? 'Sin N.º de control' }}
                                            · CURP {{ $inscripcion->alumno?->curp }}
                                        </span>
                                    </td>
                                    <td class="text-brand-muted">{{ $inscripcion->programa?->nombre ?? '—' }}</td>
                                    <td class="text-brand-muted">{{ $inscripcion->periodo?->nombre ?? '—' }}</td>
                                    <td>
                                        <x-ui.badge
                                            :tone="match ($inscripcion->estatus->value) {
                                                'DRAFT' => 'neutral',
                                                'ENROLLED' => 'success',
                                                'CANCELLED' => 'danger',
                                                default => 'primary',
                                            }">
                                            {{ $inscripcion->estatus->label() }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="text-xs text-brand-muted">
                                        {{ $inscripcion->created_at?->format('d/m/Y H:i') }}
                                    </td>
                                    <td>
                                        <div class="ui-row-actions whitespace-nowrap">
                                            <x-ui.icon-button label="Documentos" icon="document-text" variant="secondary"
                                                :href="route('inscripciones.documentos', $inscripcion)" />
                                            <x-ui.icon-button label="Observaciones" icon="clock" variant="secondary"
                                                :href="route('inscripciones.observaciones', $inscripcion)" />
                                            <x-ui.icon-button label="Revisión final" icon="check-circle"
                                                variant="secondary"
                                                :href="route('inscripciones.revision-final', $inscripcion)" />
                                            <x-ui.button size="sm" variant="primary"
                                                :href="route('inscripciones.ver', $inscripcion)">
                                                {{ $inscripcion->estatus === \App\Enums\InscripcionEstatus::DRAFT ? 'Continuar' : 'Ver' }}
                                            </x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
