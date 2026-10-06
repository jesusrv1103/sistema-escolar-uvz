@if ($modalEstadoCuentaAbierto && $alumnoEstadoCuenta)
    <x-ui.modal id="modal-estado-cuenta" size="lg"
        :title="'Estado de cuenta — ' . $alumnoEstadoCuenta->nombre_completo"
        :open="true" close="$set('modalEstadoCuentaAbierto', false)">
        @php
            $conceptos = \App\Models\ConceptoPago::query()
                ->whereHas('inscripcion', fn ($q) => $q->where('alumno_id', $alumnoEstadoCuenta->id))
                ->with('inscripcion.alumno')
                ->orderBy('fecha_vencimiento')
                ->get();
            $pagos = \App\Models\Pago::query()
                ->whereHas('conceptoPago.inscripcion', fn ($q) => $q->where('alumno_id', $alumnoEstadoCuenta->id))
                ->with('conceptoPago.inscripcion')
                ->orderByDesc('fecha_pago')
                ->get();
            $ajustes = \App\Models\AjusteFinanciero::where('alumno_id', $alumnoEstadoCuenta->id)
                ->with('conceptoPago', 'becaAlumno.beca')
                ->orderByDesc('registrado_at')
                ->get();

            $totalCargos = $conceptos->sum('monto_total');
            $totalPagos = $pagos->where('estatus', 'VALIDADO')->sum('monto');
            // `efectoSobreSaldo` es un método, no una columna: como clave de `sum()`
            // devolvía null y el total de ajustes salía siempre 0 (ver AjusteFinanciero).
            $totalAjustes = $ajustes->sum(fn ($ajuste) => $ajuste->efectoSobreSaldo());
            $saldo = $totalCargos - $totalPagos - $totalAjustes;
        @endphp

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="ui-card p-5">
                <p class="tabular-nums text-2xl font-semibold text-brand-text">${{ number_format($totalCargos, 2) }}</p>
                <p class="mt-1 text-xs text-brand-muted">Total cargos</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-2xl font-semibold text-success-ink">${{ number_format($totalPagos, 2) }}</p>
                <p class="mt-1 text-xs text-brand-muted">Pagos validados</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-2xl font-semibold text-brand-primary">${{ number_format($totalAjustes, 2) }}</p>
                <p class="mt-1 text-xs text-brand-muted">Ajustes / descuentos</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-2xl font-semibold {{ $saldo > 0 ? 'text-danger-ink' : 'text-success-ink' }}">
                    ${{ number_format($saldo, 2) }}
                </p>
                <p class="mt-1 text-xs text-brand-muted">Saldo exigible</p>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th scope="col">Concepto</th>
                        <th scope="col">Periodo</th>
                        <th scope="col" class="text-right">Monto</th>
                        <th scope="col" class="text-right">Descuento</th>
                        <th scope="col" class="text-right">Total</th>
                        <th scope="col">Estatus</th>
                        <th scope="col" class="text-right">Pagos</th>
                        <th scope="col" class="text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($conceptos as $c)
                        @php
                            $pagosConcepto = $pagos->where('concepto_pago_id', $c->id)->where('estatus', 'VALIDADO');
                            $totalPagado = $pagosConcepto->sum('monto');
                            $ajustesConcepto = $ajustes->where('concepto_pago_id', $c->id)
                                ->sum(fn ($ajuste) => $ajuste->efectoSobreSaldo());
                            $saldoConcepto = $c->monto_total - $totalPagado - $ajustesConcepto;
                        @endphp
                        <tr wire:key="ec-modal-concepto-{{ $c->id }}">
                            <td class="font-medium text-brand-text">{{ $c->tipo->label() }} {{ $c->numero ?? '' }}</td>
                            <td class="text-brand-muted">{{ $c->inscripcion->periodo->clave ?? '—' }}</td>
                            <td class="text-right tabular-nums">${{ number_format($c->monto, 2) }}</td>
                            <td class="text-right tabular-nums">${{ number_format($c->descuento, 2) }}</td>
                            <td class="text-right tabular-nums">${{ number_format($c->monto_total, 2) }}</td>
                            <td>
                                <x-ui.badge :tone="match ($c->estatus->value) {
                                    'PAGADO' => 'success',
                                    'CANCELADO' => 'neutral',
                                    default => 'warning',
                                }">
                                    {{ $c->estatus->label() }}
                                </x-ui.badge>
                            </td>
                            <td class="text-right tabular-nums">${{ number_format($totalPagado, 2) }}</td>
                            <td class="text-right tabular-nums font-semibold {{ $saldoConcepto > 0 ? 'text-danger-ink' : 'text-success-ink' }}">
                                ${{ number_format($saldoConcepto, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-brand-muted">Sin conceptos de pago.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-brand-muted">
            Ajustes / descuentos aplicados
        </p>

        <div class="mt-2 overflow-x-auto">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Concepto</th>
                        <th scope="col" class="text-right">Monto</th>
                        <th scope="col">Origen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ajustes as $a)
                        <tr wire:key="ec-modal-ajuste-{{ $a->id }}">
                            <td class="whitespace-nowrap">{{ $a->registrado_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <x-ui.badge :tone="$a->esDescuentoBeca() ? 'success' : 'danger'">
                                    {{ $a->tipo->label() }}
                                </x-ui.badge>
                            </td>
                            <td class="text-brand-muted">
                                {{ $a->conceptoPago?->tipo->label() ?? '—' }} {{ $a->conceptoPago?->numero ?? '' }}
                            </td>
                            <td class="text-right tabular-nums font-semibold {{ $a->esDescuentoBeca() ? 'text-success-ink' : 'text-danger-ink' }}">
                                ${{ number_format($a->monto, 2) }}
                            </td>
                            <td class="text-brand-muted">{{ $a->becaAlumno?->beca->nombre ?? 'Manual' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-brand-muted">Sin ajustes aplicados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <x-ui.button variant="secondary" wire:click="$set('modalEstadoCuentaAbierto', false)">Cerrar</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
@endif
