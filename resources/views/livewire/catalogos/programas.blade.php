<div>
    <x-ui.page-header title="Programas"
        description="Catálogo administrable · la clave es el identificador interno, no el RVOE.">
        <x-slot:actions>
            <x-ui.button wire:click="crear" icon="plus">Nuevo programa</x-ui.button>
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
                        placeholder="Nombre o clave…" aria-label="Buscar programas" />
                </x-ui.field>
            </div>
        </x-ui.filters>
    </div>

    <div class="mt-4">
        <x-ui.card :flush="$programas->isNotEmpty()">
            @if ($programas->isEmpty())
                <x-ui.empty-state icon="squares" title="No hay programas"
                    message="Registra el primer programa para comenzar a configurar la oferta académica.">
                    <x-slot:action>
                        <x-ui.button wire:click="crear" icon="plus">Nuevo programa</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Catálogo de programas</caption>
                        <thead>
                            <tr>
                                <th scope="col">Clave</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Duración (periodos)</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($programas as $programa)
                                <tr wire:key="programa-{{ $programa->id }}">
                                    <td class="font-medium text-brand-primary">{{ $programa->clave }}</td>
                                    <td class="font-medium text-brand-text">{{ $programa->nombre }}</td>
                                    <td class="text-brand-muted">{{ $programa->duracion_periodos ?? '—' }}</td>
                                    <td>
                                        <x-ui.badge :tone="$programa->activo ? 'success' : 'neutral'">
                                            {{ $programa->activo ? 'Activo' : 'Inactivo' }}
                                        </x-ui.badge>
                                    </td>
                                    <td>
                                        <div class="ui-row-actions">
                                            <x-ui.icon-button label="Editar {{ $programa->nombre }}" icon="cog"
                                                wire:click="editar({{ $programa->id }})" />
                                            <x-ui.icon-button
                                                :label="$programa->activo ? 'Desactivar' : 'Activar'"
                                                :icon="$programa->activo ? 'no-symbol' : 'check-circle'"
                                                :variant="$programa->activo ? 'secondary' : 'primary'"
                                                wire:click="alternarActivo({{ $programa->id }})" />
                                            <x-ui.icon-button label="Eliminar {{ $programa->nombre }}" icon="x-mark"
                                                variant="danger" wire:click="eliminar({{ $programa->id }})"
                                                wire:confirm="¿Eliminar este programa? (borrado lógico)" />
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

    <x-ui.modal id="modal-programa"
        :title="$registroId ? 'Editar programa' : 'Nuevo programa'"
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
                <x-ui.field label="Clave" for="programa-clave" required
                    hint="Solo letras, números, guión y guion bajo." :error="$errors->first('clave')">
                    <x-ui.input id="programa-clave" wire:model="clave" maxlength="20" placeholder="Ej. DER" />
                </x-ui.field>

                <x-ui.field label="Nombre" for="programa-nombre" required :error="$errors->first('nombre')">
                    <x-ui.input id="programa-nombre" wire:model="nombre" maxlength="150"
                        placeholder="Licenciatura en…" />
                </x-ui.field>

                <x-ui.field label="Duración (periodos académicos)" for="programa-duracion"
                    hint="Opcional: capturar cuando exista confirmación institucional."
                    :error="$errors->first('duracion_periodos')">
                    <x-ui.input id="programa-duracion" type="number" min="1" max="65535"
                        wire:model="duracion_periodos" placeholder="Ej. 8" />
                </x-ui.field>

                <div class="flex items-end pb-1">
                    <label class="inline-flex cursor-pointer items-center gap-2.5">
                        <input type="checkbox" id="programa-activo" wire:model="activo"
                            class="h-4 w-4 rounded border-brand-line accent-brand-primary" />
                        <span class="text-sm font-medium text-brand-text">Activo</span>
                    </label>
                </div>
            </div>

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
