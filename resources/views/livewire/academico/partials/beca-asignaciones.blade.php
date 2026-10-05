<div>
    <x-ui.card :flush="$asignaciones->isNotEmpty()">
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-brand-text">Asignaciones de becas</p>
                    <p class="mt-0.5 text-xs text-brand-muted">
                        Cada asignación debe ser aprobada o rechazada antes de surtir efecto.
                    </p>
                </div>
                @can('beca.asignar')
                    <x-ui.button size="sm" icon="plus" wire:click="abrirModalAsignacion">Asignar beca</x-ui.button>
                @endcan
            </div>
        </x-slot:header>

        <div class="border-b border-brand-line px-5 py-4">
            <div class="grid gap-3 md:grid-cols-4">
                <x-ui.field label="Buscar alumno">
                    <x-ui.input type="text" wire:model.live.debounce.300ms="busqueda"
                        placeholder="Nombre, CURP, control…" aria-label="Buscar asignaciones" />
                </x-ui.field>

                <x-ui.field label="Estatus">
                    <x-ui.select wire:model.live="filtroEstatus" aria-label="Filtrar por estatus">
                        <option value="">Todos</option>
                        @foreach (\App\Enums\EstatusBeca::cases() as $e)
                            <option value="{{ $e->value }}">{{ $e->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Periodo">
                    <x-ui.select wire:model.live="filtroPeriodo" aria-label="Filtrar por periodo">
                        <option value="">Todos</option>
                        @foreach ($periodos as $p)
                            <option value="{{ $p->id }}">{{ $p->clave }}</option>
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

        @if ($asignaciones->isEmpty())
            <x-ui.empty-state icon="academic-cap" title="Sin asignaciones registradas"
                message="Ajusta los filtros o asigna la primera beca con el botón del encabezado.">
                <x-slot:action>
                    @can('beca.asignar')
                        <x-ui.button icon="plus" wire:click="abrirModalAsignacion">Asignar beca</x-ui.button>
                    @endcan
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th scope="col">Beca</th>
                            <th scope="col">Alumno</th>
                            <th scope="col">Periodo</th>
                            <th scope="col">Estatus</th>
                            <th scope="col">Asignada por</th>
                            <th scope="col">Aprobada por</th>
                            <th scope="col">Fecha aprob.</th>
                            <th scope="col" class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($asignaciones as $a)
                            <tr wire:key="asignacion-{{ $a->id }}">
                                <td class="font-medium text-brand-text">
                                    {{ $a->beca->clave }} - {{ $a->beca->nombre }}
                                </td>
                                <td>{{ $a->alumno->nombre_completo }}</td>
                                <td class="whitespace-nowrap text-brand-muted">{{ $a->periodo->clave }}</td>
                                <td>
                                    <x-ui.badge :tone="match ($a->estatus->value) {
                                        'APROBADA' => 'success',
                                        'RECHAZADA' => 'danger',
                                        default => 'warning',
                                    }">
                                        {{ $a->estatus->label() }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-brand-muted">{{ $a->asignadaPor?->name ?? '—' }}</td>
                                <td class="text-brand-muted">{{ $a->aprobadaPor?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap text-brand-muted">
                                    {{ $a->fecha_aprobacion?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td class="text-right">
                                    @if ($a->esPendiente())
                                        <div class="ui-row-actions" role="group"
                                            aria-label="Acciones de la asignación a {{ $a->alumno->nombre_completo }}">
                                            @can('beca.aprobar', $a)
                                                <x-ui.icon-button label="Aprobar asignación" icon="check" variant="success"
                                                    wire:click="aprobar({{ $a->id }})"
                                                    wire:loading.attr="disabled" wire:target="aprobar" />
                                            @endcan
                                            @can('beca.rechazar', $a)
                                                <x-ui.icon-button label="Rechazar asignación" icon="x-mark" variant="danger"
                                                    wire:click="rechazar({{ $a->id }})"
                                                    wire:confirm="¿Rechazar esta asignación de beca? Esta acción no se puede deshacer." />
                                            @endcan
                                        </div>
                                    @else
                                        <span class="text-xs text-brand-muted">Resuelta</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-brand-line px-5 py-3">{{ $asignaciones->links() }}</div>
        @endif
    </x-ui.card>
</div>
