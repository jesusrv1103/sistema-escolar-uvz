<div>
    <x-ui.page-header title="Portal del docente"
        description="Sus grupos asignados y acceso a evaluaciones, calificaciones, asistencia y actas." />

    @if (session('success'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('success') }}</x-ui.alert></div>
    @endif

    <div class="mt-5">
        <x-ui.card>
            <x-slot:header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-brand-text">Mis grupos</p>
                    <span class="text-xs text-brand-muted">
                        {{ $docente?->nombre }} {{ $docente?->apellidos }}
                    </span>
                </div>
            </x-slot:header>

            @if ($docente === null)
                <x-ui.empty-state icon="user" title="Sin perfil de docente vinculado"
                    message="La cuenta no tiene un perfil de docente asociado; pide a Control Escolar vincularlo." />
            @elseif ($grupos->isEmpty())
                <x-ui.empty-state icon="academic-cap" title="Sin grupos asignados"
                    message="Cuando se asigne un grupo a su perfil aparecerá aquí." />
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($grupos as $grupo)
                        <div id="grupo-{{ $grupo->id }}"
                            class="flex flex-col gap-3 rounded-lg border border-brand-line p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-brand-text">
                                        {{ $grupo->materia?->nombre }}
                                    </p>
                                    <p class="text-xs text-brand-muted">
                                        Grupo {{ $grupo->clave }}
                                        · {{ $grupo->materia?->clave }}
                                    </p>
                                </div>
                                <x-ui.badge :tone="$grupo->estaAbierto() ? 'success' : 'neutral'">
                                    {{ $grupo->estatus->label() }}
                                </x-ui.badge>
                            </div>

                            <dl class="space-y-1 text-xs text-brand-muted">
                                <div class="flex justify-between gap-2">
                                    <dt>Periodo</dt>
                                    <dd class="text-brand-text">{{ $grupo->periodo?->clave ?? '—' }}</dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt>Alumnos inscritos</dt>
                                    <dd class="text-brand-text tabular-nums">
                                        {{ $grupo->alumnos_inscritos ?? 0 }}
                                        @if ($grupo->cupo)
                                            / {{ $grupo->cupo }}
                                        @endif
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt>Aula</dt>
                                    <dd class="text-brand-text">{{ $grupo->aula?->nombre ?? '—' }}</dd>
                                </div>
                            </dl>

                            @if (count($grupo->horarios) > 0)
                                <ul class="space-y-0.5 text-xs text-brand-muted">
                                    @foreach ($grupo->horarios as $horario)
                                        <li>
                                            {{ $horario->dia->label() }}
                                            {{ $horario->hora_inicio?->format('H:i') }}–{{ $horario->hora_fin?->format('H:i') }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="mt-auto flex flex-wrap gap-2 border-t border-brand-line pt-3">
                                <x-ui.button size="sm" variant="secondary" href="{{ route('docente.grupos.ver', $grupo) }}"
                                    icon="eye">Ver grupo</x-ui.button>
                                <x-ui.button size="sm" variant="ghost"
                                    href="{{ route('docente.grupos.evaluaciones', $grupo) }}" icon="clipboard">
                                    Evaluaciones
                                </x-ui.button>
                                <x-ui.button size="sm" variant="ghost"
                                    href="{{ route('docente.grupos.calificaciones', $grupo) }}" icon="chart-bar">
                                    Calificaciones
                                </x-ui.button>
                                <x-ui.button size="sm" variant="ghost"
                                    href="{{ route('docente.grupos.asistencia', $grupo) }}" icon="check-circle">
                                    Asistencia
                                </x-ui.button>
                                <x-ui.button size="sm" variant="ghost"
                                    href="{{ route('docente.grupos.actas', $grupo) }}" icon="document-text">
                                    Actas
                                </x-ui.button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
