<div>
    <x-ui.card>
        <x-slot:header>
            <p class="text-sm font-semibold text-brand-text">Estado de cuenta del alumno</p>
        </x-slot:header>

        <div class="grid items-end gap-3 md:grid-cols-4">
            <div class="md:col-span-3">
                <x-ui.field label="Alumno" for="ec-alumno" :error="$errors->first('alumnoEstadoCuenta')">
                    {{-- El id viaja a la acción: `alumnoEstadoCuenta` es un modelo tipado y no
                         admite el texto que envía `wire:model` (TypeError al enviar). --}}
                    <x-ui.select id="ec-alumno" wire:change="seleccionarAlumnoEstadoCuenta($event.target.value)">
                        <option value="">Seleccionar alumno</option>
                        @foreach (\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                            <option value="{{ $a->id }}" @selected($alumnoEstadoCuenta?->getKey() === $a->id)>
                                {{ $a->nombre_completo }} ({{ $a->curp }})
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            </div>

            {{-- El id se interpolaba como `$alumnoEstadoCuenta`, que en JavaScript es una
                 variable indefinida: la acción recibía null. Con `{{ }}` Livewire
                 resuelve el modelo a partir del id. --}}
            <x-ui.button icon="magnifying-glass"
                wire:click="abrirEstadoCuenta({{ $alumnoEstadoCuenta?->getKey() ?? 'null' }})"
                :disabled="! $alumnoEstadoCuenta" wire:loading.attr="disabled" wire:target="abrirEstadoCuenta">
                <span wire:loading.remove wire:target="abrirEstadoCuenta">Consultar</span>
                <span wire:loading wire:target="abrirEstadoCuenta"><x-ui.loading label="Consultando…" /></span>
            </x-ui.button>
        </div>

        @if ($alumnoEstadoCuenta)
            @php
                // Única fuente de la fórmula (2.0-16): el portal del alumno
                // usa exactamente el mismo servicio.
                $ec = app(\App\Services\EstadoCuentaService::class)->resumen($alumnoEstadoCuenta);
                $conceptos = $ec['conceptos'];
                $pagos = $ec['pagos'];
                $ajustes = $ec['ajustes'];
                $totalCargos = $ec['total_cargos'];
                $totalPagos = $ec['total_pagos'];
                $totalAjustes = $ec['total_ajustes'];
                $saldo = $ec['saldo'];
            @endphp

            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="ui-card p-5">
                    <p class="tabular-nums text-2xl font-semibold text-brand-text">
                        ${{ number_format($totalCargos, 2) }}
                    </p>
                    <p class="mt-1 text-xs text-brand-muted">Total cargos</p>
                </div>
                <div class="ui-card p-5">
                    <p class="tabular-nums text-2xl font-semibold text-success-ink">
                        ${{ number_format($totalPagos, 2) }}
                    </p>
                    <p class="mt-1 text-xs text-brand-muted">Pagos validados</p>
                </div>
                <div class="ui-card p-5">
                    <p class="tabular-nums text-2xl font-semibold text-brand-primary">
                        ${{ number_format($totalAjustes, 2) }}
                    </p>
                    <p class="mt-1 text-xs text-brand-muted">Ajustes / descuentos</p>
                </div>
                <div class="ui-card p-5">
                    <p class="tabular-nums text-2xl font-semibold {{ $saldo > 0 ? 'text-danger-ink' : 'text-success-ink' }}">
                        ${{ number_format($saldo, 2) }}
                    </p>
                    <p class="mt-1 text-xs text-brand-muted">Saldo exigible</p>
                </div>
            </div>

            {{-- -------------------------- Detalle de cargos -------------------------- --}}
            <div class="mt-5">
                <x-ui.card :flush="$conceptos->isNotEmpty()">
                    <x-slot:header>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-brand-text">Detalle de cargos</p>
                            <x-ui.badge :tone="$saldo > 0 ? 'danger' : 'success'">
                                {{ $saldo > 0 ? 'Con adeudo' : 'Sin adeudo' }}
                            </x-ui.badge>
                        </div>
                    </x-slot:header>

                    @if ($conceptos->isEmpty())
                        <x-ui.empty-state icon="receipt" title="Sin cargos"
                            message="Este alumno no tiene conceptos de pago generados." />
                    @else
                        <div class="overflow-x-auto">
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
                                    @foreach ($conceptos as $c)
                                        @php
                                            $pagosConcepto = $pagos->where('concepto_pago_id', $c->id)->where('estatus', 'VALIDADO');
                                            $totalPagado = $pagosConcepto->sum('monto');
                                            $ajustesConcepto = $ajustes->where('concepto_pago_id', $c->id)
                                                ->sum(fn ($ajuste) => $ajuste->efectoSobreSaldo());
                                            $saldoConcepto = $c->monto_total - $totalPagado - $ajustesConcepto;
                                        @endphp
                                        <tr wire:key="concepto-ec-{{ $c->id }}">
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
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui.card>
            </div>

            {{-- --------------------- Ajustes / descuentos aplicados -------------------- --}}
            <div class="mt-4">
                <x-ui.card :flush="$ajustes->isNotEmpty()">
                    <x-slot:header>
                        <p class="text-sm font-semibold text-brand-text">Ajustes / descuentos aplicados</p>
                    </x-slot:header>

                    @if ($ajustes->isEmpty())
                        <x-ui.empty-state icon="percent" title="Sin ajustes"
                            message="Aún no se han aplicado descuentos ni ajustes manuales." />
                    @else
                        <div class="overflow-x-auto">
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
                                    @foreach ($ajustes as $a)
                                        <tr wire:key="ajuste-ec-{{ $a->id }}">
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
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui.card>
            </div>
        @endif
    </x-ui.card>
</div>
