{{-- Panel de revisión final (Fase 12, FC-7): requisitos RB-8 y cierre a
     ENROLLED con número de control. Sin información médica (RB-18) y sin
     mensualidades como requisito (RB-8 corregida: no bloquean); la acción
     siempre se delega server-side al servicio (@can solo complementa UX). --}}
<div class="mx-auto max-w-5xl px-4 py-6">
    {{-- Navegación --}}
    <nav class="mb-4 flex items-center gap-2 text-xs text-brand-muted">
        <a href="{{ route('inscripciones.lista') }}" class="font-medium text-brand-primary hover:text-brand-secondary">Inscripciones</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('inscripciones.ver', $inscripcion) }}" class="font-medium text-brand-primary hover:text-brand-secondary">Inscripción</a>
        <span aria-hidden="true">/</span>
        <span>Revisión final</span>
    </nav>

    <div class="rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-brand-text">Revisión final</h1>
                <p class="mt-1 text-xs text-brand-muted">
                    Estatus de la inscripción:
                    <span class="rounded-full border border-brand-line bg-brand-surface px-2 py-0.5 font-semibold">{{ $inscripcion->estatus->value }}</span>
                    · Verifica los requisitos antes de cerrar la inscripción y asignar el número de control.
                </p>
            </div>
            <a href="{{ route('inscripciones.ver', $inscripcion) }}"
                class="rounded-md border border-brand-line px-3 py-1.5 text-xs font-medium text-brand-primary hover:bg-brand-surface">
                Volver a la inscripción
            </a>
        </div>

        @if (session()->has('revision'))
            <p class="mt-4 rounded-md bg-green-50 px-3 py-2 text-xs font-semibold text-green-700">{{ session('revision') }}</p>
        @endif

        @error('panel')
            <p class="mt-4 rounded-md bg-red-50 px-3 py-2 text-xs text-red-600">{{ $message }}</p>
        @enderror

        {{-- Datos de la inscripción --}}
        <dl class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div><dt class="text-xs text-brand-muted">Alumno</dt>
                <dd class="text-sm font-medium text-brand-text">{{ $alumno?->nombre }} {{ $alumno?->apellidos }}</dd></div>
            <div><dt class="text-xs text-brand-muted">Número de control</dt>
                <dd class="text-sm font-medium text-brand-primary">{{ $alumno?->numero_control ?? 'Se asigna al finalizar' }}</dd></div>
            <div><dt class="text-xs text-brand-muted">Programa</dt>
                <dd class="text-sm font-medium text-brand-text">{{ $programa?->nombre ?? '—' }}</dd></div>
            <div><dt class="text-xs text-brand-muted">Periodo</dt>
                <dd class="text-sm font-medium text-brand-text">{{ $periodo?->nombre ?? '—' }}</dd></div>
        </dl>

        {{-- Requisitos de la revisión final (RB-8) --}}
        <h2 class="mt-6 text-sm font-semibold text-brand-text">Requisitos de cierre</h2>
        <ul class="mt-2 divide-y divide-brand-line rounded-xl border border-brand-line">
            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                <span class="text-sm text-brand-text">Pago del concepto INSCRIPCION</span>
                @if ($pagoOk)
                    <span class="rounded-full border border-green-600 px-2 py-0.5 text-xs font-semibold text-green-700">✓ PAGADO</span>
                @else
                    <span class="rounded-full border border-red-600 px-2 py-0.5 text-xs font-semibold text-red-600">
                        ✗ {{ $concepto?->estatus->value ?? 'sin generar' }}
                    </span>
                @endif
            </li>
            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                <span class="text-sm text-brand-text">Documentos obligatorios</span>
                @if ($documentosOk)
                    <span class="rounded-full border border-green-600 px-2 py-0.5 text-xs font-semibold text-green-700">
                        ✓ {{ $documentos['aceptados'] }}/{{ $documentos['total'] }} aceptados
                    </span>
                @else
                    <span class="rounded-full border border-red-600 px-2 py-0.5 text-xs font-semibold text-red-600">
                        ✗ {{ $documentos['aceptados'] }}/{{ $documentos['total'] }} aceptados ·
                        {{ $documentos['pendientes'] }} pendientes ·
                        {{ $documentos['rechazados'] }} rechazados ·
                        {{ $documentos['ausentes'] }} ausentes
                    </span>
                @endif
            </li>
            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                <span class="text-sm text-brand-text">Observaciones FALTANTE abiertas</span>
                @if ($faltantesAbiertos === 0)
                    <span class="rounded-full border border-green-600 px-2 py-0.5 text-xs font-semibold text-green-700">✓ Ninguna</span>
                @else
                    <span class="rounded-full border border-red-600 px-2 py-0.5 text-xs font-semibold text-red-600">✗ {{ $faltantesAbiertos }} abierta(s)</span>
                @endif
            </li>
            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                <span class="text-sm text-brand-text">Convenio</span>
                @if ($convenioOk)
                    <span class="rounded-full border border-green-600 px-2 py-0.5 text-xs font-semibold text-green-700">
                        ✓ {{ $convenioEstado === null ? 'No aplica' : 'APROBADO' }}
                    </span>
                @else
                    <span class="rounded-full border border-red-600 px-2 py-0.5 text-xs font-semibold text-red-600">✗ {{ $convenioEstado ?? 'sin resolver' }}</span>
                @endif
            </li>
            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                <span class="text-sm text-brand-text">Revisión final (Control Escolar)</span>
                @if ($enRevisionFinal)
                    <span class="rounded-full border border-green-600 px-2 py-0.5 text-xs font-semibold text-green-700">✓ En FINAL_REVIEW</span>
                @else
                    <span class="rounded-full border border-brand-line bg-brand-surface px-2 py-0.5 text-xs font-semibold text-brand-muted">
                        Estado actual: {{ $inscripcion->estatus->value }}
                    </span>
                @endif
            </li>
        </ul>

        @if ($requisitos !== [])
            <div class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <p class="font-semibold">Requisitos pendientes:</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($requisitos as $pendiente)
                        <li>{{ $pendiente }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($inscripcion->estatus === \App\Enums\InscripcionEstatus::ENROLLED)
            <div class="mt-5 rounded-md bg-green-50 px-3 py-3 text-xs text-green-800">
                <p class="font-semibold">Inscripción completada.</p>
                <p class="mt-1">
                    Número de control: <span class="font-semibold">{{ $alumno?->numero_control }}</span>
                    · finalizada {{ $inscripcion->finalizada_at?->format('d/m/Y H:i') }}
                    @if ($inscripcion->validadaPor !== null)
                        · por {{ $inscripcion->validadaPor->name }}
                    @endif
                </p>
            </div>
        @endif

        {{-- Acción: el botón es UX; las guardas reales viven server-side. --}}
        @can('inscripcion.finalizar')
            <div class="mt-5 flex flex-wrap items-center gap-3">
                @if ($puedeFinalizar)
                    <button type="button" wire:click="finalizar" wire:loading.attr="disabled"
                        class="rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-secondary">
                        <span wire:loading.remove wire:target="finalizar">Finalizar inscripción</span>
                        <span wire:loading wire:target="finalizar">Finalizando…</span>
                    </button>
                    <span class="self-center text-xs text-brand-muted">
                        Asigna el número de control y cierra en ENROLLED (transacción única).
                    </span>
                @elseif ($enRevisionFinal)
                    <span class="text-xs font-semibold text-red-600">No se puede finalizar: faltan requisitos (los 4 roles siempre se validan en el servidor).</span>
                @else
                    <span class="text-xs font-semibold text-brand-muted">La inscripción debe estar en FINAL_REVIEW para finalizar.</span>
                @endif
            </div>
        @endcan
    </div>
</div>
