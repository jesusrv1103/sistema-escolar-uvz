<div>
    <x-ui.page-header title="Actas de Calificaciones"
        description="Cada grupo puede tener un solo acta: se abre sin folio y el folio se emite al cerrarla.">
        <x-slot:actions>
            @can('acta.crear')
                <x-ui.button icon="plus" wire:click="abrirModalCrear">Nueva Acta</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('success'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('success') }}</x-ui.alert></div>
    @endif
    @error('cerrar')
        <div class="mt-4"><x-ui.alert tone="danger">{{ $message }}</x-ui.alert></div>
    @enderror

    {{-- ------------------------- Filtros compactos ------------------------- --}}
    <div class="mt-5">
        <x-ui.filters>
            <div class="grid gap-3 md:grid-cols-3">
                <x-ui.field label="Buscar">
                    <x-ui.input type="text" wire:model.live.debounce.300ms="busqueda"
                        placeholder="Clave de grupo…" aria-label="Buscar por clave de grupo" />
                </x-ui.field>

                <x-ui.field label="Periodo">
                    <x-ui.select wire:model.live="filtroPeriodo" aria-label="Filtrar por periodo">
                        <option value="">Todos</option>
                        @foreach ($periodos as $p)
                            <option value="{{ $p->id }}">{{ $p->clave }} - {{ $p->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Estatus">
                    <x-ui.select wire:model.live="filtroEstatus" aria-label="Filtrar por estatus">
                        <option value="">Todas</option>
                        <option value="ABIERTA">Abierta</option>
                        <option value="CERRADA">Cerrada</option>
                    </x-ui.select>
                </x-ui.field>
            </div>

            <div class="mt-3 flex justify-end border-t border-brand-line pt-3">
                <x-ui.button size="sm" variant="ghost" icon="x-mark" wire:click="limpiarFiltros">
                    Limpiar filtros
                </x-ui.button>
            </div>
        </x-ui.filters>
    </div>

    {{-- ------------------------------ Listado ------------------------------ --}}
    <div class="mt-4">
        <x-ui.card :flush="count($actas) > 0">
            @if (count($actas) === 0)
                <x-ui.empty-state icon="clipboard" title="Sin actas registradas"
                    message="Ajusta los filtros o crea el primer acta de calificaciones.">
                    <x-slot:action>
                        @can('acta.crear')
                            <x-ui.button icon="plus" wire:click="abrirModalCrear">Nueva Acta</x-ui.button>
                        @endcan
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th scope="col">Grupo</th>
                                <th scope="col">Materia</th>
                                <th scope="col">Periodo</th>
                                <th scope="col">Estatus</th>
                                <th scope="col">Folio</th>
                                <th scope="col">Cerrada por</th>
                                <th scope="col">Cerrada el</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($actas as $acta)
                                <tr wire:key="acta-{{ $acta->id }}">
                                    <td class="font-medium text-brand-text">{{ $acta->grupo->clave }}</td>
                                    <td>
                                        {{ $acta->grupo->materia->clave }}
                                        <span class="text-brand-muted">· {{ $acta->grupo->materia->nombre }}</span>
                                    </td>
                                    <td class="whitespace-nowrap text-brand-muted">{{ $acta->periodo->clave }}</td>
                                    <td>
                                        <x-ui.badge :tone="$acta->esCerrada() ? 'success' : 'warning'">
                                            {{ $acta->esCerrada() ? 'Cerrada' : 'Abierta' }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="font-mono text-xs">{{ $acta->folio ?? '—' }}</td>
                                    <td class="text-brand-muted">{{ $acta->cerradaPor?->name ?? '—' }}</td>
                                    <td class="whitespace-nowrap text-brand-muted">
                                        {{ $acta->cerrada_at?->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    <td class="text-right">
                                        @if ($acta->esAbierta())
                                            <div class="ui-row-actions" role="group"
                                                aria-label="Acciones del acta {{ $acta->grupo->clave }}">
                                                <x-ui.button size="sm" variant="success" icon="check-circle"
                                                    wire:click="cerrar({{ $acta->id }})"
                                                    wire:loading.attr="disabled" wire:target="cerrar">
                                                    <span wire:loading.remove wire:target="cerrar">Cerrar</span>
                                                    <span wire:loading wire:target="cerrar"><x-ui.loading label="Cerrando…" /></span>
                                                </x-ui.button>
                                            </div>
                                        @else
                                            <span class="text-xs text-brand-muted">Acta cerrada (inmutable)</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>

    @if (count($actas) > 0)
        <div class="mt-4">{{ $actas->links() }}</div>
    @endif

    {{-- Modal de creación --}}
    <livewire:academico.crear-acta />
</div>
