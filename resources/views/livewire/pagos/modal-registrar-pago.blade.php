<div>
    {{-- Disparador (solo roles con `pago.registrar` y con conceptos PENDIENTES) --}}
    @if ($puedeRegistrar && $conceptos->isNotEmpty())
        <div class="mt-4">
            <x-ui.card>
                <x-slot:header>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-brand-text">Registrar pago</p>
                        <x-ui.button icon="banknotes" wire:click="abrir">Registrar pago</x-ui.button>
                    </div>
                </x-slot:header>

                <p class="text-xs text-brand-muted">
                    Un pago completo por concepto (sin parciales ni anticipos). Queda
                    <strong class="text-brand-text">Pendiente</strong> hasta que Administración lo valide;
                    el comprobante (PDF/JPG/PNG, máx. 10 MB) se guarda en archivo privado.
                </p>

                @error('pago')
                    <p class="ui-error mt-2">{{ $message }}</p>
                @enderror
            </x-ui.card>
        </div>
    @endif

    {{-- Modal de captura --}}
    @if ($abierto)
        <x-ui.modal id="modal-registrar-pago" size="lg" title="Registrar pago"
            :open="true" close="cerrar">
            <p class="text-xs text-brand-muted">
                El monto es el total completo del concepto seleccionado: no se admite
                dividirlo ni pagarlo parcialmente.
            </p>

            @error('pago')
                <div class="mt-3"><x-ui.alert tone="danger">{{ $message }}</x-ui.alert></div>
            @enderror

            <div class="mt-4 space-y-3">
                <x-ui.field label="Concepto" for="concepto-pago" required
                    :error="$errors->first('concepto')"
                    hint="Concepto en estatus: {{ \App\Enums\EstatusConceptoPago::PENDIENTE->label() }}.">
                    <x-ui.select id="concepto-pago" wire:model="conceptoId">
                        <option value="">— Selecciona un concepto —</option>
                        @foreach ($conceptos as $concepto)
                            <option value="{{ $concepto->id }}">
                                {{ $concepto->concepto }} · ${{ number_format((float) $concepto->monto_total, 2) }}
                                · vence {{ $concepto->fecha_vencimiento->format('d/m/Y') }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.field label="Fecha de pago" for="fecha-pago" required :error="$errors->first('fecha_pago')">
                        <x-ui.input id="fecha-pago" type="date" wire:model="fecha_pago"
                            max="{{ now()->toDateString() }}" />
                    </x-ui.field>

                    <x-ui.field label="Método" for="metodo-pago" required :error="$errors->first('metodo')">
                        <x-ui.select id="metodo-pago" wire:model="metodo">
                            <option value="">— Selecciona —</option>
                            @foreach ($metodos as $metodo)
                                <option value="{{ $metodo->value }}">{{ $metodo->label() }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>

                <x-ui.field label="Referencia de operación" for="referencia-pago"
                    hint="Obligatoria para Transferencia y Tarjeta." :error="$errors->first('referencia')">
                    <x-ui.input id="referencia-pago" type="text" wire:model="referencia" maxlength="100"
                        placeholder="Nº de operación o transferencia" />
                </x-ui.field>

                <x-ui.field label="Notas (opcional)" for="notas-pago" :error="$errors->first('notas')">
                    <textarea id="notas-pago" wire:model="notas" rows="2" maxlength="5000"
                        class="ui-textarea"></textarea>
                </x-ui.field>

                <x-ui.field label="Comprobante (opcional · PDF, JPG, JPEG o PNG · máx. 10 MB)"
                    for="comprobante-pago" :error="$errors->first('comprobante')">
                    <input id="comprobante-pago" type="file" wire:model="comprobante"
                        accept=".pdf,.jpg,.jpeg,.png"
                        class="block w-full text-xs text-brand-muted file:mr-3 file:rounded-md file:border-0 file:bg-brand-surface file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-text">
                    <div wire:loading wire:target="comprobante" class="mt-1 text-xs text-brand-muted">
                        Subiendo archivo…
                    </div>
                </x-ui.field>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="cerrar">Cancelar</x-ui.button>
                <x-ui.button icon="check" wire:click="registrar"
                    wire:loading.attr="disabled" wire:target="registrar">
                    <span wire:loading.remove wire:target="registrar">Registrar pago</span>
                    <span wire:loading wire:target="registrar"><x-ui.loading label="Guardando…" /></span>
                </x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
