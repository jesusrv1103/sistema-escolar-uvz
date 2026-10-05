<div>
    {{-- ============================ ENCABEZADO ============================ --}}
    <x-ui.page-header title="Planes de estudio"
        description="Administra planes académicos, versiones y vigencia.">
        <x-slot:actions>
            <x-ui.button wire:click="abrirModalCrear" icon="plus">Nuevo plan</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('success'))
        <div class="mt-4">
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        </div>
    @endif

    {{-- ============================= FILTROS ============================= --}}
    <div class="mt-5">
        <x-ui.filters>
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" wire:click="limpiarFiltros">Limpiar filtros</x-ui.button>
            </x-slot:actions>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field label="Buscar">
                    <x-ui.input type="search" wire:model.debounce.300ms="busqueda"
                        placeholder="Nombre o clave…" aria-label="Buscar planes" />
                </x-ui.field>

                <x-ui.field label="Programa">
                    <x-ui.select wire:model.live="filtroPrograma" aria-label="Filtrar por programa">
                        <option value="">Todos</option>
                        @foreach ($programas as $programa)
                            <option value="{{ $programa->id }}">{{ $programa->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Estado">
                    <x-ui.select wire:model.live="filtroActivo" aria-label="Filtrar por estado">
                        <option value="">Todos</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                    </x-ui.select>
                </x-ui.field>
            </div>
        </x-ui.filters>
    </div>

    {{-- ============================ RESULTADOS =========================== --}}
    <div class="mt-4">
        <x-ui.card>
            @if ($planes->isEmpty())
                <x-ui.empty-state icon="folder" title="No hay planes de estudio"
                    message="Crea el primer plan para comenzar a configurar la estructura académica.">
                    <x-slot:action>
                        <x-ui.button wire:click="abrirModalCrear" icon="plus">Crear plan</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Listado de planes de estudio</caption>
                        <thead>
                            <tr>
                                <th scope="col">Clave</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Programa</th>
                                <th scope="col">Vigencia / RVOE</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($planes as $plan)
                                <tr wire:key="plan-{{ $plan->id }}">
                                    <td class="font-medium text-brand-text">{{ $plan->clave }}</td>
                                    <td>
                                        <span class="block font-medium text-brand-text">{{ $plan->nombre }}</span>
                                        @if ($plan->fecha_resolucion)
                                            <span class="block text-xs text-brand-muted">
                                                Resolución {{ $plan->fecha_resolucion->format('d/m/Y') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-brand-muted">{{ $plan->programa->nombre ?? '—' }}</td>
                                    <td class="text-brand-muted">{{ $plan->resolucion_rvoe ?? '—' }}</td>
                                    <td>
                                        <x-ui.badge :tone="$plan->activo ? 'success' : 'neutral'">
                                            {{ $plan->activo ? 'Activo' : 'Inactivo' }}
                                        </x-ui.badge>
                                    </td>
                                    <td>
                                        <div class="ui-row-actions">
                                            <x-ui.icon-button label="Editar plan {{ $plan->clave }}" icon="pencil"
                                                wire:click="abrirModalEditar({{ $plan->id }})" />
                                            <x-ui.icon-button label="{{ $plan->activo ? 'Desactivar' : 'Activar' }} plan {{ $plan->clave }}"
                                                :icon="$plan->activo ? 'no-symbol' : 'check-circle'"
                                                :variant="$plan->activo ? 'secondary' : 'primary'"
                                                wire:click="alternarActivo({{ $plan->id }})" />
                                            <x-ui.icon-button label="Eliminar plan {{ $plan->clave }}" icon="x-mark"
                                                variant="danger"
                                                wire:click="eliminar({{ $plan->id }})"
                                                wire:confirm="¿Eliminar el plan {{ $plan->clave }}? Esta acción no se puede deshacer." />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-brand-line px-5 py-3">
                    {{ $planes->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>

    {{-- ======================= MODAL CREAR / EDITAR ======================= --}}
    <x-ui.modal id="modal-plan"
        :title="$planEnEdicion ? 'Editar plan de estudio' : 'Nuevo plan de estudio'"
        :open="$modalAbierto" close="cerrarModal" size="lg">
        <form wire:submit="guardar" class="space-y-5">
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
                <x-ui.field label="Clave" for="plan-clave" required hint="Solo mayúsculas, números, guión y guion bajo."
                    :error="$errors->first('clave')">
                    <x-ui.input id="plan-clave" wire:model="clave" maxlength="20" placeholder="Ej. DER-2024"
                        :disabled="$planEnEdicion !== null" />
                </x-ui.field>

                <x-ui.field label="Programa" for="plan-programa" required :error="$errors->first('programaId')">
                    <x-ui.select id="plan-programa" wire:model="programaId">
                        <option value="">Seleccione un programa</option>
                        @foreach ($programas as $programa)
                            <option value="{{ $programa->id }}">{{ $programa->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <div class="sm:col-span-2">
                    <x-ui.field label="Nombre" for="plan-nombre" required :error="$errors->first('nombre')">
                        <x-ui.input id="plan-nombre" wire:model="nombre" maxlength="150"
                            placeholder="Nombre completo del plan" />
                    </x-ui.field>
                </div>

                <x-ui.field label="Resolución / RVOE" for="plan-rvoe" :error="$errors->first('resolucionRvoe')">
                    <x-ui.input id="plan-rvoe" wire:model="resolucionRvoe" maxlength="100"
                        placeholder="Ej. RVOE 20240123" />
                </x-ui.field>

                <x-ui.field label="Fecha de resolución" for="plan-fecha" :error="$errors->first('fechaResolucion')">
                    <x-ui.input id="plan-fecha" type="date" wire:model="fechaResolucion" />
                </x-ui.field>
            </div>

            <label class="inline-flex cursor-pointer items-center gap-2.5">
                <input type="checkbox" id="plan-activo" wire:model="activo"
                    class="h-4 w-4 rounded border-brand-line accent-brand-primary" />
                <span class="text-sm font-medium text-brand-text">Activo</span>
            </label>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-brand-line pt-4">
                <x-ui.button variant="secondary" wire:click="cerrarModal">Cancelar</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">
                        {{ $planEnEdicion ? 'Guardar cambios' : 'Guardar plan' }}
                    </span>
                    <span wire:loading wire:target="guardar">
                        <x-ui.loading label="Guardando…" />
                    </span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
