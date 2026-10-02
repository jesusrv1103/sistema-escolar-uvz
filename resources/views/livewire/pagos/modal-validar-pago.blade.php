<div>
    {{-- Disparador: pagos PENDIENTE de la inscripción (lectura para los 4 roles) --}}
    @if ($pagos->isNotEmpty())
        <div class="mt-4 rounded-xl border border-brand-line bg-brand-surface p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-brand-text">Pagos por validar ({{ $pagos->count() }})</h3>
                    <p class="mt-1 text-xs text-brand-muted">
                        Cuatro ojos: captura Admisiones y valida Administración. Validar emite el folio
                        único y liquida el concepto; rechazar exige motivo y permite un nuevo intento.
                    </p>
                </div>
                <button type="button" wire:click="abrir"
                    class="rounded-md border border-brand-line bg-white px-4 py-2 text-sm font-semibold text-brand-text hover:bg-brand-surface">
                    Revisar pagos
                </button>
            </div>

            @error('pago')
                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @endif

    {{-- Modal de revisión --}}
    @if ($abierto)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-2xl rounded-2xl border border-brand-line bg-white p-6 shadow-lg">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-brand-text">Revisión de pagos</h3>
                        <p class="mt-1 text-xs text-brand-muted">
                            Datos del alumno, programa y comprobante cargado para decidir.
                            <span class="text-brand-muted">Sin información médica.</span>
                        </p>
                    </div>
                    <button type="button" wire:click="cerrar" class="text-brand-muted hover:text-brand-text"
                        aria-label="Cerrar">✕</button>
                </div>

                @error('pago')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">{{ $message }}</p>
                @enderror

                @if ($pagos->isEmpty())
                    <p class="mt-4 text-sm text-brand-muted">No hay pagos pendientes de revisión.</p>
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
                                    <div>
                                        <p class="font-medium text-brand-text">
                                            {{ $alumno?->nombre }} {{ $alumno?->apellidos }}
                                            <span class="text-xs font-normal text-brand-muted">
                                                · {{ $alumno?->numero_control ?? 'sin N.º de control' }}
                                            </span>
                                        </p>
                                        <p class="text-xs text-brand-muted">
                                            {{ $programa?->nombre }} · {{ $concepto->concepto }}
                                            ({{ $concepto->tipo->value }})
                                        </p>
                                    </div>
                                    <p class="font-semibold text-brand-text">${{ number_format((float) $pago->monto, 2) }}</p>
                                </div>

                                <p class="mt-1 text-xs text-brand-muted">
                                    {{ $pago->metodo->value }}
                                    @if (filled($pago->referencia)) · ref. {{ $pago->referencia }} @endif
                                    · pago {{ $pago->fecha_pago->format('d/m/Y') }}
                                    · capturó {{ $pago->registradoPor?->name ?? '—' }}
                                    @if (filled($pago->notas)) · {{ \Illuminate\Support\Str::limit($pago->notas, 100) }} @endif
                                </p>

                                <div class="mt-2 flex flex-wrap items-center gap-3">
                                    @if ($pago->comprobante_ruta !== null)
                                        <a href="{{ route('pagos.comprobante', $pago) }}"
                                            class="text-xs font-semibold text-brand-primary hover:underline" target="_blank" rel="noopener">
                                            Ver comprobante ({{ $pago->comprobante_nombre_original }})
                                        </a>
                                    @else
                                        <span class="text-xs text-brand-muted">Sin comprobante cargado</span>
                                    @endif

                                    @can('pago.validar')
                                        <div class="ml-auto flex items-center gap-2">
                                            <button type="button" wire:click="validar({{ $pago->id }})" wire:loading.attr="disabled"
                                                class="rounded-md bg-brand-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-secondary disabled:opacity-50">
                                                <span wire:loading.remove wire:target="validar({{ $pago->id }})">Validar</span>
                                                <span wire:loading wire:target="validar({{ $pago->id }})">Validando…</span>
                                            </button>
                                            <button type="button" wire:click="seleccionarRechazo({{ $pago->id }})"
                                                class="rounded-md border border-red-400 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                                Rechazar
                                            </button>
                                        </div>
                                    @endcan
                                </div>

                                {{-- Motivo de rechazo del pago seleccionado --}}
                                @if ($pagoRechazoId === $pago->id)
                                    <div class="mt-3 rounded-md border border-brand-line bg-brand-surface p-3">
                                        <label for="motivo-rechazo-pago" class="block text-xs font-medium text-brand-text">
                                            Motivo de rechazo (obligatorio, máx. 2000)
                                        </label>
                                        <textarea id="motivo-rechazo-pago" wire:model="motivo" rows="2" maxlength="2000"
                                            placeholder="Describe el motivo del rechazo"
                                            class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"></textarea>
                                        @error('motivo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        <div class="mt-2 flex gap-2">
                                            <button type="button" wire:click="rechazar" wire:loading.attr="disabled"
                                                class="rounded-md border border-red-400 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50">
                                                <span wire:loading.remove wire:target="rechazar">Confirmar rechazo</span>
                                                <span wire:loading wire:target="rechazar">Rechazando…</span>
                                            </button>
                                            <button type="button" wire:click="cerrar"
                                                class="rounded-md border border-brand-line px-3 py-1.5 text-xs font-semibold text-brand-text hover:bg-white">
                                                Cancelar
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
</div>
