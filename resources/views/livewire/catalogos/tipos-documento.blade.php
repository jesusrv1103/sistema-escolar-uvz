<div>
    <x-ui.page-header title="Tipos de documento"
        description="Solo documentos definidos en la arquitectura aprobada.">
        <x-slot:actions>
            <x-ui.button wire:click="crear" icon="plus">Nuevo tipo</x-ui.button>
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
                        placeholder="Nombre o clave…" aria-label="Buscar tipos de documento" />
                </x-ui.field>
            </div>
        </x-ui.filters>
    </div>

    <div class="mt-4">
        <x-ui.card :flush="$tipos->isNotEmpty()">
            @if ($tipos->isEmpty())
                <x-ui.empty-state icon="document-text" title="No hay tipos de documento"
                    message="Define el primer tipo para poder recibir expedientes de los alumnos.">
                    <x-slot:action>
                        <x-ui.button wire:click="crear" icon="plus">Nuevo tipo</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Catálogo de tipos de documento</caption>
                        <thead>
                            <tr>
                                <th scope="col">Clave</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Obligatorio</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tipos as $tipo)
                                <tr wire:key="tipo-{{ $tipo->id }}">
                                    <td class="font-medium text-brand-primary">{{ $tipo->clave }}</td>
                                    <td class="font-medium text-brand-text">{{ $tipo->nombre }}</td>
                                    <td>
                                        <x-ui.badge :tone="$tipo->obligatorio ? 'warning' : 'neutral'">
                                            {{ $tipo->obligatorio ? 'Obligatorio' : 'Opcional' }}
                                        </x-ui.badge>
                                    </td>
                                    <td>
                                        <x-ui.badge :tone="$tipo->activo ? 'success' : 'neutral'">
                                            {{ $tipo->activo ? 'Activo' : 'Inactivo' }}
                                        </x-ui.badge>
                                    </td>
                                    <td>
                                        <div class="ui-row-actions">
                                            <x-ui.icon-button label="Editar {{ $tipo->nombre }}" icon="pencil"
                                                wire:click="editar({{ $tipo->id }})" />
                                            <x-ui.icon-button
                                                :label="$tipo->activo ? 'Desactivar' : 'Activar'"
                                                :icon="$tipo->activo ? 'no-symbol' : 'check-circle'"
                                                :variant="$tipo->activo ? 'secondary' : 'primary'"
                                                wire:click="alternarActivo({{ $tipo->id }})" />
                                            <x-ui.icon-button label="Eliminar {{ $tipo->nombre }}" icon="x-mark"
                                                variant="danger" wire:click="eliminar({{ $tipo->id }})"
                                                wire:confirm="¿Eliminar este tipo? (borrado lógico)" />
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

    <x-ui.modal id="modal-tipo-documento"
        :title="$registroId ? 'Editar tipo de documento' : 'Nuevo tipo de documento'"
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
                <x-ui.field label="Clave" for="tipo-clave" required hint="Se usa internamente; no se muestra al usuario."
                    :error="$errors->first('clave')">
                    <x-ui.input id="tipo-clave" wire:model="clave" maxlength="30" placeholder="Ej. ACTA_NAC" />
                </x-ui.field>

                <x-ui.field label="Nombre" for="tipo-nombre" required :error="$errors->first('nombre')">
                    <x-ui.input id="tipo-nombre" wire:model="nombre" maxlength="120"
                        placeholder="Acta de nacimiento" />
                </x-ui.field>
            </div>

            <div class="flex flex-wrap items-center gap-6">
                <label class="inline-flex cursor-pointer items-center gap-2.5">
                    <input type="checkbox" id="tipo-obligatorio" wire:model="obligatorio"
                        class="h-4 w-4 rounded border-brand-line accent-brand-primary" />
                    <span class="text-sm font-medium text-brand-text">Obligatorio</span>
                </label>
                <label class="inline-flex cursor-pointer items-center gap-2.5">
                    <input type="checkbox" id="tipo-activo" wire:model="activo"
                        class="h-4 w-4 rounded border-brand-line accent-brand-primary" />
                    <span class="text-sm font-medium text-brand-text">Activo</span>
                </label>
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
