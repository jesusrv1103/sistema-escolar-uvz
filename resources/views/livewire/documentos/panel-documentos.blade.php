{{-- Panel de documentos del expediente (Fase 10): recepción, reemplazo y
     validación. Sin información médica (RB-18); toda acción se delega al
     servicio server-side (@can solo complementa la UX). --}}
<div>
    <x-ui.page-header title="Documentos de la inscripción"
        :description="'Una carga por tipo: un archivo nuevo reemplaza el anterior y regresa a Pendiente.'">
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
        <span>Documentos</span>
    </nav>

    @if (session()->has('documentos'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('documentos') }}</x-ui.alert></div>
    @endif

    @error('panel')
        <div class="mt-4"><x-ui.alert tone="danger">{{ $message }}</x-ui.alert></div>
    @enderror

    <div class="mt-5">
        <x-ui.card :flush="count($filas) > 0">
            @forelse ($filas as $fila)
                @php($tipo = $fila['tipo'])
                @php($documento = $fila['documento'])
                <div class="border-b border-brand-line px-5 py-4 last:border-b-0"
                    wire:key="tipo-{{ $tipo?->id ?? 'x-'.$documento->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-brand-text">
                                <span>{{ $tipo?->nombre ?? 'Tipo de documento eliminado' }}</span>
                                <x-ui.badge :tone="$tipo?->obligatorio ? 'primary' : 'neutral'">
                                    {{ $tipo?->obligatorio ? 'Obligatorio' : 'Opcional' }}
                                </x-ui.badge>
                                @if ($fila['historico'])
                                    <x-ui.badge tone="neutral">Histórico</x-ui.badge>
                                @endif
                            </p>

                            @if ($documento === null)
                                <p class="mt-1 text-xs text-brand-muted">Sin cargar.</p>
                            @else
                                <p class="mt-1 text-xs text-brand-muted">
                                    <span class="font-medium text-brand-text">{{ $documento->nombre_original }}</span>
                                    · cargado {{ $documento->created_at?->format('d/m/Y H:i') }}
                                    @if ($documento->subidoPor !== null)
                                        · por {{ $documento->subidoPor->name }}
                                    @endif
                                    · {{ number_format((int) ceil($documento->tamano_kb), 0) }} KB
                                </p>

                                <p class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
                                    <x-ui.badge :tone="match ($documento->estatus->value) {
                                        'ACEPTADO' => 'success',
                                        'RECHAZADO' => 'danger',
                                        'PENDIENTE' => 'warning',
                                        default => 'neutral',
                                    }">
                                        {{ $documento->estatus->label() }}
                                    </x-ui.badge>
                                    @if ($documento->validadoPor !== null && $documento->validado_at !== null)
                                        <span class="text-brand-muted">
                                            Validado por {{ $documento->validadoPor->name }}
                                            el {{ $documento->validado_at->format('d/m/Y H:i') }}
                                        </span>
                                    @endif
                                </p>

                                @if ($documento->estatus === \App\Enums\EstatusDocumento::RECHAZADO && $documento->motivo_rechazo !== null)
                                    <p class="ui-error mt-1">
                                        Motivo de rechazo: {{ $documento->motivo_rechazo }}
                                    </p>
                                @endif
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            @if ($documento !== null)
                                <x-ui.button size="sm" variant="secondary" icon="document-text"
                                    href="{{ route('documentos.archivo', $documento) }}">
                                    Descargar
                                </x-ui.button>
                            @endif

                            @if ($puedeCargar && ! $fila['historico'] && ! $tipo?->deleted_at)
                                @if ($tipoCarga === $tipo?->id)
                                    <span class="text-brand-muted">Selecciona el archivo abajo</span>
                                @elseif ($documento === null)
                                    <x-ui.button size="sm" icon="plus" wire:click="abrirCarga({{ $tipo->id }})">
                                        Subir
                                    </x-ui.button>
                                @else
                                    <x-ui.button size="sm" variant="secondary" icon="pencil"
                                        wire:click="abrirCarga({{ $tipo->id }})">
                                        Reemplazar
                                    </x-ui.button>
                                @endif
                            @endif

                            @if ($puedeValidar && $documento !== null && $documento->estatus === \App\Enums\EstatusDocumento::PENDIENTE)
                                <x-ui.button size="sm" variant="success" icon="check"
                                    wire:click="aceptar({{ $documento->id }})">
                                    Aceptar
                                </x-ui.button>
                                @if ($documentoRechazo === $documento->id)
                                    <x-ui.button size="sm" variant="ghost" wire:click="cerrarRechazo">
                                        Cancelar
                                    </x-ui.button>
                                @else
                                    <x-ui.button size="sm" variant="danger" icon="x-mark"
                                        wire:click="abrirRechazo({{ $documento->id }})">
                                        Rechazar
                                    </x-ui.button>
                                @endif
                            @endif
                        </div>
                    </div>

                    {{-- Formulario de carga/reemplazo para este tipo --}}
                    @if ($puedeCargar && $tipoCarga === $tipo?->id)
                        <div class="mt-3 rounded-lg border border-brand-line bg-brand-surface p-3">
                            <label class="ui-label" for="archivo-{{ $tipo->id }}">
                                Archivo (PDF, JPG, JPEG o PNG · máx. 10 MB)
                            </label>
                            <input id="archivo-{{ $tipo->id }}" type="file" wire:model="archivo"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="mt-1 block w-full text-xs text-brand-muted">
                            <div wire:loading wire:target="archivo" class="mt-1 text-[11px] text-brand-muted">
                                Procesando archivo…
                            </div>
                            @error('archivo')
                                <p class="ui-error mt-1">{{ $message }}</p>
                            @enderror
                            <div class="mt-2 flex gap-2">
                                <x-ui.button size="sm" wire:click="cargar"
                                    wire:loading.attr="disabled" wire:target="cargar">
                                    <span wire:loading.remove wire:target="cargar">Guardar</span>
                                    <span wire:loading wire:target="cargar"><x-ui.loading label="Guardando…" /></span>
                                </x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="cerrarCarga">Cancelar</x-ui.button>
                            </div>
                        </div>
                    @endif

                    {{-- Formulario de rechazo para este documento --}}
                    @if ($puedeValidar && $documento !== null && $documentoRechazo === $documento->id)
                        <div class="mt-3 rounded-lg border border-brand-line bg-brand-surface p-3">
                            <label class="ui-label" for="motivo-{{ $documento->id }}">
                                Motivo de rechazo (5–2000 caracteres)
                            </label>
                            <textarea id="motivo-{{ $documento->id }}" wire:model="motivoRechazo" rows="2"
                                class="ui-textarea mt-1 text-xs"
                                placeholder="Describe qué debe corregirse"></textarea>
                            @error('motivoRechazo')
                                <p class="ui-error mt-1">{{ $message }}</p>
                            @enderror
                            <div class="mt-2 flex gap-2">
                                <x-ui.button size="sm" variant="danger" wire:click="rechazar"
                                    wire:loading.attr="disabled" wire:target="rechazar">
                                    <span wire:loading.remove wire:target="rechazar">Rechazar documento</span>
                                    <span wire:loading wire:target="rechazar"><x-ui.loading label="Rechazando…" /></span>
                                </x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="cerrarRechazo">Cancelar</x-ui.button>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="document-text" title="Sin tipos de documento configurados"
                    message="Da de alta los tipos en el catálogo de documentos para poder cargar archivos." />
            @endforelse
        </x-ui.card>
    </div>
</div>
