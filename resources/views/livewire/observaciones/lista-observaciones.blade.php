{{-- Listado de observaciones (Fase 11): APPEND-ONLY — crear, ver y resolver.
     Sin edición del detalle/tipo ni borrado (§4.13/RB-15); solo un FALTANTE
     ABIERTA bloquea la finalización (RB-8). Sin información médica ni datos
     sensibles (RB-18); toda acción se delega al ObservacionService
     server-side (@can en la vista solo complementa la UX). --}}
<div class="mx-auto max-w-5xl px-4 py-6">
    {{-- Navegación --}}
    <nav class="mb-4 flex items-center gap-2 text-xs text-brand-muted">
        <a href="{{ route('inscripciones.lista') }}" class="font-medium text-brand-primary hover:text-brand-secondary">Inscripciones</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('inscripciones.ver', $inscripcion) }}" class="font-medium text-brand-primary hover:text-brand-secondary">Inscripción</a>
        <span aria-hidden="true">/</span>
        <span>Observaciones</span>
    </nav>

    <div class="rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-brand-text">Observaciones de la inscripción</h1>
                <p class="mt-1 text-xs text-brand-muted">
                    Estatus de la inscripción:
                    <span class="rounded-full border border-brand-line bg-brand-surface px-2 py-0.5 font-semibold">{{ $inscripcion->estatus->value }}</span>
                    · Se registran y se resuelven: el detalle y el tipo quedan como histórico.
                </p>
            </div>
            <a href="{{ route('inscripciones.ver', $inscripcion) }}"
                class="rounded-md border border-brand-line px-3 py-1.5 text-xs font-medium text-brand-primary hover:bg-brand-surface">
                Volver a la inscripción
            </a>
        </div>

        @if (session()->has('observaciones'))
            <p class="mt-4 rounded-md bg-green-50 px-3 py-2 text-xs text-green-700">{{ session('observaciones') }}</p>
        @endif

        @error('panel')
            <p class="mt-4 rounded-md bg-red-50 px-3 py-2 text-xs text-red-600">{{ $message }}</p>
        @enderror

        {{-- Alta de observación (server-side vía ObservacionService) --}}
        @if ($puedeCrear)
            <div class="mt-5 rounded-lg border border-brand-line bg-brand-surface/40 p-4">
                <h2 class="text-sm font-semibold text-brand-text">Nueva observación</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-[200px_1fr]">
                    <div>
                        <label class="block text-xs font-semibold text-brand-text" for="obs-tipo">Tipo</label>
                        <select id="obs-tipo" wire:model="tipo"
                            class="mt-1 block w-full rounded-md border border-brand-line px-2 py-1.5 text-xs">
                            <option value="">Selecciona…</option>
                            @foreach ($tipoOpciones as $opcion)
                                <option value="{{ $opcion->value }}">{{ $opcion->value }}</option>
                            @endforeach
                        </select>
                        @error('tipo') <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-brand-text" for="obs-detalle">Detalle (5–2000 caracteres)</label>
                        <textarea id="obs-detalle" wire:model="detalle" rows="2"
                            class="mt-1 block w-full rounded-md border border-brand-line px-2 py-1.5 text-xs"
                            placeholder="Describe el faltante u observación operativa"></textarea>
                        @error('detalle') <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="button" wire:click="crear" wire:loading.attr="disabled"
                        class="rounded-md bg-brand-primary px-4 py-2 text-xs font-semibold text-white hover:bg-brand-secondary">
                        <span wire:loading.remove wire:target="crear">Registrar observación</span>
                        <span wire:loading wire:target="crear">Registrando…</span>
                    </button>
                </div>
            </div>
        @endif

        {{-- Filtros mínimos: estatus y tipo --}}
        <div class="mt-5 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold text-brand-text" for="filtro-estatus">Estatus</label>
                <select id="filtro-estatus" wire:model.live="filtroEstatus"
                    class="mt-1 block w-44 rounded-md border border-brand-line px-2 py-1.5 text-xs">
                    <option value="">Todas</option>
                    @foreach ($estatusOpciones as $opcion)
                        <option value="{{ $opcion->value }}">{{ $opcion->value }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-brand-text" for="filtro-tipo">Tipo</label>
                <select id="filtro-tipo" wire:model.live="filtroTipo"
                    class="mt-1 block w-44 rounded-md border border-brand-line px-2 py-1.5 text-xs">
                    <option value="">Todos</option>
                    @foreach ($tipoOpciones as $opcion)
                        <option value="{{ $opcion->value }}">{{ $opcion->value }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Listado --}}
        <ul class="mt-4 divide-y divide-brand-line">
            @forelse ($observaciones as $observacion)
                <li class="py-4" wire:key="observacion-{{ $observacion->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-brand-text">
                                {{ $observacion->tipo->value }}
                                @if ($observacion->estatus === \App\Enums\EstatusObservacion::ABIERTA)
                                    <span class="ml-1 rounded-full border border-red-300 bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-700">ABIERTA</span>
                                @else
                                    <span class="ml-1 rounded-full border border-green-300 bg-green-50 px-2 py-0.5 text-[10px] font-semibold text-green-700">RESUELTA</span>
                                @endif
                                @if ($observacion->tipo === \App\Enums\TipoObservacion::FALTANTE
                                    && $observacion->estatus === \App\Enums\EstatusObservacion::ABIERTA)
                                    <span class="ml-1 rounded-full border border-brand-primary px-1.5 py-0.5 text-[10px] font-semibold text-brand-primary">Bloqueante</span>
                                @endif
                            </p>

                            <p class="mt-1 whitespace-pre-wrap text-xs text-brand-text">{{ $observacion->detalle }}</p>

                            <p class="mt-1 text-xs text-brand-muted">
                                Creada {{ $observacion->created_at?->format('d/m/Y H:i') }}
                                @if ($observacion->creadaPor !== null)
                                    · por {{ $observacion->creadaPor->name }}
                                @endif
                            </p>

                            @if ($observacion->estatus === \App\Enums\EstatusObservacion::RESUELTA)
                                <p class="mt-1 text-xs text-brand-muted">
                                    Resuelta {{ $observacion->resuelta_at?->format('d/m/Y H:i') }}
                                    @if ($observacion->resueltaPor !== null)
                                        · por {{ $observacion->resueltaPor->name }}
                                    @endif
                                </p>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            @if ($puedeResolver && $observacion->estatus === \App\Enums\EstatusObservacion::ABIERTA)
                                <button type="button" wire:click="resolver({{ $observacion->id }})" wire:loading.attr="disabled"
                                    class="rounded-md bg-green-700 px-3 py-1.5 font-semibold text-white hover:bg-green-800">Resolver</button>
                            @endif
                        </div>
                    </div>
                </li>
            @empty
                <li class="py-6 text-center text-sm text-brand-muted">Sin observaciones para mostrar.</li>
            @endforelse
        </ul>
    </div>
</div>
