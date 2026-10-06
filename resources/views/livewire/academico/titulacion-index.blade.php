<div>
    <x-ui.page-header title="Titulación"
        description="Expediente interno de titulación por alumno egresado. Modalidad y folio interno son captura libre: sin catálogo ni folio automático.">
        <x-slot:actions>
            @can('titulacion.crear')
                <x-ui.button icon="plus" wire:click="abrirModalCrear">Nuevo expediente</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ------------------------- Filtros compactos ------------------------- --}}
    <div class="mt-5">
        <x-ui.filters>
            <div class="grid gap-3 md:grid-cols-3">
                <x-ui.field label="Buscar">
                    <x-ui.input type="text" wire:model.live.debounce.300ms="busqueda"
                        placeholder="Alumno, número de control…" aria-label="Buscar titulaciones" />
                </x-ui.field>

                <x-ui.field label="Estatus">
                    <x-ui.select wire:model.live="filtroEstatus" aria-label="Filtrar por estatus">
                        <option value="">Todos</option>
                        @foreach ($estatuses as $e)
                            <option value="{{ $e->value }}">{{ $e->label() }}</option>
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

    {{-- ------------------------------ Listado ------------------------------ --}}
    <div class="mt-4">
        <x-ui.card :flush="count($titulaciones) > 0">
            @if (session('success'))
                <div class="px-5 pt-4"><x-ui.alert tone="success">{{ session('success') }}</x-ui.alert></div>
            @endif

            @if (count($titulaciones) === 0)
                <x-ui.empty-state icon="academic-cap" title="Sin expedientes de titulación"
                    message="Crea un expediente para un alumno que ya tenga egreso registrado.">
                    <x-slot:action>
                        @can('titulacion.crear')
                            <x-ui.button icon="plus" wire:click="abrirModalCrear">Nuevo expediente</x-ui.button>
                        @endcan
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th scope="col">Alumno</th>
                                <th scope="col">Egreso</th>
                                <th scope="col">Modalidad</th>
                                <th scope="col">Folio interno</th>
                                <th scope="col">Estatus</th>
                                <th scope="col">Registro</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($titulaciones as $t)
                                <tr wire:key="titulacion-{{ $t->id }}" id="titulacion-{{ $t->id }}">
                                    <td class="font-medium text-brand-text">
                                        {{ $t->alumno?->nombre_completo }}
                                        <span class="block text-xs text-brand-muted">
                                            {{ $t->alumno?->numero_control ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap text-brand-muted">
                                        {{ $t->egreso?->registrado_at?->format('d/m/Y') ?? '—' }}
                                        <span class="block text-xs">
                                            {{ $t->egreso?->planVersion?->plan?->clave ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="max-w-[200px]">{{ $t->modalidad ?? '—' }}</td>
                                    <td class="whitespace-nowrap">{{ $t->folio_interno ?? '—' }}</td>
                                    <td>
                                        <x-ui.badge :tone="$t->esTitulado() ? 'success' : 'warning'">
                                            {{ $t->estatus->label() }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="whitespace-nowrap text-brand-muted">
                                        {{ $t->registradoPor?->name ?? '—' }}
                                        {{ $t->registrado_at?->format('d/m/Y') }}
                                    </td>
                                    <td class="text-right">
                                        <div class="ui-row-actions" role="group"
                                            aria-label="Acciones de la titulación de {{ $t->alumno?->nombre_completo }}">
                                            @can('titulacion.actualizar', $t)
                                                <x-ui.icon-button label="Editar expediente" icon="pencil"
                                                    wire:click="abrirModalEditar({{ $t->id }})" />
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-brand-line px-5 py-3">{{ $titulaciones->links() }}</div>
            @endif
        </x-ui.card>
    </div>

    {{-- --------------------- Modal: crear / editar expediente --------------------- --}}
    @if ($modalAbierto)
        <x-ui.modal id="modal-titulacion" size="lg"
            :title="($titulacion ? 'Editar' : 'Registrar') . ' titulación'"
            :open="true" close="$set('modalAbierto', false)">
            <form novalidate id="form-titulacion" wire:submit="guardar">
                <div class="grid gap-3 md:grid-cols-2">
                    @if ($errors->any())
                        <div class="md:col-span-2">
                            <x-ui.alert tone="danger">
                                <ul class="list-inside list-disc space-y-0.5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </x-ui.alert>
                        </div>
                    @endif

                    @if ($titulacion)
                        {{-- En edición el alumno no cambia: se muestra sólo lectura. --}}
                        <x-ui.field label="Alumno" for="tt-alumno" required>
                            <x-ui.input id="tt-alumno" type="text" disabled
                                :value="$titulacion->alumno?->nombre_completo" />
                        </x-ui.field>
                    @else
                        <x-ui.field label="Alumno egresado" for="tt-alumno" required
                            hint="Sólo aparecen alumnos con egreso registrado."
                            :error="$errors->first('alumno')">
                            <x-ui.select id="tt-alumno" wire:change="seleccionarAlumno($event.target.value)">
                                <option value="">Seleccionar alumno</option>
                                @foreach ($alumnosEgresados as $a)
                                    <option value="{{ $a->id }}" @selected($alumno?->getKey() === $a->id)>
                                        {{ $a->nombre_completo }} ({{ $a->numero_control ?? $a->curp }})
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    @endif

                    <x-ui.field label="Estatus" for="tt-estatus" required :error="$errors->first('estatus')">
                        <x-ui.select id="tt-estatus" wire:model="estatus">
                            @foreach ($estatuses as $e)
                                <option value="{{ $e->value }}">{{ $e->label() }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field label="Modalidad" for="tt-modalidad"
                        hint="Texto libre: no hay catálogo oficial en el sistema."
                        :error="$errors->first('modalidad')">
                        <x-ui.input id="tt-modalidad" type="text" wire:model="modalidad" maxlength="200" />
                    </x-ui.field>

                    <x-ui.field label="Folio interno" for="tt-folio"
                        hint="Captura manual opcional; no se genera automáticamente."
                        :error="$errors->first('folio_interno')">
                        <x-ui.input id="tt-folio" type="text" wire:model="folio_interno" maxlength="100" />
                    </x-ui.field>
                </div>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="$set('modalAbierto', false)">Cancelar</x-ui.button>
                    <x-ui.button type="submit" form="form-titulacion" icon="check"
                        wire:loading.attr="disabled" wire:target="guardar">
                        <span wire:loading.remove wire:target="guardar">
                            {{ $titulacion ? 'Actualizar' : 'Registrar' }}
                        </span>
                        <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                    </x-ui.button>
                </x-slot:footer>
            </form>
        </x-ui.modal>
    @endif
</div>
