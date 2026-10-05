<div>
    <x-ui.page-header title="Aulas"
        description="Espacios físicos disponibles para grupos, horarios y carga académica.">
        <x-slot:actions>
            <x-ui.button wire:click="crear" icon="plus">Nueva aula</x-ui.button>
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
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field label="Buscar" class="lg:col-span-2">
                    <x-ui.input type="search" wire:model.debounce.300ms="busqueda"
                        placeholder="Nombre, clave o edificio…" aria-label="Buscar aulas" />
                </x-ui.field>
                <x-ui.field label="Estado">
                    <x-ui.select wire:model.live="filtroActivo" aria-label="Filtrar por estado">
                        <option value="">Todas</option>
                        <option value="1">Activas</option>
                        <option value="0">Inactivas</option>
                    </x-ui.select>
                </x-ui.field>
            </div>
        </x-ui.filters>
    </div>

    <div class="mt-4">
        <x-ui.card :flush="$aulas->isNotEmpty()">
            @if ($aulas->isEmpty())
                <x-ui.empty-state icon="squares" title="No hay aulas"
                    message="Registra el primer aula para poder asignar grupos y horarios.">
                    <x-slot:action>
                        <x-ui.button wire:click="crear" icon="plus">Nueva aula</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Catálogo de aulas</caption>
                        <thead>
                            <tr>
                                <th scope="col">Clave</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Edificio</th>
                                <th scope="col">Capacidad</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($aulas as $a)
                                <tr wire:key="aula-{{ $a->id }}">
                                    <td class="font-medium text-brand-primary">{{ $a->clave }}</td>
                                    <td class="font-medium text-brand-text">{{ $a->nombre }}</td>
                                    <td class="text-brand-muted">{{ $a->edificio }}</td>
                                    <td class="text-brand-muted">{{ $a->capacidad }}</td>
                                    <td><x-ui.badge tone="info">{{ $a->tipo?->label() ?? $a->tipo }}</x-ui.badge></td>
                                    <td>
                                        <x-ui.badge :tone="$a->activo ? 'success' : 'neutral'">
                                            {{ $a->activo ? 'Activa' : 'Inactiva' }}
                                        </x-ui.badge>
                                    </td>
                                    <td>
                                        <div class="ui-row-actions">
                                            <x-ui.icon-button label="Editar {{ $a->nombre }}" icon="cog"
                                                wire:click="editar({{ $a->id }})" />
                                            <x-ui.icon-button
                                                :label="$a->activo ? 'Desactivar' : 'Activar'"
                                                :icon="$a->activo ? 'no-symbol' : 'check-circle'"
                                                :variant="$a->activo ? 'secondary' : 'primary'"
                                                wire:click="alternarActivo({{ $a->id }})" />
                                            <x-ui.icon-button label="Eliminar {{ $a->nombre }}" icon="x-mark"
                                                variant="danger" wire:click="eliminar({{ $a->id }})"
                                                wire:confirm="¿Eliminar el aula {{ $a->nombre }}? (borrado lógico)" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-brand-line px-5 py-3">
                    {{ $aulas->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>

    <x-ui.modal id="modal-aula"
        :title="$registroId ? 'Editar aula' : 'Nueva aula'"
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
                <x-ui.field label="Clave" for="aula-clave" required
                    hint="Solo mayúsculas, números, guión y guion bajo." :error="$errors->first('clave')">
                    <x-ui.input id="aula-clave" wire:model="clave" maxlength="20" placeholder="Ej. A-101" />
                </x-ui.field>

                <x-ui.field label="Nombre" for="aula-nombre" required :error="$errors->first('nombre')">
                    <x-ui.input id="aula-nombre" wire:model="nombre" maxlength="100" />
                </x-ui.field>

                <x-ui.field label="Edificio" for="aula-edificio" required :error="$errors->first('edificio')">
                    <x-ui.input id="aula-edificio" wire:model="edificio" maxlength="100" />
                </x-ui.field>

                <x-ui.field label="Capacidad" for="aula-capacidad" required :error="$errors->first('capacidad')">
                    <x-ui.input id="aula-capacidad" type="number" min="1" max="500" wire:model="capacidad" />
                </x-ui.field>

                <x-ui.field label="Tipo" for="aula-tipo" required :error="$errors->first('tipo')">
                    <x-ui.select id="aula-tipo" wire:model="tipo">
                        <option value="TEORICA">Teórica</option>
                        <option value="PRACTICA">Práctica</option>
                        <option value="MIXTA">Mixta</option>
                        <option value="LABORATORIO">Laboratorio</option>
                    </x-ui.select>
                </x-ui.field>

                <div class="flex items-end pb-1">
                    <label class="inline-flex cursor-pointer items-center gap-2.5">
                        <input type="checkbox" id="aula-activo" wire:model="activo"
                            class="h-4 w-4 rounded border-brand-line accent-brand-primary" />
                        <span class="text-sm font-medium text-brand-text">Activa</span>
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
