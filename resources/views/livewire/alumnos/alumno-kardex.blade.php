<div>
    <x-ui.page-header title="Kardex" description="Historial académico de su expediente.">
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
        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="ui-card p-5">
                <p class="tabular-nums text-2xl font-semibold text-brand-text">
                    {{ $resumen['promedio'] !== null ? number_format($resumen['promedio'], 2) : '—' }}
                </p>
                <p class="mt-1 text-xs text-brand-muted">Promedio general</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-2xl font-semibold text-brand-primary">
                    {{ $resumen['creditos_aprobados'] }} / {{ $resumen['creditos_totales'] }}
                </p>
                <p class="mt-1 text-xs text-brand-muted">Créditos aprobados</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-2xl font-semibold text-brand-text">{{ $resumen['creditos_cursados'] }}</p>
                <p class="mt-1 text-xs text-brand-muted">Créditos cursados</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-2xl font-semibold text-brand-text">
                    {{ number_format($resumen['porcentaje_avance'], 2) }}%
                </p>
                <p class="mt-1 text-xs text-brand-muted">Avance del plan</p>
            </div>
        </div>

        <div class="mt-5">
            <x-ui.card :flush="$filas->isNotEmpty()">
                <x-slot:header>
                    <p class="text-sm font-semibold text-brand-text">Materias cursadas</p>
                </x-slot:header>

                @if ($filas->isEmpty())
                    <x-ui.empty-state icon="document-text" title="Sin materias en el historial"
                        message="Aún no hay materias registradas en su expediente." />
                @else
                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th scope="col">Periodo</th>
                                    <th scope="col">Materia</th>
                                    <th scope="col">Grupo</th>
                                    <th scope="col" class="text-right">Calificación</th>
                                    <th scope="col" class="text-right">Créditos</th>
                                    <th scope="col">Estatus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filas as $fila)
                                    <tr>
                                        <td class="whitespace-nowrap text-brand-muted">{{ $fila['periodo']['clave'] }}</td>
                                        <td class="font-medium text-brand-text">
                                            {{ $fila['materia']['nombre'] ?? '—' }}
                                            <span class="text-brand-muted">· {{ $fila['materia']['clave'] ?? '' }}</span>
                                        </td>
                                        <td class="text-brand-muted">{{ $fila['grupo']['clave'] ?? '—' }}</td>
                                        <td class="text-right tabular-nums">
                                            {{ $fila['calificacion_final'] ?? '—' }}
                                        </td>
                                        <td class="text-right tabular-nums">{{ $fila['materia']['creditos'] ?? '—' }}</td>
                                        <td>
                                            <x-ui.badge :tone="match (true) {
                                                $fila['es_aprobada'] => 'success',
                                                $fila['es_reprobada'] => 'danger',
                                                $fila['es_cursando'] => 'info',
                                                default => 'neutral',
                                            }">
                                                @if ($fila['es_equivalencia'])
                                                    Equivalencia
                                                @elseif ($fila['es_cursando'])
                                                    Cursando
                                                @elseif ($fila['es_aprobada'])
                                                    Aprobada
                                                @elseif ($fila['es_reprobada'])
                                                    Reprobada
                                                @else
                                                    {{ $fila['estatus'] }}
                                                @endif
                                            </x-ui.badge>
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
</div>
