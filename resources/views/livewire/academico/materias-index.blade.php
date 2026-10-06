<div>
    {{-- ============ ENTRADA A MATERIAS: programa → plan → versión ========== --}}
    <x-ui.page-header title="Materias"
        description="Consulta global de materias: filtra por programa, plan y versión para entrar a gestionar cada materia dentro de su versión.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="folder" href="{{ route('admin.planes-estudio') }}">
                Planes de estudio
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('success'))
        <div class="mt-4">
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        </div>
    @endif

    {{-- ============================== FILTROS ============================= --}}
    <div class="mt-5">
        <x-ui.filters>
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" wire:click="limpiarFiltros">Limpiar filtros</x-ui.button>
            </x-slot:actions>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field label="Buscar">
                    <x-ui.input type="search" wire:model.debounce.300ms="busqueda"
                        placeholder="Clave o nombre…" aria-label="Buscar materias" />
                </x-ui.field>

                <x-ui.field label="Programa">
                    <x-ui.select wire:model.live="filtroPrograma" aria-label="Filtrar por programa">
                        <option value="">Todos</option>
                        @foreach ($programas as $programa)
                            <option value="{{ $programa->id }}" @selected($filtroPrograma === (string) $programa->id)>
                                {{ $programa->nombre }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Plan de estudio">
                    <x-ui.select wire:model.live="filtroPlan" aria-label="Filtrar por plan de estudio">
                        <option value="">Todos</option>
                        @foreach ($planes as $plan)
                            <option value="{{ $plan->id }}" @selected($filtroPlan === (string) $plan->id)>
                                {{ $plan->clave }} — {{ $plan->nombre }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Versión">
                    <x-ui.select wire:model.live="filtroVersion" aria-label="Filtrar por versión">
                        <option value="">Todas</option>
                        @foreach ($versiones as $v)
                            <option value="{{ $v->id }}" @selected($filtroVersion === (string) $v->id)>
                                {{ $v->plan->clave ?? 'Plan' }} · versión {{ $v->version }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            </div>
        </x-ui.filters>
    </div>

    {{-- ============================ RESULTADOS =========================== --}}
    <div class="mt-4">
        <x-ui.card :flush="$materias->isNotEmpty()">
            @if ($materias->isEmpty())
                <x-ui.empty-state icon="academic-cap" title="No hay materias que mostrar"
                    message="Crea un plan de estudio, su versión y las materias correspondientes para verlas aquí.">
                    <x-slot:action>
                        <x-ui.button variant="secondary" icon="folder" href="{{ route('admin.planes-estudio') }}">
                            Ir a planes de estudio
                        </x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Listado global de materias</caption>
                        <thead>
                            <tr>
                                <th scope="col">Clave</th>
                                <th scope="col">Materia</th>
                                <th scope="col">Programa</th>
                                <th scope="col">Plan</th>
                                <th scope="col">Versión</th>
                                <th scope="col">Cuatrimestre</th>
                                <th scope="col">Créditos</th>
                                <th scope="col">Obligatoria</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($materias as $m)
                                @php
                                    $versionMateria = $m->planVersion;
                                    $planMateria = $versionMateria?->plan;
                                @endphp
                                <tr wire:key="materia-global-{{ $m->id }}">
                                    <td class="font-medium text-brand-primary">{{ $m->clave }}</td>
                                    <td class="font-medium text-brand-text">{{ $m->nombre }}</td>
                                    <td class="text-brand-muted">{{ $planMateria?->programa?->nombre ?? '—' }}</td>
                                    <td class="text-brand-muted">
                                        {{ $planMateria?->clave ?? '—' }}
                                        @if ($planMateria?->nombre)
                                            <span class="block text-xs">{{ $planMateria->nombre }}</span>
                                        @endif
                                    </td>
                                    <td class="text-brand-muted">{{ $versionMateria?->version ?? '—' }}</td>
                                    <td><x-ui.badge tone="info">Cuatrimestre {{ $m->periodo_orden }}</x-ui.badge></td>
                                    <td class="text-brand-muted">{{ $m->creditos }}</td>
                                    <td>
                                        <x-ui.badge :tone="$m->obligatoria ? 'danger' : 'neutral'">
                                            {{ $m->obligatoria ? 'Obligatoria' : 'Optativa' }}
                                        </x-ui.badge>
                                    </td>
                                    <td>
                                        <x-ui.badge :tone="$m->activo ? 'success' : 'neutral'">
                                            {{ $m->activo ? 'Activa' : 'Inactiva' }}
                                        </x-ui.badge>
                                    </td>
                                    <td>
                                        <div class="ui-row-actions">
                                            @if ($versionMateria)
                                                <x-ui.button size="sm" variant="secondary" icon="academic-cap"
                                                    href="{{ route('admin.materias', [
                                                        'plan' => $planMateria->id,
                                                        'version' => $versionMateria->id,
                                                    ]) }}#materia-{{ $m->id }}">
                                                    Gestionar
                                                </x-ui.button>
                                            @else
                                                <span class="text-sm text-brand-muted" aria-label="Sin versión asociada">—</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-brand-line px-5 py-3">
                    {{ $materias->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
