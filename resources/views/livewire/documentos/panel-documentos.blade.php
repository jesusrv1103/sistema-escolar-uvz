{{-- Panel de documentos del expediente (Fase 10): recepción, reemplazo y
     validación. Sin información médica (RB-18); toda acción se delega al
     servicio server-side (@can solo complementa la UX). --}}
<div class="mx-auto max-w-5xl px-4 py-6">
    {{-- Navegación --}}
    <nav class="mb-4 flex items-center gap-2 text-xs text-brand-muted">
        <a href="{{ route('inscripciones.lista') }}" class="font-medium text-brand-primary hover:text-brand-secondary">Inscripciones</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('inscripciones.ver', $inscripcion) }}" class="font-medium text-brand-primary hover:text-brand-secondary">Inscripción</a>
        <span aria-hidden="true">/</span>
        <span>Documentos</span>
    </nav>

    <div class="rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-brand-text">Documentos de la inscripción</h1>
                <p class="mt-1 text-xs text-brand-muted">
                    Estatus de la inscripción:
                    <span class="rounded-full border border-brand-line bg-brand-surface px-2 py-0.5 font-semibold">{{ $inscripcion->estatus->label() }}</span>
                    · Un registro por tipo: una nueva carga reemplaza el archivo y regresa a Pendiente.
                </p>
            </div>
            <a href="{{ route('inscripciones.ver', $inscripcion) }}"
                class="rounded-md border border-brand-line px-3 py-1.5 text-xs font-medium text-brand-primary hover:bg-brand-surface">
                Volver a la inscripción
            </a>
        </div>

        @if (session()->has('documentos'))
            <p class="mt-4 rounded-md bg-green-50 px-3 py-2 text-xs text-green-700">{{ session('documentos') }}</p>
        @endif

        @error('panel')
            <p class="mt-4 rounded-md bg-red-50 px-3 py-2 text-xs text-red-600">{{ $message }}</p>
        @enderror

        {{-- Lista de tipos --}}
        <ul class="mt-5 divide-y divide-brand-line">
            @forelse ($filas as $fila)
                @php($tipo = $fila['tipo'])
                @php($documento = $fila['documento'])
                <li class="py-4" wire:key="tipo-{{ $tipo?->id ?? 'x-'.$documento->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-brand-text">
                                {{ $tipo?->nombre ?? 'Tipo de documento eliminado' }}
                                @if ($tipo?->obligatorio)
                                    <span class="ml-1 rounded-full border border-brand-primary px-1.5 py-0.5 text-[10px] font-semibold text-brand-primary">Obligatorio</span>
                                @else
                                    <span class="ml-1 rounded-full border border-brand-line px-1.5 py-0.5 text-[10px] font-semibold text-brand-muted">Opcional</span>
                                @endif
                                @if ($fila['historico'])
                                    <span class="ml-1 rounded-full border border-brand-line bg-brand-surface px-1.5 py-0.5 text-[10px] font-semibold text-brand-muted">Histórico</span>
                                @endif
                            </p>

                            @if ($documento === null)
                                <p class="mt-1 text-xs text-brand-muted">Sin cargar.</p>
                            @else
                                <p class="mt-1 text-xs text-brand-muted">
                                    {{ $documento->nombre_original }}
                                    ·
                                    <span class="rounded-full border border-brand-line bg-brand-surface px-1.5 py-0.5 font-semibold">{{ $documento->estatus->label() }}</span>
                                    · cargado {{ $documento->created_at?->format('d/m/Y H:i') }}
                                    @if ($documento->subidoPor !== null)
                                        · por {{ $documento->subidoPor->name }}
                                    @endif
                                    · {{ number_format((int) ceil($documento->tamano_kb), 0) }} KB
                                </p>

                                @if ($documento->estatus === \App\Enums\EstatusDocumento::RECHAZADO && $documento->motivo_rechazo !== null)
                                    <p class="mt-1 text-xs text-red-600">Motivo de rechazo: {{ $documento->motivo_rechazo }}</p>
                                @endif
                                @if ($documento->validadoPor !== null && $documento->validado_at !== null)
                                    <p class="mt-1 text-xs text-brand-muted">
                                        Validado por {{ $documento->validadoPor->name }} el {{ $documento->validado_at->format('d/m/Y H:i') }}
                                    </p>
                                @endif
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            @if ($documento !== null)
                                <a href="{{ route('documentos.archivo', $documento) }}"
                                    class="rounded-md border border-brand-line px-3 py-1.5 font-medium text-brand-primary hover:bg-brand-surface">
                                    Descargar
                                </a>
                            @endif

                            @if ($puedeCargar && ! $fila['historico'] && ! $tipo?->deleted_at)
                                @if ($tipoCarga === $tipo?->id)
                                    <span class="text-[11px] text-brand-muted">Selecciona el archivo abajo</span>
                                @elseif ($documento === null)
                                    <button type="button" wire:click="abrirCarga({{ $tipo->id }})"
                                        class="rounded-md bg-brand-primary px-3 py-1.5 font-semibold text-white hover:bg-brand-secondary">
                                        Subir
                                    </button>
                                @else
                                    <button type="button" wire:click="abrirCarga({{ $tipo->id }})"
                                        class="rounded-md border border-brand-line px-3 py-1.5 font-medium text-brand-primary hover:bg-brand-surface">
                                        Reemplazar
                                    </button>
                                @endif
                            @endif

                            @if ($puedeValidar && $documento !== null && $documento->estatus === \App\Enums\EstatusDocumento::PENDIENTE)
                                <button type="button" wire:click="aceptar({{ $documento->id }})"
                                    class="rounded-md bg-green-700 px-3 py-1.5 font-semibold text-white hover:bg-green-800">
                                    Aceptar
                                </button>
                                @if ($documentoRechazo === $documento->id)
                                    <button type="button" wire:click="cerrarRechazo"
                                        class="rounded-md border border-brand-line px-3 py-1.5 font-medium text-brand-muted hover:bg-brand-surface">
                                        Cancelar
                                    </button>
                                @else
                                    <button type="button" wire:click="abrirRechazo({{ $documento->id }})"
                                        class="rounded-md border border-red-300 px-3 py-1.5 font-medium text-red-600 hover:bg-red-50">
                                        Rechazar
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>

                    {{-- Formulario de carga/reemplazo para este tipo --}}
                    @if ($puedeCargar && $tipoCarga === $tipo?->id)
                        <div class="mt-3 rounded-lg border border-brand-line bg-brand-surface/40 p-3">
                            <label class="block text-xs font-semibold text-brand-text" for="archivo-{{ $tipo->id }}">
                                Archivo (PDF, JPG, JPEG o PNG · máx. 10 MB)
                            </label>
                            <input id="archivo-{{ $tipo->id }}" type="file" wire:model="archivo"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="mt-1 block w-full text-xs text-brand-muted">
                            <div wire:loading wire:target="archivo" class="mt-1 text-[11px] text-brand-muted">Procesando archivo…</div>
                            @error('archivo') <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p> @enderror
                            <div class="mt-2 flex gap-2">
                                <button type="button" wire:click="cargar" wire:loading.attr="disabled"
                                    class="rounded-md bg-brand-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-secondary">
                                    <span wire:loading.remove wire:target="cargar">Guardar</span>
                                    <span wire:loading wire:target="cargar">Guardando…</span>
                                </button>
                                <button type="button" wire:click="cerrarCarga"
                                    class="rounded-md border border-brand-line px-3 py-1.5 text-xs font-medium text-brand-muted hover:bg-brand-surface">
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- Formulario de rechazo para este documento --}}
                    @if ($puedeValidar && $documento !== null && $documentoRechazo === $documento->id)
                        <div class="mt-3 rounded-lg border border-red-200 bg-red-50/50 p-3">
                            <label class="block text-xs font-semibold text-brand-text" for="motivo-{{ $documento->id }}">
                                Motivo de rechazo (5–2000 caracteres)
                            </label>
                            <textarea id="motivo-{{ $documento->id }}" wire:model="motivoRechazo" rows="2"
                                class="mt-1 block w-full rounded-md border border-brand-line px-2 py-1 text-xs"
                                placeholder="Describe qué debe corregirse"></textarea>
                            @error('motivoRechazo') <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p> @enderror
                            <div class="mt-2 flex gap-2">
                                <button type="button" wire:click="rechazar" wire:loading.attr="disabled"
                                    class="rounded-md bg-red-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-800">
                                    <span wire:loading.remove wire:target="rechazar">Rechazar documento</span>
                                    <span wire:loading wire:target="rechazar">Rechazando…</span>
                                </button>
                                <button type="button" wire:click="cerrarRechazo"
                                    class="rounded-md border border-brand-line px-3 py-1.5 text-xs font-medium text-brand-muted hover:bg-brand-surface">
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    @endif
                </li>
            @empty
                <li class="py-6 text-center text-sm text-brand-muted">Sin tipos de documento configurados en el catálogo.</li>
            @endforelse
        </ul>
    </div>
</div>
