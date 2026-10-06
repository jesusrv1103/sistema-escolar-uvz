<div>
    <x-ui.page-header title="{{ $grupo->materia?->nombre ?? 'Grupo' }}"
        description="Grupo {{ $grupo->clave }} · {{ $grupo->periodo?->clave ?? '—' }} — sólo su grupo asignado.">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('docente.inicio') }}" icon="arrow-left">
                Volver a mis grupos
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Accesos de captura (componentes reutilizados, acotados a este grupo) --}}
    <div class="mt-5 flex flex-wrap gap-2">
        <x-ui.button variant="secondary" href="{{ route('docente.grupos.evaluaciones', $grupo) }}" icon="clipboard">
            Evaluaciones
        </x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('docente.grupos.calificaciones', $grupo) }}" icon="chart-bar">
            Calificaciones
        </x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('docente.grupos.asistencia', $grupo) }}" icon="check-circle">
            Asistencia
        </x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('docente.grupos.actas', $grupo) }}" icon="document-text">
            Actas
        </x-ui.button>
    </div>

    {{-- Datos del grupo --}}
    <div class="mt-5">
        <x-ui.card>
            <x-slot:header>
                <p class="text-sm font-semibold text-brand-text">Datos del grupo</p>
            </x-slot:header>

            <div class="grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-brand-muted">Materia</p>
                    <p class="text-sm text-brand-text">{{ $grupo->materia?->clave }} · {{ $grupo->materia?->nombre }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-brand-muted">Periodo</p>
                    <p class="text-sm text-brand-text">{{ $grupo->periodo?->nombre ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-brand-muted">Estatus</p>
                    <p class="text-sm text-brand-text">{{ $grupo->estatus->label() }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-brand-muted">Aula</p>
                    <p class="text-sm text-brand-text">{{ $grupo->aula?->nombre ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-brand-muted">Créditos</p>
                    <p class="text-sm text-brand-text">{{ $grupo->materia?->creditos ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-brand-muted">Horarios</p>
                    <p class="text-sm text-brand-text">
                        @if (count($grupo->horarios) === 0)
                            —
                        @else
                            {{ $grupo->horarios->map(fn ($h) => $h->dia->label() . ' ' . $h->hora_inicio?->format('H:i') . '–' . $h->hora_fin?->format('H:i'))->implode(' · ') }}
                        @endif
                    </p>
                </div>
            </div>
        </x-ui.card>
    </div>

    {{-- Alumnos inscritos (sólo lectura) --}}
    <div class="mt-5">
        <x-ui.card :flush="count($inscripciones) > 0">
            <x-slot:header>
                <p class="text-sm font-semibold text-brand-text">Alumnos inscritos</p>
            </x-slot:header>

            @if (count($inscripciones) === 0)
                <x-ui.empty-state icon="users" title="Sin alumnos inscritos"
                    message="Cuando se inscriban alumnos a este grupo aparecerán aquí." />
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th scope="col">Alumno</th>
                                <th scope="col">Número de control</th>
                                <th scope="col" class="text-right">Estatus</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inscripciones as $inscripcion)
                                <tr id="alumno-{{ $inscripcion->matriculaPeriodo?->alumno_id }}">
                                    <td class="font-medium text-brand-text">
                                        {{ $inscripcion->matriculaPeriodo?->alumno?->nombre_completo }}
                                    </td>
                                    <td class="text-brand-muted">
                                        {{ $inscripcion->matriculaPeriodo?->alumno?->numero_control ?? '—' }}
                                    </td>
                                    <td class="text-right">
                                        <x-ui.badge tone="neutral">{{ $inscripcion->estatus->label() }}</x-ui.badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
