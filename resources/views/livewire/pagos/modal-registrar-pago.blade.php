<div>
    {{-- Disparador (solo roles con `pago.registrar` y con conceptos PENDIENTES) --}}
    @if ($puedeRegistrar && $conceptos->isNotEmpty())
        <div class="mt-4 rounded-xl border border-brand-line bg-brand-surface p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-brand-text">Registrar pago</h3>
                    <p class="mt-1 text-xs text-brand-muted">
                        Un pago completo por concepto (sin parciales ni anticipos). Queda
                        <strong>PENDIENTE</strong> hasta que Administración lo valide; el comprobante
                        (PDF/JPG/PNG, máx. 10 MB) se guarda en archivo privado.
                    </p>
                </div>
                <button type="button" wire:click="abrir"
                    class="rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-secondary">
                    Registrar pago
                </button>
            </div>

            @error('pago')
                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @endif

    {{-- Modal de captura --}}
    @if ($abierto)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-2xl border border-brand-line bg-white p-6 shadow-lg">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-brand-text">Registrar pago</h3>
                        <p class="mt-1 text-xs text-brand-muted">
                            El monto es el total completo del concepto seleccionado: no se admite
                            dividirlo ni pagarlo parcialmente.
                        </p>
                    </div>
                    <button type="button" wire:click="cerrar" class="text-brand-muted hover:text-brand-text"
                        aria-label="Cerrar">✕</button>
                </div>

                @error('pago')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">{{ $message }}</p>
                @enderror

                <div class="mt-4 space-y-3">
                    <div>
                        <label for="concepto-pago" class="block text-xs font-medium text-brand-text">Concepto (PENDIENTE)</label>
                        <select id="concepto-pago" wire:model="conceptoId"
                            class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                            <option value="">— Selecciona un concepto —</option>
                            @foreach ($conceptos as $concepto)
                                <option value="{{ $concepto->id }}">
                                    {{ $concepto->concepto }} · ${{ number_format((float) $concepto->monto_total, 2) }}
                                    · vence {{ $concepto->fecha_vencimiento->format('d/m/Y') }}
                                </option>
                            @endforeach
                        </select>
                        @error('concepto') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="fecha-pago" class="block text-xs font-medium text-brand-text">Fecha de pago</label>
                            <input id="fecha-pago" type="date" wire:model="fecha_pago" max="{{ now()->toDateString() }}"
                                class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                            @error('fecha_pago') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="metodo-pago" class="block text-xs font-medium text-brand-text">Método</label>
                            <select id="metodo-pago" wire:model="metodo"
                                class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                                <option value="">— Selecciona —</option>
                                @foreach ($metodos as $metodo)
                                    <option value="{{ $metodo->value }}">{{ $metodo->value }}</option>
                                @endforeach
                            </select>
                            @error('metodo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="referencia-pago" class="block text-xs font-medium text-brand-text">
                            Referencia de operación
                            <span class="font-normal text-brand-muted">(obligatoria para TRANSFERENCIA y TARJETA)</span>
                        </label>
                        <input id="referencia-pago" type="text" wire:model="referencia" maxlength="100"
                            placeholder="Nº de operación o transferencia"
                            class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                        @error('referencia') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="notas-pago" class="block text-xs font-medium text-brand-text">Notas (opcional)</label>
                        <textarea id="notas-pago" wire:model="notas" rows="2" maxlength="5000"
                            class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"></textarea>
                        @error('notas') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="comprobante-pago" class="block text-xs font-medium text-brand-text">
                            Comprobante (opcional · PDF, JPG, JPEG o PNG · máx. 10 MB)
                        </label>
                        <input id="comprobante-pago" type="file" wire:model="comprobante" accept=".pdf,.jpg,.jpeg,.png"
                            class="mt-1 block w-full text-xs text-brand-muted file:mr-3 file:rounded-md file:border-0 file:bg-brand-surface file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-text">
                        <div wire:loading wire:target="comprobante" class="mt-1 text-xs text-brand-muted">Subiendo archivo…</div>
                        @error('comprobante') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-5 flex items-center justify-end gap-3">
                    <button type="button" wire:click="cerrar"
                        class="rounded-md border border-brand-line px-4 py-2 text-sm font-medium text-brand-text hover:bg-brand-surface">
                        Cancelar
                    </button>
                    <button type="button" wire:click="registrar" wire:loading.attr="disabled"
                        class="rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-secondary disabled:opacity-50">
                        <span wire:loading.remove wire:target="registrar">Registrar pago</span>
                        <span wire:loading wire:target="registrar">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
