<div>
    <x-ui.page-header title="Servicio Social"
        description="Actividad, dependencia, fechas y avance del Servicio Social de cada alumno. El avance académico sólo se consulta.">
        <x-slot:actions>
            @can('servicio_social.crear')
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
                        placeholder="Alumno, número de control…" aria-label="Buscar servicio social" />
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
                <x-ui.empty-state icon="academic-cap" title="Sin registros de Servicio Social"
                    message="Ajusta los filtros o registra el primer proceso de Servicio Social.">
                    <x-slot:action>
                        @can('servicio_social.crear')
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
                                <th scope="col">Actividad</th>
                                <th scope="col">Dependencia</th>
                                <th scope="col">Periodo</th>
                                <th scope="col" class="text-right">Avance</th>
                                <th scope="col" class="text-right">Avance académico</th>
                                <th scope="col">Estatus</th>
                                <th scope="col">Validación</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($registros as $s)
                                <tr wire:key="servicio-social-{{ $s->id }}" id="servicio-social-{{ $s->id }}">
                                    <td class="font-medium text-brand-text">
                                        {{ $s->alumno?->nombre_completo }}
                                        <span class="block text-xs text-brand-muted">
                                            {{ $s->alumno?->numero_control ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="max-w-[220px]">{{ \Illuminate\Support\Str::limit($s->actividad, 50) }}</td>
                                    <td class="max-w-[200px] text-brand-muted">{{ \Illuminate\Support\Str::limit($s->dependencia, 45) }}</td>
                                    <td class="whitespace-nowrap text-brand-muted">
                                        {{ $s->fecha_inicio?->format('d/m/Y') ?? '—' }}
                                        →
                                        {{ $s->fecha_fin?->format('d/m/Y') ?? '—' }}
                                    </td>
                                    <td class="text-right tabular-nums">{{ $s->avance }}%</td>
                                    <td class="text-right tabular-nums text-brand-muted">
                                        {{ number_format($avanceAlumnos[$s->alumno_id] ?? 0, 1) }}%
                                    </td>
                                    <td>
                                        <x-ui.badge :tone="$s->esValidado() ? 'success' : 'warning'">
                                            {{ $s->estatus->label() }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="whitespace-nowrap text-brand-muted">
                                        {{ $s->validadoPor?->name ?? '—' }}
                                        {{ $s->validado_at?->format('d/m/Y') }}
                                    </td>
                                    <td class="text-right">
                                        <div class="ui-row-actions" role="group"
                                            aria-label="Acciones del Servicio Social de {{ $s->alumno?->nombre_completo }}">
                                            @if (! $s->esValidado())
                                                @can('servicio_social.actualizar', $s)
                                                    <x-ui.icon-button label="Editar Servicio Social" icon="pencil"
                                                        wire:click="abrirModalEditar({{ $s->id }})" />
                                                @endcan
                                                @can('servicio_social.validar', $s)
                                                    <x-ui.icon-button label="Validar Servicio Social" icon="check"
                                                        wire:click="validarRegistro({{ $s->id }})"
                                                        wire:confirm="¿Validar el Servicio Social de {{ $s->alumno?->nombre_completo }}? Después el registro queda inmutable." />
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
        <x-ui.modal id="modal-servicio-social" size="lg"
            :title="($servicio ? 'Editar' : 'Registrar') . ' Servicio Social'"
            :open="true" close="$set('modalAbierto', false)">
            <form novalidate id="form-servicio-social" wire:submit="guardar">
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

                    @if ($servicio)
                        {{-- En edición el alumno no cambia: se muestra sólo lectura. --}}
                        <x-ui.field label="Alumno" for="ss-alumno" required>
                            <x-ui.input id="ss-alumno" type="text" disabled
                                :value="$servicio->alumno?->nombre_completo" />
                        </x-ui.field>
                    @else
                        <x-ui.field label="Alumno" for="ss-alumno" required :error="$errors->first('alumno')">
                            <x-ui.select id="ss-alumno" wire:change="seleccionarAlumno($event.target.value)">
                                <option value="">Seleccionar alumno</option>
                                @foreach (\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                                    <option value="{{ $a->id }}" @selected($alumno?->getKey() === $a->id)>
                                        {{ $a->nombre_completo }} ({{ $a->numero_control ?? $a->curp }})
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    @endif

                    <x-ui.field label="Avance académico (sólo lectura)" for="ss-avance-academico">
                        <x-ui.input id="ss-avance-academico" type="text" disabled
                            :value="$avanceSeleccionado !== null ? number_format($avanceSeleccionado, 1) . '%' : 'Selecciona un alumno'" />
                    </x-ui.field>

                    <div class="md:col-span-2">
                        <x-ui.field label="Actividad a realizar" for="ss-actividad" required
                            :error="$errors->first('actividad')">
                            <x-ui.input id="ss-actividad" type="text" wire:model="actividad" maxlength="500"
                                placeholder="Apoyo en archivo, atención a usuarios, gestión documental…" />
                        </x-ui.field>
                    </div>

                    <div class="md:col-span-2">
                        <x-ui.field label="Dependencia" for="ss-dependencia" required
                            :error="$errors->first('dependencia')">
                            <x-ui.input id="ss-dependencia" type="text" wire:model="dependencia" maxlength="500"
                                placeholder="Unidad o área donde se realiza el Servicio Social" />
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Fecha de inicio" for="ss-inicio" :error="$errors->first('fecha_inicio')">
                        <x-ui.input id="ss-inicio" type="date" wire:model="fecha_inicio" />
                    </x-ui.field>

                    <x-ui.field label="Fecha de término" for="ss-fin" :error="$errors->first('fecha_fin')">
                        <x-ui.input id="ss-fin" type="date" wire:model="fecha_fin" />
                    </x-ui.field>

                    @if ($servicio)
                        <div class="md:col-span-2">
                            <x-ui.field label="Avance del proceso (%)" for="ss-avance" required
                                hint="Se almacena pero no valida el proceso automáticamente."
                                :error="$errors->first('avance')">
                                <x-ui.input id="ss-avance" type="number" wire:model="avance" min="0" max="100" />
                            </x-ui.field>
                        </div>
                    @endif
                </div>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="$set('modalAbierto', false)">Cancelar</x-ui.button>
                    <x-ui.button type="submit" form="form-servicio-social" icon="check"
                        wire:loading.attr="disabled" wire:target="guardar">
                        <span wire:loading.remove wire:target="guardar">
                            {{ $servicio ? 'Actualizar' : 'Registrar' }}
                        </span>
                        <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                    </x-ui.button>
                </x-slot:footer>
            </form>
        </x-ui.modal>
    @endif
</div>
