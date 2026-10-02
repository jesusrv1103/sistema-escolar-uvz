<div>
    <div class="mt-4 rounded-xl border border-brand-line bg-brand-surface p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-brand-text">Convenio / promoción</h3>
                <p class="mt-1 text-xs text-brand-muted">
                    El convenio aplica únicamente sobre el concepto de inscripción; las mensualidades no reciben
                    descuento en el MVP. Cuatro ojos: lo aplica Admisiones y lo aprueba Administración.
                </p>
            </div>

            @if ($inscripcion->convenio_id === null)
                @can('convenio.aplicar')
                    <button type="button" wire:click="abrir"
                        class="rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-secondary">
                        Aplicar convenio
                    </button>
                @endcan
            @endif
        </div>

        @if ($inscripcion->convenio_id !== null)
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                @php
                    $estatusConvenio = $inscripcion->convenio_estatus?->value;
                @endphp
                <span @class([
                    'rounded-full border px-3 py-1 font-semibold',
                    'border-amber-300 bg-amber-50 text-amber-800' => $estatusConvenio === 'PENDIENTE_APROBACION',
                    'border-green-300 bg-green-50 text-green-800' => $estatusConvenio === 'APROBADO',
                    'border-red-300 bg-red-50 text-red-800' => $estatusConvenio === 'RECHAZADO',
                ])>{{ $estatusConvenio ?? 'SIN ESTATUS' }}</span>
                <span class="text-brand-text">{{ $inscripcion->convenio?->nombre }}</span>
                <span class="text-brand-muted">
                    ({{ $inscripcion->convenio?->tipo?->value }}
                    {{ $inscripcion->convenio?->tipo?->value === 'PORCENTAJE' ? $inscripcion->convenio?->valor.'%' : '$'.number_format((float) ($inscripcion->convenio?->valor ?? 0), 2) }})
                </span>
                <span class="text-brand-muted">Descuento: ${{ number_format((float) $inscripcion->monto_descuento, 2) }}</span>
                @if ($estatusConvenio === 'PENDIENTE_APROBACION')
                    <span class="text-brand-muted">· Pendiente de aprobación</span>
                @elseif ($estatusConvenio === 'RECHAZADO' && filled($inscripcion->convenio_motivo))
                    <span class="text-brand-muted">· Motivo: {{ \Illuminate\Support\Str::limit($inscripcion->convenio_motivo, 120) }}</span>
                @endif
            </div>
        @endif

        @error('convenio')
            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Panel de aprobación / rechazo (solo ADMINISTRACION o ADMIN) --}}
    @if ($puedeAprobar && $inscripcion->convenio_estatus === \App\Enums\EstatusConvenio::PENDIENTE_APROBACION)
        <div class="mt-3 rounded-xl border border-brand-line bg-white p-4">
            <h4 class="text-sm font-semibold text-brand-text">Resolución del convenio</h4>
            <p class="mt-1 text-xs text-brand-muted">Aprueba (congela el descuento) o rechaza con motivo obligatorio.</p>
            <div class="mt-3 flex flex-wrap items-start gap-3">
                <button type="button" wire:click="aprobar" wire:loading.attr="disabled"
                    class="rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-secondary">
                    <span wire:loading.remove wire:target="aprobar">Aprobar convenio</span>
                    <span wire:loading wire:target="aprobar">Guardando…</span>
                </button>
                <div class="flex-1 min-w-[260px]">
                    <label for="motivo-rechazo" class="block text-xs font-medium text-brand-text">Motivo de rechazo</label>
                    <textarea id="motivo-rechazo" wire:model="motivo" rows="2" maxlength="2000"
                        placeholder="Describe el motivo (mínimo 5 caracteres)"
                        class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"></textarea>
                    @error('motivo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <button type="button" wire:click="rechazar" wire:loading.attr="disabled"
                        class="mt-2 rounded-md border border-red-400 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                        Rechazar convenio
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: convenios activos, no eliminados y vigentes --}}
    @if ($abierto)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-2xl border border-brand-line bg-white p-6 shadow-lg">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-brand-text">Aplicar convenio</h3>
                        <p class="mt-1 text-xs text-brand-muted">
                            Solo convenios activos y vigentes. Quedará en <strong>PENDIENTE_APROBACION</strong>
                            hasta que Administración lo resuelva.
                        </p>
                    </div>
                    <button type="button" wire:click="cerrar" class="text-brand-muted hover:text-brand-text"
                        aria-label="Cerrar">✕</button>
                </div>

                @if ($convenios->isEmpty())
                    <p class="mt-4 text-sm text-brand-muted">No hay convenios activos y vigentes disponibles.</p>
                @else
                    <ul class="mt-4 divide-y divide-brand-line">
                        @foreach ($convenios as $convenio)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <div class="text-sm">
                                    <p class="font-medium text-brand-text">{{ $convenio->nombre }}</p>
                                    <p class="text-xs text-brand-muted">
                                        {{ $convenio->clave }} ·
                                        {{ $convenio->tipo->value === 'PORCENTAJE' ? 'Descuento '.$convenio->valor.'%' : 'Descuento fijo $'.number_format((float) $convenio->valor, 2).' MXN' }} ·
                                        Vigencia:
                                        {{ $convenio->fecha_inicio?->format('d/m/Y') ?? '—' }}
                                        a {{ $convenio->fecha_fin?->format('d/m/Y') ?? '—' }}
                                    </p>
                                </div>
                                <button type="button" wire:click="aplicar({{ $convenio->id }})" wire:loading.attr="disabled"
                                    class="rounded-md bg-brand-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-secondary">
                                    Aplicar
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
</div>
