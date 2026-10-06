<div>
    <x-ui.page-header title="Historial Académico"
        description="Kardex y avance académico por alumno: tira de materias y detalle por periodos." />

    {{-- --------------------------- Búsqueda ---------------------------- --}}
    <div class="mt-5">
        <x-ui.filters>
            <div class="grid gap-3 md:grid-cols-5">
                <div class="md:col-span-3">
                    <x-ui.field label="Buscar alumno">
                        <x-ui.input type="text" wire:model.live.debounce.300ms="busqueda"
                            placeholder="Nombre, apellidos, CURP, número de control…"
                            aria-label="Buscar alumno" />
                    </x-ui.field>
                </div>

                <div class="md:col-span-2">
                    <x-ui.field label="Filtrar por periodo">
                        <x-ui.select wire:model.live="filtroPeriodo" aria-label="Filtrar por periodo">
                            <option value="">Todos los periodos</option>
                            @foreach ($periodos as $p)
                                <option value="{{ $p->id }}">{{ $p->clave }} - {{ $p->nombre }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>
            </div>

            <div class="mt-3 flex justify-end border-t border-brand-line pt-3">
                <x-ui.button size="sm" variant="ghost" icon="x-mark" wire:click="limpiarFiltros">
                    Limpiar filtros
                </x-ui.button>
            </div>
        </x-ui.filters>
    </div>

    @if (! $alumno)
        {{-- ----------------------- Selector de alumno ---------------------- --}}
        <div class="mt-4">
            <x-ui.card :flush="$alumnos->isNotEmpty()">
                <x-slot:header>
                    <p class="text-sm font-semibold text-brand-text">Seleccione un alumno</p>
                </x-slot:header>

                @if ($alumnos->isEmpty())
                    <x-ui.empty-state icon="users" title="Sin alumnos"
                        message="No hay alumnos registrados{{ $busqueda ? ' con ese criterio' : '' }}." />
                @else
                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th scope="col">N.º de control</th>
                                    <th scope="col">Alumno</th>
                                    <th scope="col">CURP</th>
                                    <th scope="col">Programa</th>
                                    <th scope="col">Periodo activo</th>
                                    <th scope="col" class="text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($alumnos as $a)
                                    <tr wire:key="alumno-hist-{{ $a->id }}" wire:click="alumnoSeleccionado({{ $a->id }})"
                                        class="cursor-pointer">
                                        <td class="font-mono text-xs">{{ $a->numero_control ?? 'Por asignar' }}</td>
                                        <td class="font-medium text-brand-text">{{ $a->nombre_completo }}</td>
                                        <td class="font-mono text-xs">{{ $a->curp }}</td>
                                        <td class="text-brand-muted">
                                            {{ $a->matriculas->first()?->programa->clave ?? '—' }}
                                        </td>
                                        <td class="text-brand-muted">
                                            {{ $a->matriculas->first()?->periodo->clave ?? '—' }}
                                        </td>
                                        <td class="text-right">
                                            <x-ui.button size="sm" variant="secondary" icon="clipboard"
                                                wire:click.stop="alumnoSeleccionado({{ $a->id }})">
                                                Ver historial
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-brand-line px-5 py-3">{{ $alumnos->links() }}</div>
                @endif
            </x-ui.card>
        </div>
    @else
        {{-- -------------------------- Resumen académico ---------------------- --}}
        @php
            $service = app(\App\Services\HistorialAcademicoService::class);
            $resumen = $service->resumenAcademico($alumno);
            $tira = $service->tiraMaterias($alumno);
        @endphp

        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="ui-card p-5">
                <p class="tabular-nums text-3xl font-semibold text-brand-text">{{ $resumen['promedio'] ?? '—' }}</p>
                <p class="mt-1 text-xs text-brand-muted">Promedio ponderado</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-3xl font-semibold text-brand-primary">{{ $resumen['porcentaje_avance'] }}%</p>
                <p class="mt-1 text-xs text-brand-muted">Avance académico</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-3xl font-semibold text-brand-text">
                    {{ $resumen['creditos_aprobados'] }} / {{ $resumen['creditos_totales'] }}
                </p>
                <p class="mt-1 text-xs text-brand-muted">Créditos aprobados / totales</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-3xl font-semibold text-warning-ink">{{ $resumen['creditos_pendientes'] }}</p>
                <p class="mt-1 text-xs text-brand-muted">Créditos pendientes</p>
            </div>
        </div>

        {{-- --------------------------- Datos del alumno ----------------------- --}}
        <div class="mt-4">
            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-base font-semibold text-brand-text">{{ $alumno->nombre_completo }}</p>
                        @if ($alumno->numero_control)
                            <p class="font-mono text-xs text-brand-muted">{{ $alumno->numero_control }}</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-brand-muted">Generar formatos</span>
                        <x-ui.button size="sm" variant="secondary" icon="document-text"
                            :href="route('formatos-academicos.kardex', $alumno)">
                            Kardex
                        </x-ui.button>
                        <x-ui.button size="sm" variant="secondary" icon="receipt"
                            :href="route('formatos-academicos.boleta', $alumno)">
                            Boleta
                        </x-ui.button>
                        <x-ui.button size="sm" variant="secondary" icon="clipboard"
                            :href="route('formatos-academicos.historial', $alumno)">
                            Historial
                        </x-ui.button>
                    </div>
                </div>
            </x-ui.card>
        </div>

        {{-- ---------------------- Tira de materias ------------------------- --}}
        <div class="mt-4">
            <x-ui.card :flush="$tira->isNotEmpty()">
                <x-slot:header>
                    <p class="text-sm font-semibold text-brand-text">Tira de materias / historial académico</p>
                </x-slot:header>

                @if ($tira->isEmpty())
                    <x-ui.empty-state icon="clipboard" title="Sin materias"
                        message="No hay materias registradas en el plan de estudios." />
                @else
                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th scope="col">Clave</th>
                                    <th scope="col">Materia</th>
                                    <th scope="col" class="text-right">Créditos</th>
                                    <th scope="col">Estatus</th>
                                    <th scope="col" class="text-right">Calificación</th>
                                    <th scope="col">Observación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tira as $m)
                                    <tr wire:key="tira-{{ $m['materia']['clave'] }}-{{ $m['estatus'] }}">
                                        <td class="font-mono text-xs">{{ $m['materia']['clave'] }}</td>
                                        <td class="font-medium text-brand-text">{{ $m['materia']['nombre'] }}</td>
                                        <td class="text-right tabular-nums">{{ $m['materia']['creditos'] }}</td>
                                        <td>
                                            <x-ui.badge :tone="match ($m['estatus']) {
                                                'APROBADA' => 'success',
                                                'REPROBADA' => 'danger',
                                                'EXTRAORDINARIO' => 'warning',
                                                'CURSANDO' => 'primary',
                                                default => 'neutral',
                                            }">
                                                {{ $m['estatus'] }}
                                            </x-ui.badge>
                                        </td>
                                        <td class="text-right tabular-nums">{{ $m['calificacion_final'] ?? '—' }}</td>
                                        <td>
                                            <div class="flex flex-wrap gap-1.5">
                                                @if ($m['es_extraordinario'])
                                                    <x-ui.badge tone="warning">Extraordinario</x-ui.badge>
                                                @endif
                                                @if ($m['es_equivalencia'] ?? false)
                                                    <x-ui.badge tone="info">Equivalencia</x-ui.badge>
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

        {{-- ------------------------ Detalle por periodos --------------------- --}}
        @php
            $historialPorPeriodo = $resumen['historial']->groupBy('periodo.clave');
        @endphp

        <div class="mt-4">
            <x-ui.card :flush="$historialPorPeriodo->isNotEmpty()">
                <x-slot:header>
                    <p class="text-sm font-semibold text-brand-text">Detalle por periodos</p>
                </x-slot:header>

                @forelse ($historialPorPeriodo as $periodoClave => $materias)
                    @if (! $loop->first)
                        <div class="border-t border-brand-line"></div>
                    @endif
                    <div class="border-t border-brand-line px-5 py-3 text-xs font-semibold uppercase tracking-wide text-brand-muted first:border-t-0">
                        {{ $periodoClave }}
                    </div>

                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th scope="col">Clave</th>
                                    <th scope="col">Materia</th>
                                    <th scope="col" class="text-right">Créditos</th>
                                    <th scope="col">Estatus</th>
                                    <th scope="col" class="text-right">Calificación</th>
                                    <th scope="col">Observación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($materias as $m)
                                    <tr wire:key="hist-{{ $periodoClave }}-{{ $m['materia']['clave'] }}">
                                        <td class="font-mono text-xs">{{ $m['materia']['clave'] }}</td>
                                        <td class="font-medium text-brand-text">{{ $m['materia']['nombre'] }}</td>
                                        <td class="text-right tabular-nums">{{ $m['materia']['creditos'] }}</td>
                                        <td>
                                            <x-ui.badge :tone="match ($m['estatus']) {
                                                'APROBADA' => 'success',
                                                'REPROBADA' => 'danger',
                                                'EXTRAORDINARIO' => 'warning',
                                                'CURSANDO' => 'primary',
                                                default => 'neutral',
                                            }">
                                                {{ $m['estatus'] }}
                                            </x-ui.badge>
                                        </td>
                                        <td class="text-right tabular-nums">{{ $m['calificacion_final'] ?? '—' }}</td>
                                        <td>
                                            @if ($m['es_extraordinario'])
                                                <x-ui.badge tone="warning">Extraordinario</x-ui.badge>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <x-ui.empty-state icon="clipboard" title="Sin periodos"
                        message="Aún no hay historial por periodos para este alumno." />
                @endforelse
            </x-ui.card>
        </div>

        <div class="mt-4 flex justify-start">
            <x-ui.button variant="secondary" icon="arrow-left" :href="url()->previous()">
                Volver a la lista
            </x-ui.button>
        </div>
    @endif
</div>
