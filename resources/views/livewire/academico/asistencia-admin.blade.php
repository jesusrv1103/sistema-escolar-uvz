<div>
    <x-ui.page-header title="Asistencia"
        description="Selecciona grupo y fecha, carga la lista y marca presentes/ausentes.">
        <x-slot:meta>
            @if ($grupo)
                <x-ui.badge tone="primary">{{ $grupo->clave }}</x-ui.badge>
                <x-ui.badge tone="info">{{ $fecha }}</x-ui.badge>
            @endif
        </x-slot:meta>
    </x-ui.page-header>

    @if (session('success'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('success') }}</x-ui.alert></div>
    @endif

    {{-- --------------------- Selección de grupo y fecha --------------------- --}}
    <div class="mt-5">
        <x-ui.filters>
            <div class="grid gap-3 md:grid-cols-4">
                <x-ui.field label="Grupo" :error="$errors->first('grupo')">
                    {{-- El id viaja a la acción: `grupo` es un modelo tipado y no admite
                         el texto que envía `wire:model` (TypeError "Cannot assign string"). --}}
                    <x-ui.select wire:change="seleccionarGrupo($event.target.value)" aria-label="Grupo">
                        <option value="">Seleccionar grupo</option>
                        @foreach ($grupos as $g)
                            <option value="{{ $g->id }}" @selected($grupo?->getKey() === $g->id)>
                                {{ $g->clave }} - {{ $g->materia->clave }} ({{ $g->periodo->clave }})
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Fecha" :error="$errors->first('fecha')">
                    <x-ui.input type="date" wire:model.live="fecha"
                        max="{{ $grupo?->periodo?->fecha_fin?->format('Y-m-d') ?? now()->format('Y-m-d') }}"
                        min="{{ $grupo?->periodo?->fecha_inicio?->format('Y-m-d') ?? '2000-01-01' }}" />
                </x-ui.field>

                <div class="flex items-end">
                    <x-ui.button block icon="magnifying-glass" wire:click="cargarLista" :disabled="! $grupo"
                        wire:loading.attr="disabled" wire:target="cargarLista">
                        <span wire:loading.remove wire:target="cargarLista">Cargar lista</span>
                        <span wire:loading wire:target="cargarLista"><x-ui.loading label="Cargando…" /></span>
                    </x-ui.button>
                </div>
            </div>
        </x-ui.filters>
    </div>

    @if ($grupo)
        {{-- ----------------------- Lista de asistencia ----------------------- --}}
        <div class="mt-4">
            <x-ui.card :flush="! empty($asistencias)">
                <x-slot:header>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-brand-text">
                                {{ $grupo->clave }} - {{ $grupo->materia->nombre }}
                                <span class="text-xs font-normal text-brand-muted">({{ $grupo->periodo->clave }})</span>
                            </p>
                            <p class="mt-0.5 text-xs text-brand-muted">Fecha: {{ $fecha }}</p>
                        </div>
                        <x-ui.badge tone="info">{{ $fecha }}</x-ui.badge>
                    </div>
                </x-slot:header>

                @error('guardar')
                    <div class="px-5 pt-4"><x-ui.alert tone="danger">{{ $message }}</x-ui.alert></div>
                @enderror

                @if (empty($asistencias))
                    <x-ui.empty-state icon="users" title="Lista sin cargar"
                        message="Haga clic en «Cargar lista» para ver los alumnos del grupo." />
                @else
                    <form novalidate wire:submit="guardar">
                        <div class="overflow-x-auto">
                            <table class="ui-table">
                                <thead>
                                    <tr>
                                        <th scope="col" class="w-[5%]">#</th>
                                        <th scope="col" class="w-[20%]">Número de control</th>
                                        <th scope="col" class="w-[35%]">Alumno</th>
                                        <th scope="col" class="w-[20%]">Asistencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php($i = 1)
                                    @foreach ($asistencias as $item)
                                        <tr wire:key="asistencia-{{ $item['inscripcion']->id }}">
                                            <td class="tabular-nums text-brand-muted">{{ $i++ }}</td>
                                            <td class="font-mono text-xs">
                                                {{ $item['inscripcion']->matriculaPeriodo->alumno->numero_control ?? 'Por asignar' }}
                                            </td>
                                            <td class="font-medium text-brand-text">
                                                {{ $item['inscripcion']->matriculaPeriodo->alumno->nombre_completo }}
                                            </td>
                                            <td>
                                                <label class="inline-flex cursor-pointer items-center gap-2.5">
                                                    <input type="checkbox"
                                                        wire:model="asistencias.{{ $item['inscripcion']->id }}.presente"
                                                        aria-label="Asistencia de {{ $item['inscripcion']->matriculaPeriodo->alumno->nombre_completo }}"
                                                        class="h-4 w-4 rounded border-brand-line accent-brand-primary" />
                                                    <x-ui.badge :tone="$item['presente'] ? 'success' : 'neutral'">
                                                        {{ $item['presente'] ? 'Presente' : 'Ausente' }}
                                                    </x-ui.badge>
                                                </label>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2 border-t border-brand-line px-5 py-4">
                            <x-ui.button variant="secondary" icon="arrow-path" wire:click="cargarLista">
                                Recargar
                            </x-ui.button>
                            <x-ui.button type="submit" icon="check"
                                wire:loading.attr="disabled" wire:target="guardar">
                                <span wire:loading.remove wire:target="guardar">Guardar asistencia</span>
                                <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                            </x-ui.button>
                        </div>
                    </form>
                @endif
            </x-ui.card>
        </div>
    @endif

    {{-- Modal de registro individual (opcional) --}}
    <livewire:academico.asistencia-form />
</div>
