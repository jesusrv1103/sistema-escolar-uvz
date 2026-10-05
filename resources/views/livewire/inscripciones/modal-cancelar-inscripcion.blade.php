<div>
    @if ($puedeCancelar)
        <div class="mt-4">
            <x-ui.card>
                <x-slot:header>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-danger-ink">Cancelar inscripción</p>
                        @if ($cancelable)
                            <x-ui.button variant="danger" icon="no-symbol" wire:click="abrir">
                                Cancelar inscripción
                            </x-ui.button>
                        @endif
                    </div>
                </x-slot:header>

                <p class="text-sm text-brand-muted">
                    La cancelación es <strong class="text-brand-text">definitiva en el MVP</strong>
                    (no se puede reabrir), <strong class="text-brand-text">no implica reembolso</strong> y los
                    <strong class="text-brand-text">pagos ya validados permanecen registrados</strong>
                    como historial.
                </p>

                @unless ($cancelable)
                    <p class="mt-3">
                        <x-ui.badge tone="neutral">No cancelable ({{ $inscripcion->estatus->label() }})</x-ui.badge>
                    </p>
                @endunless
            </x-ui.card>
        </div>

        {{-- Modal: confirmación con motivo obligatorio (solo ADMIN) --}}
        @if ($cancelable)
            <x-ui.modal id="modal-cancelar-inscripcion" size="lg" title="Cancelar inscripción"
                :open="$abierto" close="cerrar">
                <div class="space-y-4">
                    <x-ui.alert tone="danger">
                        Acción <strong>definitiva</strong>: la inscripción pasa a
                        <strong>{{ \App\Enums\InscripcionEstatus::CANCELLED->label() }}</strong> y no se puede reabrir.
                        <strong>No se realizan reembolsos</strong> y los pagos validados permanecen registrados
                        (folio, montos y comprobantes intactos).
                    </x-ui.alert>

                    <x-ui.field label="Motivo de la cancelación (obligatorio)" for="motivo-cancelacion"
                        :error="$errors->first('motivo') ?: $errors->first('cancelacion')">
                        <textarea id="motivo-cancelacion" wire:model="motivo" rows="3" maxlength="2000"
                            class="ui-textarea" placeholder="Describe el motivo de la cancelación"></textarea>
                    </x-ui.field>
                </div>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="cerrar">Cerrar</x-ui.button>
                    <x-ui.button variant="danger" icon="no-symbol" wire:click="cancelar"
                        wire:loading.attr="disabled" wire:target="cancelar">
                        <span wire:loading.remove wire:target="cancelar">Confirmar cancelación</span>
                        <span wire:loading wire:target="cancelar"><x-ui.loading label="Cancelando…" /></span>
                    </x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endif
    @endif
</div>
