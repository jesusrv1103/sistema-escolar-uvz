<div>
    <x-ui.page-header title="Documentos" description="Su expediente y los formatos emitidos a su nombre.">
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
        {{-- Documentos del expediente (privados: la descarga valida la policy) --}}
        <div class="mt-5">
            <x-ui.card :flush="$expediente->isNotEmpty()">
                <x-slot:header>
                    <p class="text-sm font-semibold text-brand-text">Documentos del expediente</p>
                </x-slot:header>

                @if ($expediente->isEmpty())
                    <x-ui.empty-state icon="folder" title="Sin documentos"
                        message="Aún no hay documentos cargados en su expediente." />
                @else
                    <ul class="divide-y divide-brand-line">
                        @foreach ($expediente as $documento)
                            <li id="documento-{{ $documento->id }}"
                                class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-brand-text">
                                        {{ $documento->tipoDocumento?->nombre ?? 'Documento' }}
                                        <span class="text-brand-muted">· {{ $documento->nombre_original }}</span>
                                    </p>
                                    <p class="text-xs text-brand-muted">
                                        cargado {{ $documento->created_at?->format('d/m/Y H:i') }}
                                        · {{ number_format((int) ceil($documento->tamano_kb), 0) }} KB
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-ui.badge :tone="match ($documento->estatus->value) {
                                        'ACEPTADO' => 'success',
                                        'RECHAZADO' => 'danger',
                                        default => 'warning',
                                    }">
                                        {{ $documento->estatus->label() }}
                                    </x-ui.badge>
                                    <x-ui.button size="sm" variant="secondary" icon="document-text"
                                        href="{{ route('documentos.archivo', $documento) }}">
                                        Descargar
                                    </x-ui.button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        {{-- Formatos académicos emitidos --}}
        <div class="mt-5">
            <x-ui.card :flush="$generados->isNotEmpty()">
                <x-slot:header>
                    <p class="text-sm font-semibold text-brand-text">Formatos emitidos</p>
                </x-slot:header>

                @if ($generados->isEmpty())
                    <x-ui.empty-state icon="document-text" title="Sin formatos emitidos"
                        message="Cuando Control Escolar emita un formato a su nombre aparecerá aquí." />
                @else
                    <ul class="divide-y divide-brand-line">
                        @foreach ($generados as $generado)
                            <li id="generado-{{ $generado->id }}"
                                class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-brand-text">
                                        {{ $generado->tipo->label() }}
                                    </p>
                                    <p class="text-xs text-brand-muted">
                                        emitido {{ $generado->emitido_at?->format('d/m/Y H:i') ?? '—' }}
                                        @if ($generado->folio)
                                            · folio {{ $generado->folio }}
                                        @endif
                                    </p>
                                </div>
                                <x-ui.button size="sm" variant="secondary" icon="document-text"
                                    href="{{ route('documentos.generados.descargar', $generado) }}">
                                    Descargar PDF
                                </x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    @endif
</div>
