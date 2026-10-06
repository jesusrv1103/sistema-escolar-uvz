<div>
    <x-ui.page-header title="Horario" description="Materias activas de su matrícula.">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('alumno.inicio') }}" icon="arrow-left">
                Volver al inicio
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($alumno === null)
        <div class="mt-5">
            <x-ui.card>
                <x-ui.empty-state icon="user" title="Sin expediente vinculado"
                    message="La cuenta no tiene un alumno asociado." />
            </x-ui.card>
        </div>
    @else
        @if ($matricula !== null)
            <div class="mt-5">
                <x-ui.alert tone="info">
                    Periodo {{ $matricula->periodo?->nombre ?? '—' }} · matrícula
                    {{ $matricula->estatus->label() }}
                </x-ui.alert>
            </div>
        @endif

        <div class="mt-5">
            <x-ui.card :flush="$inscripciones->isNotEmpty()">
                <x-slot:header>
                    <p class="text-sm font-semibold text-brand-text">Clases de la semana</p>
                </x-slot:header>

                @php($totalHorarios = $inscripciones->sum(fn ($i) => count($i->grupo?->horarios ?? [])))

                @if ($totalHorarios === 0)
                    <x-ui.empty-state icon="calendar" title="Sin horario registrado"
                        message="No hay grupos con horario en su matrícula activa." />
                @else
                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th scope="col">Materia</th>
                                    <th scope="col">Grupo</th>
                                    <th scope="col">Día</th>
                                    <th scope="col">Horario</th>
                                    <th scope="col">Aula</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($inscripciones as $inscripcion)
                                    @foreach ($inscripcion->grupo?->horarios ?? [] as $horario)
                                        <tr>
                                            <td class="font-medium text-brand-text">
                                                {{ $inscripcion->grupo?->materia?->nombre }}
                                                <span class="text-brand-muted">· {{ $inscripcion->grupo?->materia?->clave }}</span>
                                            </td>
                                            <td class="text-brand-muted">{{ $inscripcion->grupo?->clave ?? '—' }}</td>
                                            <td>{{ $horario->dia->label() }}</td>
                                            <td class="whitespace-nowrap tabular-nums">
                                                {{ $horario->hora_inicio?->format('H:i') }}–{{ $horario->hora_fin?->format('H:i') }}
                                            </td>
                                            <td class="text-brand-muted">{{ $inscripcion->grupo?->aula?->nombre ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>
    @endif
</div>
