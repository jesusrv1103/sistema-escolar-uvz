<div>
    <x-ui.page-header title="Periodos"
        description="El cierre (cerrado_at) bloquea inscripciones, no operaciones académicas.">
        <x-slot:actions>
            <x-ui.button wire:click="crear" icon="plus">Nuevo periodo</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('catalogo'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('catalogo') }}</x-ui.alert></div>
    @endif

    <div class="mt-5">
        <x-ui.filters>
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" wire:click="limpiarFiltros">Limpiar filtros</x-ui.button>
            </x-slot:actions>
            <div class="grid gap-3 sm:grid-cols-3">
                <x-ui.field label="Buscar">
                    <x-ui.input type="search" wire:model.debounce.300ms="busqueda"
                        placeholder="Nombre o clave…" aria-label="Buscar periodos" />
                </x-ui.field>
            </div>
        </x-ui.filters>
    </div>

    <div class="mt-4">
        <x-ui.card :flush="$periodos->isNotEmpty()">
            @if ($periodos->isEmpty())
                <x-ui.empty-state icon="calendar" title="No hay periodos"
                    message="Crea el primer periodo para poder inscribir alumnos en un ciclo escolar.">
                    <x-slot:action>
                        <x-ui.button wire:click="crear" icon="plus">Nuevo periodo</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Catálogo de periodos escolares</caption>
                        <thead>
                            <tr>
                                <th scope="col">Clave</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Inicio</th>
                                <th scope="col">Fin</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($periodos as $periodo)
                                <tr wire:key="periodo-{{ $periodo->id }}">
                                    <td class="font-medium text-brand-primary">{{ $periodo->clave }}</td>
                                    <td class="font-medium text-brand-text">{{ $periodo->nombre }}</td>
                                    <td class="text-brand-muted">{{ $periodo->fecha_inicio?->format('d/m/Y') }}</td>
                                    <td class="text-brand-muted">{{ $periodo->fecha_fin?->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="inline-flex flex-wrap gap-1">
                                            <x-ui.badge :tone="$periodo->activo ? 'success' : 'neutral'">
                                                {{ $periodo->activo ? 'Activo' : 'Inactivo' }}
                                            </x-ui.badge>
                                            @if ($periodo->cerrado_at)
                                                <x-ui.badge tone="danger">Cerrado</x-ui.badge>
                                            @endif
                                        </span>
                                    </td>
                                    <td>
                                        <div class="ui-row-actions">
                                            <x-ui.icon-button label="Editar {{ $periodo->nombre }}" icon="cog"
                                                wire:click="editar({{ $periodo->id }})" />
                                            @if ($periodo->cerrado_at)
                                                <x-ui.icon-button label="Reabrir {{ $periodo->nombre }}" icon="check-circle"
                                                    wire:click="reabrir({{ $periodo->id }})" />
                                            @else
                                                <x-ui.icon-button label="Cerrar {{ $periodo->nombre }}" icon="clock"
                                                    variant="secondary" wire:click="cerrar({{ $periodo->id }})"
                                                    wire:confirm="¿Cerrar el periodo {{ $periodo->nombre }}? Se bloquearán las inscripciones." />
                                            @endif
                                            <x-ui.icon-button
                                                :label="$periodo->activo ? 'Desactivar' : 'Activar'"
                                                :icon="$periodo->activo ? 'no-symbol' : 'check-circle'"
                                                :variant="$periodo->activo ? 'secondary' : 'primary'"
                                                wire:click="alternarActivo({{ $periodo->id }})" />
                                            <x-ui.icon-button label="Eliminar {{ $periodo->nombre }}" icon="x-mark"
                                                variant="danger" wire:click="eliminar({{ $periodo->id }})"
                                                wire:confirm="¿Eliminar este periodo? (borrado lógico)" />
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

    <x-ui.modal id="modal-periodo"
        :title="$registroId ? 'Editar periodo' : 'Nuevo periodo'"
        :open="$formulario" close="cancelar">
        <form wire:submit="guardar" class="space-y-4">
            @if ($errors->any())
                <x-ui.alert tone="danger">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Clave" for="periodo-clave" required :error="$errors->first('clave')">
                    <x-ui.input id="periodo-clave" wire:model="clave" maxlength="20" placeholder="Ej. 2026-1" />
                </x-ui.field>

                <x-ui.field label="Nombre" for="periodo-nombre" required :error="$errors->first('nombre')">
                    <x-ui.input id="periodo-nombre" wire:model="nombre" maxlength="100"
                        placeholder="Enero – Junio 2026" />
                </x-ui.field>

                <x-ui.field label="Fecha de inicio" for="periodo-inicio" required
                    :error="$errors->first('fecha_inicio')">
                    <x-ui.input id="periodo-inicio" type="date" wire:model="fecha_inicio" />
                </x-ui.field>

                <x-ui.field label="Fecha de fin" for="periodo-fin" required :error="$errors->first('fecha_fin')">
                    <x-ui.input id="periodo-fin" type="date" wire:model="fecha_fin" />
                </x-ui.field>
            </div>

            <label class="inline-flex cursor-pointer items-center gap-2.5">
                <input type="checkbox" id="periodo-activo" wire:model="activo"
                    class="h-4 w-4 rounded border-brand-line accent-brand-primary" />
                <span class="text-sm font-medium text-brand-text">Activo</span>
            </label>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-brand-line pt-4">
                <x-ui.button variant="secondary" wire:click="cancelar">Cancelar</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">Guardar</span>
                    <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
