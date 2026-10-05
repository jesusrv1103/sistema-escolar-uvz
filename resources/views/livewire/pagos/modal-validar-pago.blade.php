<div>
    {{-- Disparador: pagos PENDIENTE de la inscripción (lectura para los 4 roles) --}}
    @if ($pagos->isNotEmpty())
        <div class="mt-4">
            <x-ui.card>
                <x-slot:header>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-brand-text">
                                Pagos por validar ({{ $pagos->count() }})
                            </p>
                            <p class="mt-0.5 text-xs text-brand-muted">
                                Cuatro ojos: captura Admisiones y valida Administración. Validar emite el folio
                                único y liquida el concepto; rechazar exige motivo y permite un nuevo intento.
                            </p>
                        </div>
                        <x-ui.button variant="secondary" icon="receipt" wire:click="abrir">Revisar pagos</x-ui.button>
                    </div>
                </x-slot:header>

                @error('pago')
                    <p class="ui-error">{{ $message }}</p>
                @enderror
            </x-ui.card>
        </div>
    @endif

    {{-- Modal de revisión --}}
    @if ($abierto)
        <x-ui.modal id="modal-validar-pago" size="lg" title="Revisión de pagos"
            :open="true" close="cerrar">
            <p class="text-xs text-brand-muted">
                Datos del alumno, programa y comprobante cargado para decidir.
                Sin información médica.
            </p>

            @error('pago')
                <div class="mt-3"><x-ui.alert tone="danger">{{ $message }}</x-ui.alert></div>
            @enderror

            @if ($pagos->isEmpty())
                <div class="mt-4">
                    <x-ui.empty-state icon="receipt" title="Sin pagos pendientes de revisión"
                        message="En cuanto se registre un pago aparecerá aquí." />
                </div>
            @else
                <ul class="mt-4 max-h-[60vh] divide-y divide-brand-line overflow-y-auto">
                    @foreach ($pagos as $pago)
                        @php
                            $concepto = $pago->conceptoPago;
                            $alumno = $concepto->inscripcion->alumno;
                            $programa = $concepto->inscripcion->programa;
                        @endphp
                        <li class="py-3 text-sm">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-medium text-brand-text">
                                        {{ $alumno?->nombre }} {{ $alumno?->apellidos }}
                                        <span class="text-xs font-normal text-brand-muted">
                                            · {{ $alumno?->numero_control ?? 'sin N.º de control' }}
                                        </span>
                                    </p>
                                    <p class="text-xs text-brand-muted">
                                        {{ $programa?->nombre }} · {{ $concepto->concepto }}
                                        ({{ $concepto->tipo->label() }})
                                    </p>
                                </div>
                                <p class="font-semibold text-brand-text">
                                    ${{ number_format((float) $pago->monto, 2) }}
                                </p>
                            </div>

                            <p class="mt-1 text-xs text-brand-muted">
                                {{ $pago->metodo->label() }}
                                @if (filled($pago->referencia)) · ref. {{ $pago->referencia }} @endif
                                · pago {{ $pago->fecha_pago->format('d/m/Y') }}
                                · capturó {{ $pago->registradoPor?->name ?? '—' }}
                                @if (filled($pago->notas)) · {{ \Illuminate\Support\Str::limit($pago->notas, 100) }} @endif
                            </p>

                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                @if ($pago->comprobante_ruta !== null)
                                    <a href="{{ route('pagos.comprobante', $pago) }}"
                                        class="text-xs font-semibold text-brand-primary hover:underline"
                                        target="_blank" rel="noopener">
                                        Ver comprobante ({{ $pago->comprobante_nombre_original }})
                                    </a>
                                @else
                                    <span class="text-xs text-brand-muted">Sin comprobante cargado</span>
                                @endif

                                @can('pago.validar')
                                    <div class="ml-auto flex items-center gap-2">
                                        <x-ui.button size="sm" icon="check"
                                            wire:click="validar({{ $pago->id }})"
                                            wire:loading.attr="disabled" wire:target="validar({{ $pago->id }})">
                                            <span wire:loading.remove wire:target="validar({{ $pago->id }})">Validar</span>
                                            <span wire:loading wire:target="validar({{ $pago->id }})">
                                                <x-ui.loading label="Validando…" />
                                            </span>
                                        </x-ui.button>
                                        <x-ui.button size="sm" variant="danger" icon="x-mark"
                                            wire:click="seleccionarRechazo({{ $pago->id }})">
                                            Rechazar
                                        </x-ui.button>
                                    </div>
                                @endcan
                            </div>

                            {{-- Motivo de rechazo del pago seleccionado --}}
                            @if ($pagoRechazoId === $pago->id)
                                <div class="mt-3 rounded-lg border border-brand-line bg-brand-surface p-3">
                                    <x-ui.field label="Motivo de rechazo (obligatorio, máx. 2000)"
                                        for="motivo-rechazo-pago" :error="$errors->first('motivo')">
                                        <textarea id="motivo-rechazo-pago" wire:model="motivo" rows="2"
                                            maxlength="2000" class="ui-textarea"
                                            placeholder="Describe el motivo del rechazo"></textarea>
                                    </x-ui.field>
                                    <div class="mt-2 flex gap-2">
                                        <x-ui.button size="sm" variant="danger" wire:click="rechazar"
                                            wire:loading.attr="disabled" wire:target="rechazar">
                                            <span wire:loading.remove wire:target="rechazar">Confirmar rechazo</span>
                                            <span wire:loading wire:target="rechazar">
                                                <x-ui.loading label="Rechazando…" />
                                            </span>
                                        </x-ui.button>
                                        <x-ui.button size="sm" variant="ghost" wire:click="cerrar">Cancelar</x-ui.button>
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.modal>
    @endif
</div>
