<div>
    <x-ui.page-header title="Prácticas Profesionales"
        description="Empresa, periodo, asesor y validación de las prácticas profesionales. La calificación se captura como texto.">
        <x-slot:actions>
            @can('practica.crear')
                <x-ui.button icon="plus" wire:click="abrirModalCrear">Nuevo registro</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ------------------------- Filtros compactos ------------------------- --}}
    <div class="mt-5">
        <x-ui.filters>
            <div class="grid gap-3 md:grid-cols-3">
                <x-ui.field label="Buscar">
                    <x-ui.input type="text" wire:model.live.debounce.300ms="busqueda"
                        placeholder="Alumno, número de control…" aria-label="Buscar prácticas profesionales" />
                </x-ui.field>

                <x-ui.field label="Estatus">
                    <x-ui.select wire:model.live="filtroEstatus" aria-label="Filtrar por estatus">
                        <option value="">Todos</option>
                        @foreach ($estatus as $e)
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
        <x-ui.card :flush="count($registros) > 0">
            @if (session('success'))
                <div class="px-5 pt-4"><x-ui.alert tone="success">{{ session('success') }}</x-ui.alert></div>
            @endif

            @if ($errors->has('validar'))
                <div class="px-5 pt-4"><x-ui.alert tone="danger">{{ $errors->first('validar') }}</x-ui.alert></div>
            @endif

            @if (count($registros) === 0)
                <x-ui.empty-state icon="clipboard" title="Sin registros de Prácticas"
                    message="Ajusta los filtros o registra el primer proceso de Prácticas Profesionales.">
                    <x-slot:action>
                        @can('practica.crear')
                            <x-ui.button icon="plus" wire:click="abrirModalCrear">Nuevo registro</x-ui.button>
                        @endcan
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th scope="col">Alumno</th>
                                <th scope="col">Empresa</th>
                                <th scope="col">Periodo</th>
                                <th scope="col">Asesor</th>
                                <th scope="col">Calificación</th>
                                <th scope="col" class="text-right">Avance académico</th>
                                <th scope="col">Estatus</th>
                                <th scope="col">Validación</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($registros as $p)
                                <tr wire:key="practica-{{ $p->id }}" id="practica-{{ $p->id }}">
                                    <td class="font-medium text-brand-text">
                                        {{ $p->alumno?->nombre_completo }}
                                        <span class="block text-xs text-brand-muted">
                                            {{ $p->alumno?->numero_control ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="max-w-[220px]">{{ \Illuminate\Support\Str::limit($p->empresa, 50) }}</td>
                                    <td class="whitespace-nowrap text-brand-muted">
                                        {{ $p->fecha_inicio?->format('d/m/Y') ?? '—' }}
                                        →
                                        {{ $p->fecha_fin?->format('d/m/Y') ?? '—' }}
                                    </td>
                                    <td class="text-brand-muted">{{ $p->asesor ?? '—' }}</td>
                                    <td class="text-brand-muted">{{ $p->calificacion ?? '—' }}</td>
                                    <td class="text-right tabular-nums text-brand-muted">
                                        {{ number_format($avanceAlumnos[$p->alumno_id] ?? 0, 1) }}%
                                    </td>
                                    <td>
                                        <x-ui.badge :tone="$p->esValidado() ? 'success' : 'warning'">
                                            {{ $p->estatus->label() }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="whitespace-nowrap text-brand-muted">
                                        {{ $p->validadoPor?->name ?? '—' }}
                                        {{ $p->validado_at?->format('d/m/Y') }}
                                    </td>
                                    <td class="text-right">
                                        <div class="ui-row-actions" role="group"
                                            aria-label="Acciones de las prácticas de {{ $p->alumno?->nombre_completo }}">
                                            @if (! $p->esValidado())
                                                @can('practica.actualizar', $p)
                                                    <x-ui.icon-button label="Editar prácticas" icon="pencil"
                                                        wire:click="abrirModalEditar({{ $p->id }})" />
                                                @endcan
                                                @can('practica.validar', $p)
                                                    <x-ui.icon-button label="Validar prácticas" icon="check"
                                                        wire:click="validarRegistro({{ $p->id }})"
                                                        wire:confirm="¿Validar las prácticas de {{ $p->alumno?->nombre_completo }}? Después el registro queda inmutable." />
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-brand-line px-5 py-3">{{ $registros->links() }}</div>
            @endif
        </x-ui.card>
    </div>

    {{-- --------------------- Modal: crear / editar registro --------------------- --}}
    @if ($modalAbierto)
        <x-ui.modal id="modal-practicas" size="lg"
            :title="($practica ? 'Editar' : 'Registrar') . ' Prácticas Profesionales'"
            :open="true" close="$set('modalAbierto', false)">
            <form novalidate id="form-practicas" wire:submit="guardar">
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

                    @if ($practica)
                        {{-- En edición el alumno no cambia: se muestra sólo lectura. --}}
                        <x-ui.field label="Alumno" for="pp-alumno" required>
                            <x-ui.input id="pp-alumno" type="text" disabled
                                :value="$practica->alumno?->nombre_completo" />
                        </x-ui.field>
                    @else
                        <x-ui.field label="Alumno" for="pp-alumno" required :error="$errors->first('alumno')">
                            <x-ui.select id="pp-alumno" wire:change="seleccionarAlumno($event.target.value)">
                                <option value="">Seleccionar alumno</option>
                                @foreach (\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                                    <option value="{{ $a->id }}" @selected($alumno?->getKey() === $a->id)>
                                        {{ $a->nombre_completo }} ({{ $a->numero_control ?? $a->curp }})
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    @endif

                    <x-ui.field label="Avance académico (sólo lectura)" for="pp-avance-academico">
                        <x-ui.input id="pp-avance-academico" type="text" disabled
                            :value="$avanceSeleccionado !== null ? number_format($avanceSeleccionado, 1) . '%' : 'Selecciona un alumno'" />
                    </x-ui.field>

                    <div class="md:col-span-2">
                        <x-ui.field label="Empresa / institución" for="pp-empresa" required
                            :error="$errors->first('empresa')">
                            <x-ui.input id="pp-empresa" type="text" wire:model="empresa" maxlength="500"
                                placeholder="Nombre de la empresa donde realiza las prácticas" />
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Fecha de inicio" for="pp-inicio" :error="$errors->first('fecha_inicio')">
                        <x-ui.input id="pp-inicio" type="date" wire:model="fecha_inicio" />
                    </x-ui.field>

                    <x-ui.field label="Fecha de término" for="pp-fin" :error="$errors->first('fecha_fin')">
                        <x-ui.input id="pp-fin" type="date" wire:model="fecha_fin" />
                    </x-ui.field>

                    <x-ui.field label="Asesor" for="pp-asesor" :error="$errors->first('asesor')">
                        <x-ui.input id="pp-asesor" type="text" wire:model="asesor" maxlength="500" />
                    </x-ui.field>

                    <x-ui.field label="Calificación (texto)" for="pp-calificacion"
                        hint="Sin escala predefinida: se captura tal como la documente la institución."
                        :error="$errors->first('calificacion')">
                        <x-ui.input id="pp-calificacion" type="text" wire:model="calificacion" maxlength="100" />
                    </x-ui.field>
                </div>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="$set('modalAbierto', false)">Cancelar</x-ui.button>
                    <x-ui.button type="submit" form="form-practicas" icon="check"
                        wire:loading.attr="disabled" wire:target="guardar">
                        <span wire:loading.remove wire:target="guardar">
                            {{ $practica ? 'Actualizar' : 'Registrar' }}
                        </span>
                        <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                    </x-ui.button>
                </x-slot:footer>
            </form>
        </x-ui.modal>
    @endif
</div>
