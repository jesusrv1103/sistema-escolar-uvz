{{-- Listado de observaciones (Fase 11): APPEND-ONLY — crear, ver y resolver.
     Sin edición del detalle/tipo ni borrado (§4.13/RB-15); solo un FALTANTE
     ABIERTA bloquea la finalización (RB-8). Sin información médica ni datos
     sensibles (RB-18); toda acción se delega al ObservacionService
     server-side (@can en la vista solo complementa la UX). --}}
<div>
    <x-ui.page-header title="Observaciones de la inscripción"
        description="Se registran y se resuelven: el detalle y el tipo quedan como histórico.">
        <x-slot:meta>
            <x-ui.badge tone="primary">Estatus: {{ $inscripcion->estatus->label() }}</x-ui.badge>
        </x-slot:meta>
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-left" href="{{ route('inscripciones.ver', $inscripcion) }}">
                Volver a la inscripción
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <nav class="mt-2 flex flex-wrap items-center gap-2 text-xs text-brand-muted" aria-label="Ruta de navegación">
        <a href="{{ route('inscripciones.lista') }}" class="font-medium text-brand-primary hover:underline">Inscripciones</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('inscripciones.ver', $inscripcion) }}" class="font-medium text-brand-primary hover:underline">Inscripción</a>
        <span aria-hidden="true">/</span>
        <span>Observaciones</span>
    </nav>

    @if (session()->has('observaciones'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('observaciones') }}</x-ui.alert></div>
    @endif

    @error('panel')
        <div class="mt-4"><x-ui.alert tone="danger">{{ $message }}</x-ui.alert></div>
    @enderror

    {{-- Alta de observación (server-side vía ObservacionService) --}}
    @if ($puedeCrear)
        <div class="mt-5">
            <x-ui.card>
                <x-slot:header>
                    <p class="text-sm font-semibold text-brand-text">Nueva observación</p>
                </x-slot:header>

                <form wire:submit="crear" class="space-y-3">
                    <div class="grid gap-3 sm:grid-cols-[220px_1fr]">
                        <x-ui.field label="Tipo" for="obs-tipo" required :error="$errors->first('tipo')">
                            <x-ui.select id="obs-tipo" wire:model="tipo">
                                <option value="">Selecciona…</option>
                                @foreach ($tipoOpciones as $opcion)
                                    <option value="{{ $opcion->value }}">{{ $opcion->label() }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>

                        <x-ui.field label="Detalle (5–2000 caracteres)" for="obs-detalle" required
                            :error="$errors->first('detalle')">
                            <textarea id="obs-detalle" wire:model="detalle" rows="2" class="ui-textarea"
                                placeholder="Describe el faltante u observación operativa"></textarea>
                        </x-ui.field>
                    </div>

                    <div class="flex justify-end border-t border-brand-line pt-3">
                        <x-ui.button type="submit" icon="plus"
                            wire:loading.attr="disabled" wire:target="crear">
                            <span wire:loading.remove wire:target="crear">Registrar observación</span>
                            <span wire:loading wire:target="crear"><x-ui.loading label="Registrando…" /></span>
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    @endif

    {{-- Filtros mínimos: estatus y tipo --}}
    <div class="mt-5">
        <x-ui.filters>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Estatus">
                    <x-ui.select id="filtro-estatus" wire:model.live="filtroEstatus" aria-label="Filtrar por estatus">
                        <option value="">Todas</option>
                        @foreach ($estatusOpciones as $opcion)
                            <option value="{{ $opcion->value }}">{{ $opcion->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Tipo">
                    <x-ui.select id="filtro-tipo" wire:model.live="filtroTipo" aria-label="Filtrar por tipo">
                        <option value="">Todos</option>
                        @foreach ($tipoOpciones as $opcion)
                            <option value="{{ $opcion->value }}">{{ $opcion->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            </div>
        </x-ui.filters>
    </div>

    {{-- Listado --}}
    <div class="mt-4">
        <x-ui.card :flush="count($observaciones) > 0">
            @forelse ($observaciones as $observacion)
                <div class="border-b border-brand-line px-5 py-4 last:border-b-0"
                    wire:key="observacion-{{ $observacion->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-brand-text">
                                <span>{{ $observacion->tipo->label() }}</span>
                                <x-ui.badge :tone="$observacion->estatus === \App\Enums\EstatusObservacion::ABIERTA
                                    ? 'danger' : 'success'">
                                    {{ $observacion->estatus->label() }}
                                </x-ui.badge>
                                @if ($observacion->tipo === \App\Enums\TipoObservacion::FALTANTE
                                    && $observacion->estatus === \App\Enums\EstatusObservacion::ABIERTA)
                                    <x-ui.badge tone="primary">Bloqueante</x-ui.badge>
                                @endif
                            </p>

                            <p class="mt-1.5 whitespace-pre-wrap text-xs text-brand-text">{{ $observacion->detalle }}</p>

                            <p class="mt-1.5 text-xs text-brand-muted">
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
                                <x-ui.button size="sm" variant="success" icon="check"
                                    wire:click="resolver({{ $observacion->id }})"
                                    wire:loading.attr="disabled" wire:target="resolver">
                                    <span wire:loading.remove wire:target="resolver">Resolver</span>
                                    <span wire:loading wire:target="resolver"><x-ui.loading label="Resolviendo…" /></span>
                                </x-ui.button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <x-ui.empty-state icon="clipboard" title="Sin observaciones para mostrar"
                    message="Ajusta los filtros o registra la primera observación con el formulario superior." />
            @endforelse
        </x-ui.card>
    </div>
</div>
