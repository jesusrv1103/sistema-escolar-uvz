<div>
    @if ($puedeCancelar)
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-red-800">Cancelar inscripción</h3>
                    <p class="mt-1 text-xs text-red-700">
                        La cancelación es <strong>definitiva en el MVP</strong> (no se puede reabrir),
                        <strong>no implica reembolso</strong> y los <strong>pagos ya validados permanecen
                        registrados</strong> como historial.
                    </p>
                </div>

                @if ($cancelable)
                    <button type="button" wire:click="abrir"
                        class="rounded-md border border-red-400 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">
                        Cancelar inscripción
                    </button>
                @else
                    <span class="rounded-full border border-brand-line bg-white px-3 py-1 text-xs font-semibold text-brand-muted">
                        No cancelable ({{ $inscripcion->estatus->label() }})
                    </span>
                @endif
            </div>
        </div>
    @endif

    {{-- Modal: confirmación con motivo obligatorio (solo ADMIN) --}}
    @if ($abierto)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-2xl border border-brand-line bg-white p-6 shadow-lg">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-brand-text">Cancelar inscripción</h3>
                        <p class="mt-1 text-xs text-brand-muted">
                            Acción <strong>definitiva</strong>: la inscripción pasa a <strong>{{ \App\Enums\InscripcionEstatus::CANCELLED->label() }}</strong> y no se
                            puede reabrir. <strong>No se realizan reembolsos</strong> y los pagos validados permanecen
                            registrados (folio, montos y comprobantes intactos).
                        </p>
                    </div>
                    <button type="button" wire:click="cerrar" class="text-brand-muted hover:text-brand-text"
                        aria-label="Cerrar">✕</button>
                </div>

                <div class="mt-4">
                    <label for="motivo-cancelacion" class="block text-xs font-medium text-brand-text">
                        Motivo de la cancelación (obligatorio)
                    </label>
                    <textarea id="motivo-cancelacion" wire:model="motivo" rows="3" maxlength="2000"
                        placeholder="Describe el motivo de la cancelación"
                        class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"></textarea>
                    @error('motivo')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    @error('cancelacion')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    <button type="button" wire:click="cancelar" wire:loading.attr="disabled"
                        class="mt-3 rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        <span wire:loading.remove wire:target="cancelar">Confirmar cancelación</span>
                        <span wire:loading wire:target="cancelar">Cancelando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
