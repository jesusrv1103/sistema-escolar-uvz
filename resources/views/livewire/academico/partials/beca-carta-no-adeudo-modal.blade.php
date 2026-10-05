@if ($modalCartaNoAdeudoAbierto && $alumnoCarta)
    <x-ui.modal id="modal-carta-no-adeudo" size="lg"
        :title="'Carta de no adeudo — ' . $alumnoCarta->nombre_completo"
        :open="true" close="$set('modalCartaNoAdeudoAbierto', false)">
        @php
            $conceptos = \App\Models\ConceptoPago::query()
                ->whereHas('inscripcion', fn ($q) => $q->where('alumno_id', $alumnoCarta->id))
                ->with('inscripcion.alumno')
                ->get();
            $pagos = \App\Models\Pago::query()
                ->whereHas('conceptoPago.inscripcion', fn ($q) => $q->where('alumno_id', $alumnoCarta->id))
                ->where('estatus', 'VALIDADO')
                ->with('conceptoPago.inscripcion')
                ->get();
            $ajustes = \App\Models\AjusteFinanciero::where('alumno_id', $alumnoCarta->id)
                ->get();

            $totalCargos = $conceptos->sum('monto_total');
            $totalPagos = $pagos->sum('monto');
            $totalAjustes = $ajustes->sum('efectoSobreSaldo');
            $saldo = $totalCargos - $totalPagos - $totalAjustes;
        @endphp

        <x-ui.alert :tone="$saldo <= 0 ? 'success' : 'warning'">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm font-semibold">Saldo exigible: ${{ number_format($saldo, 2) }}</p>
                <x-ui.badge :tone="$saldo <= 0 ? 'success' : 'warning'">
                    {{ $saldo <= 0 ? 'Sin adeudo' : 'Con adeudo' }}
                </x-ui.badge>
            </div>
        </x-ui.alert>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div class="ui-card p-5">
                <p class="tabular-nums text-xl font-semibold text-brand-text">${{ number_format($totalCargos, 2) }}</p>
                <p class="mt-1 text-xs text-brand-muted">Total cargos</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-xl font-semibold text-success-ink">${{ number_format($totalPagos, 2) }}</p>
                <p class="mt-1 text-xs text-brand-muted">Pagos validados</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-xl font-semibold text-brand-primary">${{ number_format($totalAjustes, 2) }}</p>
                <p class="mt-1 text-xs text-brand-muted">Ajustes / descuentos</p>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th scope="col">Concepto</th>
                        <th scope="col">Vencimiento</th>
                        <th scope="col" class="text-right">Monto</th>
                        <th scope="col" class="text-right">Descuento</th>
                        <th scope="col" class="text-right">Total</th>
                        <th scope="col" class="text-right">Pagado</th>
                        <th scope="col" class="text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($conceptos as $c)
                        @php
                            $pagado = \App\Models\Pago::where('concepto_pago_id', $c->id)->where('estatus', 'VALIDADO')->sum('monto');
                            $ajustesC = \App\Models\AjusteFinanciero::where('concepto_pago_id', $c->id)->sum('efectoSobreSaldo');
                            $saldoC = $c->monto_total - $pagado - $ajustesC;
                        @endphp
                        <tr wire:key="cna-modal-concepto-{{ $c->id }}">
                            <td class="font-medium text-brand-text">{{ $c->tipo->label() }} {{ $c->numero ?? '' }}</td>
                            <td class="whitespace-nowrap text-brand-muted">{{ $c->fecha_vencimiento?->format('d/m/Y') }}</td>
                            <td class="text-right tabular-nums">${{ number_format($c->monto, 2) }}</td>
                            <td class="text-right tabular-nums">${{ number_format($c->descuento, 2) }}</td>
                            <td class="text-right tabular-nums">${{ number_format($c->monto_total, 2) }}</td>
                            <td class="text-right tabular-nums">${{ number_format($pagado, 2) }}</td>
                            <td class="text-right tabular-nums font-semibold {{ $saldoC > 0 ? 'text-danger-ink' : 'text-success-ink' }}">
                                ${{ number_format($saldoC, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-brand-muted">Sin conceptos de pago.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <p class="mr-auto text-xs text-brand-muted">
                Documento de consulta · el saldo se recalcula al momento de la consulta.
            </p>
            <x-ui.button variant="secondary" wire:click="$set('modalCartaNoAdeudoAbierto', false)">Cerrar</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
@endif
