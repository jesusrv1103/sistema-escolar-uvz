{{-- Panel operativo mínimo (Fase 14, FC-9 / RB-20): exactamente los
     4 contadores aprobados, cada uno desde DashboardService; tarjetas
     simples y responsive, sin gráficas ni estadísticas inventadas. --}}
<div class="rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-brand-text">Panel operativo</h2>
            <p class="mt-1 text-xs text-brand-muted">
                Indicadores del flujo de admisión e inscripción (FC-9 / RB-20).
            </p>
        </div>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {{-- 1. Inscripciones pendientes (estatus distinto de ENROLLED/CANCELLED) --}}
        <div class="rounded-xl border border-brand-line bg-brand-surface p-4">
            <p class="text-xs font-medium text-brand-muted">Inscripciones pendientes</p>
            <p class="mt-1 text-2xl font-semibold text-brand-text">{{ $contadores['inscripciones_pendientes'] }}</p>
            <p class="mt-1 text-[11px] text-brand-muted">Estatus distinto de ENROLLED y CANCELLED.</p>
            <a href="{{ route('inscripciones.lista') }}"
                class="mt-2 inline-block text-xs font-semibold text-brand-primary hover:underline">
                Ver listado →
            </a>
        </div>

        {{-- 2. Pagos por validar (solo estatus PENDIENTE) --}}
        <div class="rounded-xl border border-brand-line bg-brand-surface p-4">
            <p class="text-xs font-medium text-brand-muted">Pagos por validar</p>
            <p class="mt-1 text-2xl font-semibold text-brand-text">{{ $contadores['pagos_por_validar'] }}</p>
            <p class="mt-1 text-[11px] text-brand-muted">Pagos con estatus PENDIENTE (cuatro ojos).</p>
        </div>

        {{-- 3. Documentos pendientes (cargados y esperando validación) --}}
        <div class="rounded-xl border border-brand-line bg-brand-surface p-4">
            <p class="text-xs font-medium text-brand-muted">Documentos pendientes</p>
            <p class="mt-1 text-2xl font-semibold text-brand-text">{{ $contadores['documentos_pendientes'] }}</p>
            <p class="mt-1 text-[11px] text-brand-muted">Documentos cargados con estatus PENDIENTE.</p>
        </div>

        {{-- 4. Inscripciones completadas (solo estatus ENROLLED) --}}
        <div class="rounded-xl border border-brand-line bg-brand-surface p-4">
            <p class="text-xs font-medium text-brand-muted">Inscripciones completadas</p>
            <p class="mt-1 text-2xl font-semibold text-brand-text">{{ $contadores['inscripciones_completadas'] }}</p>
            <p class="mt-1 text-[11px] text-brand-muted">Inscripciones con estatus ENROLLED.</p>
            <a href="{{ route('inscripciones.lista', ['filtroEstatus' => 'ENROLLED']) }}"
                class="mt-2 inline-block text-xs font-semibold text-brand-primary hover:underline">
                Ver listado →
            </a>
        </div>
    </div>
</div>
