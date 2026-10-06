<div>
    <x-ui.page-header title="Captura de Calificaciones"
        :description="$grupo
            ? 'Matriz de calificaciones del grupo ' . $grupo->clave . ' (' . $grupo->materia->clave . ').'
            : 'Selecciona un grupo para abrir la matriz y capturar calificaciones.'">
        <x-slot:meta>
            @if ($grupo)
                <x-ui.badge tone="primary">{{ $grupo->clave }}</x-ui.badge>
                <x-ui.badge tone="neutral">{{ $grupo->materia->clave }}</x-ui.badge>
            @endif
        </x-slot:meta>
    </x-ui.page-header>

    {{-- --------------------- Selección de grupo --------------------- --}}
    <div class="mt-5">
        <x-ui.filters>
            <div class="grid gap-3 md:grid-cols-4">
                <x-ui.field label="Grupo" hint="Filtra la matriz por grupo.">
                    {{-- wire:model sobre una propiedad tipada (Model) no puede recibir el id
                         del navegador: el id se envía a la acción y Blade marca la opción. --}}
                    <x-ui.select wire:change="seleccionarGrupo($event.target.value)" aria-label="Grupo">
                        <option value="">Seleccionar grupo</option>
                        @foreach ($grupos as $g)
                            <option value="{{ $g->id }}" @selected($grupo?->getKey() === $g->id)>
                                {{ $g->clave }} - {{ $g->materia->clave }} ({{ $g->periodo->clave }})
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            </div>
        </x-ui.filters>
    </div>

    @if (! $grupo)
        <div class="mt-5">
            <x-ui.empty-state icon="chart-bar" title="Sin grupo seleccionado"
                message="Elige un grupo en el filtro para ver la matriz de calificaciones del grupo." />
        </div>
    @else
        {{-- ------------------- Resumen de ponderación ------------------- --}}
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <x-ui.card>
                <x-slot:header>
                    <p class="text-sm font-semibold text-brand-text">Configuración del grupo</p>
                </x-slot:header>

                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-brand-muted">Grupo</dt>
                        <dd class="font-medium text-brand-text">
                            {{ $grupo->clave }} · {{ $grupo->materia->clave }} · {{ $grupo->materia->nombre }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-brand-muted">Docente</dt>
                        <dd class="font-medium text-brand-text">{{ $grupo->docente?->nombreCompleto() ?? 'Sin asignar' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-brand-muted">Periodo</dt>
                        <dd class="font-medium text-brand-text">{{ $grupo->periodo->clave }} · {{ $grupo->periodo->nombre }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-brand-muted">Cupo / inscritos</dt>
                        <dd class="font-medium text-brand-text">
                            {{ $grupo->cupo }} / {{ $grupo->inscripcionesMaterias()->where('estatus', 'CURSANDO')->count() }}
                        </dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card>
                <x-slot:header>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-brand-text">Ponderación de evaluaciones</p>
                        @php
                            $ponderacionTotal = \App\Models\Evaluacion::ponderacionTotalGrupo($grupo->id);
                        @endphp
                        <x-ui.badge :tone="$ponderacionTotal === 100 ? 'success' : 'warning'">
                            {{ $ponderacionTotal }}%
                        </x-ui.badge>
                    </div>
                </x-slot:header>

                @if (count($evaluaciones) === 0)
                    <p class="text-sm text-brand-muted">Sin evaluaciones definidas para este grupo.</p>
                @else
                    <ul class="divide-y divide-brand-line text-sm">
                        @foreach ($evaluaciones as $eval)
                            <li class="flex items-center justify-between gap-3 py-1.5">
                                <span class="min-w-0 text-brand-text">
                                    {{ $eval->nombre }}
                                    <span class="text-xs text-brand-muted">
                                        · {{ \App\Enums\TipoEvaluacion::EJEMPLOS[$eval->tipo] ?? $eval->tipo }}
                                    </span>
                                </span>
                                <span class="tabular-nums font-semibold text-brand-text">{{ $eval->ponderacion }}%</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        {{-- ----------------------- Matriz de calificaciones ----------------------- --}}
        <div class="mt-4">
            <x-ui.card :flush="count($inscripciones) > 0">
                <x-slot:header>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-brand-text">Matriz de calificaciones</p>
                            <p class="mt-0.5 text-xs text-brand-muted">
                                Promedio ponderado calculado con la ponderación de cada evaluación.
                            </p>
                        </div>

                        @if (\App\Models\Evaluacion::ponderacionTotalGrupo($grupo->id) === 100)
                            <x-ui.button size="sm" variant="success" icon="check-circle"
                                wire:click="consolidar({{ $grupo->inscripcionesMaterias()->where('estatus', 'CURSANDO')->first()?->id ?? 0 }})"
                                :disabled="count($inscripciones) === 0"
                                wire:loading.attr="disabled" wire:target="consolidar">
                                Consolidar Calificaciones Finales
                            </x-ui.button>
                        @endif
                    </div>
                </x-slot:header>

                @if (count($inscripciones) === 0)
                    <x-ui.empty-state icon="users" title="Sin alumnos inscritos en este grupo"
                        message="Inscribe alumnos al grupo para comenzar la captura." />
                @else
                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="min-w-[150px]">Alumno</th>
                                    <th scope="col" class="min-w-[100px]">N.º de control</th>
                                    @foreach ($evaluaciones as $eval)
                                        <th scope="col" class="min-w-[80px] text-right"
                                            title="{{ $eval->nombre }} ({{ $eval->tipo }})">
                                            <span class="block font-semibold">{{ $eval->nombre }}</span>
                                            <span class="block text-xs font-normal text-brand-muted">
                                                {{ $eval->ponderacion }}%
                                            </span>
                                        </th>
                                    @endforeach
                                    <th scope="col" class="min-w-[100px] text-right">Promedio</th>
                                    <th scope="col" class="min-w-[110px]">Estatus</th>
                                    <th scope="col" class="min-w-[120px] text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($inscripciones as $inscripcion)
                                    @php
                                        $totalPonderado = 0;
                                        $totalPonderacion = 0;
                                        foreach ($evaluaciones as $eval) {
                                            $cal = $inscripcion->calificaciones->where('evaluacion_id', $eval->id)->first();
                                            if ($cal) {
                                                $totalPonderado += $cal->valor * ($eval->ponderacion / 100);
                                                $totalPonderacion += $eval->ponderacion / 100;
                                            }
                                        }
                                    @endphp
                                    <tr wire:key="calif-{{ $inscripcion->id }}">
                                        <td class="font-medium text-brand-text">
                                            {{ $inscripcion->matriculaPeriodo->alumno->nombre_completo }}
                                        </td>
                                        <td class="font-mono text-xs text-brand-muted">
                                            {{ $inscripcion->matriculaPeriodo->alumno->numero_control ?? '—' }}
                                        </td>
                                        @foreach ($evaluaciones as $eval)
                                            @php
                                                $cal = $inscripcion->calificaciones->where('evaluacion_id', $eval->id)->first();
                                            @endphp
                                            <td class="text-right tabular-nums">
                                                <button type="button"
                                                    class="-m-1.5 rounded px-1.5 py-1.5 transition-colors hover:bg-brand-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
                                                    wire:click="abrirModalCapturar({{ $inscripcion->id }}, {{ $eval->id }})"
                                                    wire:loading.attr="disabled"
                                                    aria-label="Capturar {{ $eval->nombre }} de {{ $inscripcion->matriculaPeriodo->alumno->nombre_completo }}">
                                                    @if ($cal !== null)
                                                        <x-ui.badge :tone="$cal->valor >= 60 ? 'success' : 'danger'">
                                                            {{ number_format($cal->valor, 2) }}
                                                        </x-ui.badge>
                                                    @else
                                                        <span class="text-brand-muted">—</span>
                                                    @endif
                                                </button>
                                            </td>
                                        @endforeach
                                        <td class="text-right tabular-nums font-semibold text-brand-text">
                                            @if ($totalPonderacion > 0)
                                                {{ number_format($totalPonderado / $totalPonderacion, 2) }}
                                            @else
                                                <span class="font-normal text-brand-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <x-ui.badge :tone="match ($inscripcion->estatus->value) {
                                                'APROBADA' => 'success',
                                                'REPROBADA' => 'danger',
                                                default => 'neutral',
                                            }">
                                                {{ $inscripcion->estatus->label() }}
                                            </x-ui.badge>
                                        </td>
                                        <td class="text-right">
                                            <div class="ui-row-actions" role="group"
                                                aria-label="Acciones de {{ $inscripcion->matriculaPeriodo->alumno->nombre_completo }}">
                                                @if (\App\Models\Evaluacion::ponderacionTotalGrupo($grupo->id) === 100
                                                    && $inscripcion->estatus->value === 'CURSANDO')
                                                    <x-ui.icon-button label="Consolidar calificación final"
                                                        icon="check-circle" variant="success"
                                                        wire:click="consolidar({{ $inscripcion->id }})"
                                                        wire:confirm="¿Consolidar la calificación final de este alumno? Esta acción no se puede deshacer." />
                                                @else
                                                    <span class="text-brand-muted" aria-hidden="true">—</span>
                                                @endif
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

    @endif

    @if (session('success'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('success') }}</x-ui.alert></div>
    @endif
    @error('consolidar')
        <div class="mt-4"><x-ui.alert tone="danger">{{ $message }}</x-ui.alert></div>
    @enderror


    {{-- ------------------------- Modal de captura ------------------------- --}}
    <x-ui.modal id="modal-capturar-calificacion"
        :title="$evaluacionCaptura ? 'Capturar ' . $evaluacionCaptura->nombre : 'Capturar Calificación'"
        :open="$modalCalificacionAbierto" close="cerrarModalCalificacion">
        <form novalidate id="form-calificacion" wire:submit="guardarCalificacion">
            @if ($errors->any())
                <x-ui.alert tone="danger">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            <div class="mt-4 space-y-3">
                @if ($inscripcionCaptura && $evaluacionCaptura)
                    <p class="text-sm text-brand-muted">
                        {{ $inscripcionCaptura->matriculaPeriodo->alumno->nombre_completo }}
                        · {{ $evaluacionCaptura->nombre }}
                        ({{ number_format($evaluacionCaptura->ponderacion, 0) }}%)
                    </p>
                @endif

                <x-ui.field label="Calificación" for="cal-valor" required
                    :error="$errors->first('valor')" hint="Entre 0.00 y 100.00">
                    <x-ui.input id="cal-valor" type="number" wire:model="valor"
                        min="0" max="100" step="0.01" />
                </x-ui.field>

                <x-ui.field label="Observación" for="cal-observacion" :error="$errors->first('observacion')">
                    <textarea id="cal-observacion" wire:model="observacion" rows="3" maxlength="1000"
                        class="ui-textarea" placeholder="Observación opcional…"></textarea>
                </x-ui.field>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="cerrarModalCalificacion">Cancelar</x-ui.button>
                <x-ui.button type="submit" form="form-calificacion" icon="check" wire:loading.attr="disabled" wire:target="guardarCalificacion">
                    <span wire:loading.remove wire:target="guardarCalificacion">Guardar</span>
                    <span wire:loading wire:target="guardarCalificacion"><x-ui.loading label="Guardando…" /></span>
                </x-ui.button>
            </x-slot:footer>
        </form>
    </x-ui.modal>
</div>
