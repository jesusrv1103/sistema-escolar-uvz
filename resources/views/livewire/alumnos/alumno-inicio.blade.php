<div>
    <x-ui.page-header title="Portal del alumno"
        description="Su información académica, horario, documentos y estado de cuenta." />

    @if ($alumno === null)
        <div class="mt-5">
            <x-ui.card>
                <x-ui.empty-state icon="user" title="Sin expediente vinculado"
                    message="La cuenta no tiene un alumno asociado; pide a Control Escolar vincular tu expediente." />
            </x-ui.card>
        </div>
    @else
        {{-- Datos de identidad --}}
        <div class="mt-5">
            <x-ui.card>
                <x-slot:header>
                    <p class="text-sm font-semibold text-brand-text">Mis datos</p>
                </x-slot:header>

                <div class="grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-brand-muted">Nombre</p>
                        <p class="text-sm text-brand-text">{{ $alumno->nombre_completo }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-brand-muted">Número de control</p>
                        <p class="text-sm text-brand-text">{{ $alumno->numero_control ?? 'Por asignar' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-brand-muted">CURP</p>
                        <p class="text-sm text-brand-text">{{ $alumno->curp }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-brand-muted">Correo</p>
                        <p class="text-sm text-brand-text">{{ $alumno->correo ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-brand-muted">Programa</p>
                        <p class="text-sm text-brand-text">
                            {{ $matricula?->programa?->nombre ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-brand-muted">Periodo activo</p>
                        <p class="text-sm text-brand-text">{{ $matricula?->periodo?->nombre ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-brand-muted">Estatus de matrícula</p>
                        <p class="text-sm text-brand-text">{{ $matricula?->estatus->label() ?? 'Sin matrícula activa' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-brand-muted">Plan de estudios</p>
                        <p class="text-sm text-brand-text">
                            {{ $matricula?->planVersion?->plan?->nombre ?? '—' }}
                        </p>
                    </div>
                </div>
            </x-ui.card>
        </div>

        {{-- Resumen académico --}}
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
                <p class="tabular-nums text-2xl font-semibold text-brand-text">
                    {{ number_format($resumen['porcentaje_avance'], 2) }}%
                </p>
                <p class="mt-1 text-xs text-brand-muted">Avance del plan</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-2xl font-semibold text-brand-text">{{ $matricula?->periodo?->clave ?? '—' }}</p>
                <p class="mt-1 text-xs text-brand-muted">Último periodo con matrícula</p>
            </div>
        </div>

        {{-- Accesos --}}
        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.card>
                <x-slot:header><p class="text-sm font-semibold text-brand-text">Kardex</p></x-slot:header>
                <p class="text-sm text-brand-muted">Historial de materias, calificaciones y créditos.</p>
                <div class="mt-3">
                    <x-ui.button size="sm" variant="secondary" href="{{ route('alumno.kardex') }}" icon="document-text">
                        Ver kardex
                    </x-ui.button>
                </div>
            </x-ui.card>
            <x-ui.card>
                <x-slot:header><p class="text-sm font-semibold text-brand-text">Horario</p></x-slot:header>
                <p class="text-sm text-brand-muted">Días, horas y aulas de sus materias activas.</p>
                <div class="mt-3">
                    <x-ui.button size="sm" variant="secondary" href="{{ route('alumno.horario') }}" icon="calendar">
                        Ver horario
                    </x-ui.button>
                </div>
            </x-ui.card>
            <x-ui.card>
                <x-slot:header><p class="text-sm font-semibold text-brand-text">Estado de cuenta</p></x-slot:header>
                <p class="text-sm text-brand-muted">Cargos, pagos validados y saldo exigible.</p>
                <div class="mt-3">
                    <x-ui.button size="sm" variant="secondary" href="{{ route('alumno.estado.cuenta') }}" icon="credit-card">
                        Ver estado de cuenta
                    </x-ui.button>
                </div>
            </x-ui.card>
            <x-ui.card>
                <x-slot:header><p class="text-sm font-semibold text-brand-text">Documentos</p></x-slot:header>
                <p class="text-sm text-brand-muted">Expediente y formatos emitidos a su nombre.</p>
                <div class="mt-3">
                    <x-ui.button size="sm" variant="secondary" href="{{ route('alumno.documentos') }}" icon="folder">
                        Ver documentos
                    </x-ui.button>
                </div>
            </x-ui.card>
        </div>
    @endif
</div>
