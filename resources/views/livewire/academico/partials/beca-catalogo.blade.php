<div>
    <x-ui.card :flush="$becas->isNotEmpty()">
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-brand-text">Catálogo de becas</p>
                    <p class="mt-0.5 text-xs text-brand-muted">
                        Porcentaje sobre cargos o monto fijo, con vigencia por periodo.
                    </p>
                </div>
                @can('beca.crear')
                    <x-ui.button size="sm" icon="plus" wire:click="abrirModalBeca">Nueva beca</x-ui.button>
                @endcan
            </div>
        </x-slot:header>

        <div class="border-b border-brand-line px-5 py-4">
            <div class="grid gap-3 md:grid-cols-4">
                <x-ui.field label="Buscar">
                    <x-ui.input type="text" wire:model.live.debounce.300ms="busqueda"
                        placeholder="Nombre o clave…" aria-label="Buscar becas" />
                </x-ui.field>

                <x-ui.field label="Estatus">
                    <x-ui.select wire:model.live="filtroEstatus" aria-label="Filtrar por estatus">
                        <option value="">Todos los estatus</option>
                        <option value="ACTIVA">Activas</option>
                        <option value="INACTIVA">Inactivas</option>
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Periodo">
                    <x-ui.select wire:model.live="filtroPeriodo" aria-label="Filtrar por periodo">
                        <option value="">Todos los periodos</option>
                        @foreach ($periodos as $p)
                            <option value="{{ $p->id }}">{{ $p->clave }} - {{ $p->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <div class="flex items-end">
                    <x-ui.button size="sm" variant="ghost" icon="x-mark" block wire:click="limpiarFiltros">
                        Limpiar filtros
                    </x-ui.button>
                </div>
            </div>
        </div>

        @if ($becas->isEmpty())
            <x-ui.empty-state icon="percent" title="Sin becas registradas"
                message="Ajusta los filtros o crea la primera beca con el botón del encabezado.">
                <x-slot:action>
                    @can('beca.crear')
                        <x-ui.button icon="plus" wire:click="abrirModalBeca">Nueva beca</x-ui.button>
                    @endcan
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th scope="col">Clave</th>
                            <th scope="col">Nombre</th>
                            <th scope="col">Tipo</th>
                            <th scope="col" class="text-right">Valor</th>
                            <th scope="col">Alcance</th>
                            <th scope="col">Periodo</th>
                            <th scope="col">Vigencia</th>
                            <th scope="col">Estatus</th>
                            <th scope="col" class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($becas as $b)
                            <tr wire:key="beca-{{ $b->id }}">
                                <td class="font-mono text-xs">{{ $b->clave }}</td>
                                <td class="font-medium text-brand-text">{{ $b->nombre }}</td>
                                <td>
                                    <x-ui.badge :tone="$b->tipo->value === 'PORCENTAJE' ? 'info' : 'primary'">
                                        {{ $b->tipo->label() }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-right tabular-nums">
                                    {{ $b->tipo->value === 'PORCENTAJE' ? $b->valor . '%' : '$' . number_format($b->valor, 2) }}
                                </td>
                                <td class="text-brand-muted">{{ $b->alcance->label() }}</td>
                                <td class="whitespace-nowrap text-brand-muted">{{ $b->periodo->clave }}</td>
                                <td class="whitespace-nowrap text-brand-muted">
                                    {{ $b->fecha_inicio?->format('d/m/Y') ?? '—' }} -
                                    {{ $b->fecha_fin?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td>
                                    <x-ui.badge :tone="$b->activo ? 'success' : 'neutral'">
                                        {{ $b->activo ? 'Activa' : 'Inactiva' }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-right">
                                    <div class="ui-row-actions" role="group" aria-label="Acciones de la beca {{ $b->nombre }}">
                                        @can('beca.actualizar', $b)
                                            <x-ui.icon-button label="Editar la beca {{ $b->nombre }}" icon="pencil"
                                                wire:click="abrirModalEditar({{ $b->id }})" />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-brand-line px-5 py-3">{{ $becas->links() }}</div>
        @endif
    </x-ui.card>
</div>
