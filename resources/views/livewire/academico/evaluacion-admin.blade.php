<div>
    <x-ui.page-header title="Evaluaciones"
        :description="$grupo
            ? 'Catálogo de evaluaciones del grupo ' . $grupo->clave . ' (' . $grupo->materia->clave . ').'
            : 'Catálogo de evaluaciones de todos los grupos.'">
        <x-slot:meta>
            @if ($grupo)
                <x-ui.badge tone="primary">{{ $grupo->clave }}</x-ui.badge>
                <x-ui.badge tone="neutral">{{ $grupo->materia->clave }}</x-ui.badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            @can('evaluacion.crear')
                <x-ui.button icon="plus" wire:click="abrirModalCrear" :disabled="! $grupo">
                    Nueva Evaluación
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ------------------------- Filtros compactos ------------------------- --}}
    <div class="mt-5">
        <x-ui.filters>
            <div class="grid gap-3 md:grid-cols-3">
                <x-ui.field label="Buscar">
                    <x-ui.input type="text" wire:model.live.debounce.300ms="busqueda"
                        placeholder="Nombre de la evaluación…" aria-label="Buscar evaluación" />
                </x-ui.field>

                @unless ($grupo)
                    <x-ui.field label="Grupo">
                        <x-ui.select wire:model.live="filtroGrupo" aria-label="Filtrar por grupo">
                            <option value="">Todos los grupos</option>
                            @foreach ($grupos as $g)
                                <option value="{{ $g->id }}">{{ $g->clave }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                @endunless

                <x-ui.field label="Tipo">
                    <x-ui.select wire:model.live="filtroTipo" aria-label="Filtrar por tipo">
                        <option value="">Todos los tipos</option>
                        @foreach ($tipos as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
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

    {{-- ----------------------------- Listado ------------------------------ --}}
    <div class="mt-4">
        <x-ui.card :flush="count($evaluaciones) > 0">
            @if (session('success'))
                <div class="px-5 pt-4"><x-ui.alert tone="success">{{ session('success') }}</x-ui.alert></div>
            @endif
            @error('eliminar')
                <div class="px-5 pt-4"><x-ui.alert tone="danger">{{ $message }}</x-ui.alert></div>
            @enderror

            @if (count($evaluaciones) > 0)
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th scope="col">Nombre</th>
                                <th scope="col">Tipo</th>
                                <th scope="col" class="text-right">Ponderación</th>
                                <th scope="col">Fecha</th>
                                <th scope="col">Grupo</th>
                                <th scope="col">Creada por</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($evaluaciones as $evaluacion)
                                <tr wire:key="evaluacion-{{ $evaluacion->id }}">
                                    <td class="font-medium text-brand-text">{{ $evaluacion->nombre }}</td>
                                    <td>
                                        <x-ui.badge tone="info">
                                            {{ \App\Enums\TipoEvaluacion::EJEMPLOS[$evaluacion->tipo] ?? $evaluacion->tipo }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="text-right tabular-nums">{{ $evaluacion->ponderacion }}%</td>
                                    <td class="whitespace-nowrap text-brand-muted">
                                        {{ $evaluacion->fecha?->format('d/m/Y') ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap">
                                        {{ $evaluacion->grupo?->clave ?? '—' }}
                                        <span class="text-brand-muted">· {{ $evaluacion->grupo?->materia?->clave ?? '—' }}</span>
                                    </td>
                                    <td class="text-brand-muted">{{ $evaluacion->creadaPor?->name ?? '—' }}</td>
                                    <td class="text-right">
                                        <div class="ui-row-actions" role="group"
                                            aria-label="Acciones de {{ $evaluacion->nombre }}">
                                            @can('evaluacion.actualizar', $evaluacion)
                                                <x-ui.icon-button label="Editar {{ $evaluacion->nombre }}" icon="pencil"
                                                    wire:click="abrirModalEditar({{ $evaluacion->id }})" />
                                            @endcan
                                            @can('evaluacion.eliminar', $evaluacion)
                                                <x-ui.icon-button label="Eliminar {{ $evaluacion->nombre }}" icon="trash"
                                                    variant="danger"
                                                    wire:click="eliminar({{ $evaluacion->id }})"
                                                    wire:confirm="¿Eliminar la evaluación «{{ $evaluacion->nombre }}»? Esta acción no se puede deshacer." />
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-ui.empty-state icon="clipboard" title="Sin evaluaciones registradas"
                    message="Ajusta los filtros o crea la primera evaluación del grupo.">
                    <x-slot:action>
                        @can('evaluacion.crear')
                            <x-ui.button icon="plus" wire:click="abrirModalCrear" :disabled="! $grupo">
                                Nueva Evaluación
                            </x-ui.button>
                        @endcan
                    </x-slot:action>
                </x-ui.empty-state>
            @endif
        </x-ui.card>
    </div>

    @if (count($evaluaciones) > 0)
        <div class="mt-4">{{ $evaluaciones->links() }}</div>
    @endif

    {{-- ------------------------- Modales (hijos) -------------------------- --}}
    <livewire:academico.crear-evaluacion />
    <livewire:academico.editar-evaluacion />
</div>
