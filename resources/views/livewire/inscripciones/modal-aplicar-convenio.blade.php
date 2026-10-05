<div>
    <div class="mt-4">
        <x-ui.card>
            <x-slot:header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-brand-text">Convenio / promoción</p>

                    @if ($inscripcion->convenio_id === null)
                        @can('convenio.aplicar')
                            <x-ui.button icon="percent" wire:click="abrir">Aplicar convenio</x-ui.button>
                        @endcan
                    @endif
                </div>
            </x-slot:header>

            <p class="text-xs text-brand-muted">
                El convenio aplica únicamente sobre el concepto de inscripción; las mensualidades no reciben
                descuento en el MVP. Cuatro ojos: lo aplica Admisiones y lo aprueba Administración.
            </p>

            @if ($inscripcion->convenio_id !== null)
                @php
                    $estatusConvenio = $inscripcion->convenio_estatus?->value;
                @endphp
                <p class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <x-ui.badge :tone="match ($estatusConvenio) {
                        'APROBADO' => 'success',
                        'RECHAZADO' => 'danger',
                        'PENDIENTE_APROBACION' => 'warning',
                        default => 'neutral',
                    }">
                        {{ $inscripcion->convenio_estatus?->label() ?? 'Sin estatus' }}
                    </x-ui.badge>
                    <span class="font-medium text-brand-text">{{ $inscripcion->convenio?->nombre }}</span>
                    <span class="text-brand-muted">
                        ({{ $inscripcion->convenio?->tipo?->label() }}
                        {{ $inscripcion->convenio?->tipo?->value === 'PORCENTAJE' ? $inscripcion->convenio?->valor.'%' : '$'.number_format((float) ($inscripcion->convenio?->valor ?? 0), 2) }})
                    </span>
                    <span class="text-brand-muted">
                        Descuento: ${{ number_format((float) $inscripcion->monto_descuento, 2) }}
                    </span>
                    @if ($estatusConvenio === 'PENDIENTE_APROBACION')
                        <span class="text-brand-muted">· Pendiente de aprobación</span>
                    @elseif ($estatusConvenio === 'RECHAZADO' && filled($inscripcion->convenio_motivo))
                        <span class="text-brand-muted">· Motivo: {{ \Illuminate\Support\Str::limit($inscripcion->convenio_motivo, 120) }}</span>
                    @endif
                </p>
            @endif

            @error('convenio')
                <p class="ui-error mt-2">{{ $message }}</p>
            @enderror
        </x-ui.card>
    </div>

    {{-- Panel de aprobación / rechazo (solo ADMINISTRACION o ADMIN) --}}
    @if ($puedeAprobar && $inscripcion->convenio_estatus === \App\Enums\EstatusConvenio::PENDIENTE_APROBACION)
        <div class="mt-3">
            <x-ui.card>
                <x-slot:header>
                    <p class="text-sm font-semibold text-brand-text">Resolución del convenio</p>
                </x-slot:header>

                <p class="text-xs text-brand-muted">
                    Aprueba (congela el descuento) o rechaza con motivo obligatorio.
                </p>

                <div class="mt-3 flex flex-wrap items-start gap-3">
                    <x-ui.button icon="check" wire:click="aprobar"
                        wire:loading.attr="disabled" wire:target="aprobar">
                        <span wire:loading.remove wire:target="aprobar">Aprobar convenio</span>
                        <span wire:loading wire:target="aprobar"><x-ui.loading label="Guardando…" /></span>
                    </x-ui.button>

                    <div class="min-w-[260px] flex-1">
                        <x-ui.field label="Motivo de rechazo" for="motivo-rechazo" :error="$errors->first('motivo')">
                            <textarea id="motivo-rechazo" wire:model="motivo" rows="2" maxlength="2000"
                                class="ui-textarea" placeholder="Describe el motivo (mínimo 5 caracteres)"></textarea>
                        </x-ui.field>
                        <div class="mt-2">
                            <x-ui.button variant="danger" icon="x-mark" wire:click="rechazar"
                                wire:loading.attr="disabled" wire:target="rechazar">
                                <span wire:loading.remove wire:target="rechazar">Rechazar convenio</span>
                                <span wire:loading wire:target="rechazar"><x-ui.loading label="Rechazando…" /></span>
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>
    @endif

    {{-- Modal: convenios activos, no eliminados y vigentes --}}
    @if ($abierto)
        <x-ui.modal id="modal-aplicar-convenio" size="lg" title="Aplicar convenio"
            :open="true" close="cerrar">
            <p class="text-xs text-brand-muted">
                Solo convenios activos y vigentes. Quedará en <strong>Pendiente de aprobación</strong>
                hasta que Administración lo resuelva.
            </p>

            @if ($convenios->isEmpty())
                <div class="mt-4">
                    <x-ui.empty-state icon="percent" title="Sin convenios disponibles"
                        message="No hay convenios activos y vigentes disponibles." />
                </div>
            @else
                <ul class="mt-4 divide-y divide-brand-line">
                    @foreach ($convenios as $convenio)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div class="min-w-0 text-sm">
                                <p class="font-medium text-brand-text">{{ $convenio->nombre }}</p>
                                <p class="text-xs text-brand-muted">
                                    {{ $convenio->clave }} ·
                                    {{ $convenio->tipo->value === 'PORCENTAJE' ? 'Descuento '.$convenio->valor.'%' : 'Descuento fijo $'.number_format((float) $convenio->valor, 2).' MXN' }} ·
                                    Vigencia:
                                    {{ $convenio->fecha_inicio?->format('d/m/Y') ?? '—' }}
                                    a {{ $convenio->fecha_fin?->format('d/m/Y') ?? '—' }}
                                </p>
                            </div>
                            <x-ui.button size="sm" icon="percent" wire:click="aplicar({{ $convenio->id }})"
                                wire:loading.attr="disabled" wire:target="aplicar">
                                Aplicar
                            </x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.modal>
    @endif
</div>
