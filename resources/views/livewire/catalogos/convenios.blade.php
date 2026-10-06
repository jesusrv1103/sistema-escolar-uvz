<div>
    <x-ui.page-header title="Convenios"
        description="Catálogo de descuentos · la aprobación vive en cada inscripción, no aquí.">
        <x-slot:actions>
            <x-ui.button wire:click="crear" icon="plus">Nuevo convenio</x-ui.button>
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
                        placeholder="Nombre o clave…" aria-label="Buscar convenios" />
                </x-ui.field>
            </div>
        </x-ui.filters>
    </div>

    <div class="mt-4">
        <x-ui.card :flush="$convenios->isNotEmpty()">
            @if ($convenios->isEmpty())
                <x-ui.empty-state icon="percent" title="No hay convenios"
                    message="Registra el primer convenio para poder ofrecer descuentos en las inscripciones.">
                    <x-slot:action>
                        <x-ui.button wire:click="crear" icon="plus">Nuevo convenio</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Catálogo de convenios y descuentos</caption>
                        <thead>
                            <tr>
                                <th scope="col">Clave</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Valor</th>
                                <th scope="col">Vigencia</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($convenios as $convenio)
                                <tr wire:key="convenio-{{ $convenio->id }}">
                                    <td class="font-medium text-brand-primary">{{ $convenio->clave }}</td>
                                    <td>
                                        <span class="block font-medium text-brand-text">{{ $convenio->nombre }}</span>
                                        @if ($convenio->notas)
                                            <span class="block max-w-xs truncate text-xs text-brand-muted">
                                                {{ $convenio->notas }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <x-ui.badge tone="info">{{ $convenio->tipo->label() }}</x-ui.badge>
                                    </td>
                                    <td class="font-medium text-brand-text">
                                        {{ $convenio->tipo === \App\Enums\TipoConvenio::PORCENTAJE
                                            ? $convenio->valor . ' %'
                                            : '$' . $convenio->valor }}
                                    </td>
                                    <td class="text-xs text-brand-muted">
                                        {{ $convenio->fecha_inicio?->format('d/m/Y') ?? '—' }}
                                        → {{ $convenio->fecha_fin?->format('d/m/Y') ?? '—' }}
                                    </td>
                                    <td>
                                        <x-ui.badge :tone="$convenio->activo ? 'success' : 'neutral'">
                                            {{ $convenio->activo ? 'Activo' : 'Inactivo' }}
                                        </x-ui.badge>
                                    </td>
                                    <td>
                                        <div class="ui-row-actions">
                                            <x-ui.icon-button label="Editar {{ $convenio->nombre }}" icon="pencil"
                                                wire:click="editar({{ $convenio->id }})" />
                                            <x-ui.icon-button
                                                :label="$convenio->activo ? 'Desactivar' : 'Activar'"
                                                :icon="$convenio->activo ? 'no-symbol' : 'check-circle'"
                                                :variant="$convenio->activo ? 'secondary' : 'primary'"
                                                wire:click="alternarActivo({{ $convenio->id }})" />
                                            <x-ui.icon-button label="Eliminar {{ $convenio->nombre }}" icon="x-mark"
                                                variant="danger" wire:click="eliminar({{ $convenio->id }})"
                                                wire:confirm="¿Eliminar este convenio? (borrado lógico)" />
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

    <x-ui.modal id="modal-convenio"
        :title="$registroId ? 'Editar convenio' : 'Nuevo convenio'"
        :open="$formulario" close="cancelar">
        <form novalidate wire:submit="guardar" class="space-y-4">
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
                <x-ui.field label="Clave" for="convenio-clave" required :error="$errors->first('clave')">
                    <x-ui.input id="convenio-clave" wire:model="clave" maxlength="20" placeholder="Ej. BECA10" />
                </x-ui.field>

                <x-ui.field label="Nombre" for="convenio-nombre" required :error="$errors->first('nombre')">
                    <x-ui.input id="convenio-nombre" wire:model="nombre" maxlength="150"
                        placeholder="Nombre del convenio" />
                </x-ui.field>

                <x-ui.field label="Tipo" for="convenio-tipo" required :error="$errors->first('tipo')">
                    <x-ui.select id="convenio-tipo" wire:model.live="tipo">
                        <option value="PORCENTAJE">Porcentaje (%)</option>
                        <option value="MONTO">Monto (MXN)</option>
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field :label="$tipo === 'MONTO' ? 'Monto (MXN)' : 'Porcentaje (%)'" for="convenio-valor"
                    required :error="$errors->first('valor')">
                    <x-ui.input id="convenio-valor" type="number" step="0.01" min="0" wire:model="valor"
                        placeholder="Ej. 10" />
                </x-ui.field>

                <x-ui.field label="Vigente desde" for="convenio-desde" :error="$errors->first('fecha_inicio')">
                    <x-ui.input id="convenio-desde" type="date" wire:model="fecha_inicio" />
                </x-ui.field>

                <x-ui.field label="Vigente hasta" for="convenio-hasta" :error="$errors->first('fecha_fin')">
                    <x-ui.input id="convenio-hasta" type="date" wire:model="fecha_fin" />
                </x-ui.field>

                <div class="sm:col-span-2">
                    <x-ui.field label="Notas" for="convenio-notas" :error="$errors->first('notas')">
                        <x-ui.textarea id="convenio-notas" wire:model="notas" :rows="2" maxlength="2000" />
                    </x-ui.field>
                </div>
            </div>

            <label class="inline-flex cursor-pointer items-center gap-2.5">
                <input type="checkbox" id="convenio-activo" wire:model="activo"
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
