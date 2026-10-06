<div>
    <x-ui.card>
        <x-slot:header>
            <p class="text-sm font-semibold text-brand-text">Carta de no adeudo</p>
        </x-slot:header>

        <div class="grid items-end gap-3 md:grid-cols-4">
            <div class="md:col-span-3">
                <x-ui.field label="Alumno" for="cna-alumno" :error="$errors->first('alumnoCarta')">
                    {{-- El id viaja a la acción: `alumnoCarta` es un modelo tipado y no
                         admite el texto que envía `wire:model` (TypeError al enviar). --}}
                    <x-ui.select id="cna-alumno" wire:change="seleccionarAlumnoCarta($event.target.value)">
                        <option value="">Seleccionar alumno</option>
                        @foreach (\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                            <option value="{{ $a->id }}" @selected($alumnoCarta?->getKey() === $a->id)>
                                {{ $a->nombre_completo }} ({{ $a->curp }})
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            </div>

            {{-- El id se interpolaba como `$alumnoCarta`, que en JavaScript es una variable
                 indefinida: la acción recibía null. Con `{{ }}` Livewire resuelve el modelo. --}}
            <x-ui.button icon="document-text"
                wire:click="abrirCartaNoAdeudo({{ $alumnoCarta?->getKey() ?? 'null' }})"
                :disabled="! $alumnoCarta" wire:loading.attr="disabled" wire:target="abrirCartaNoAdeudo">
                <span wire:loading.remove wire:target="abrirCartaNoAdeudo">Generar carta</span>
                <span wire:loading wire:target="abrirCartaNoAdeudo"><x-ui.loading label="Generando…" /></span>
            </x-ui.button>
        </div>

        @if ($alumnoCarta)
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
                // `efectoSobreSaldo` es un método, no una columna: como clave de
                // `sum()` devolvía null (total 0) o rompía en SQL. Ver AjusteFinanciero.
                $totalAjustes = $ajustes->sum(fn ($ajuste) => $ajuste->efectoSobreSaldo());
                $saldo = $totalCargos - $totalPagos - $totalAjustes;
            @endphp

            <div class="mt-5">
                <x-ui.alert :tone="$saldo <= 0 ? 'success' : 'warning'">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-semibold">Saldo exigible: ${{ number_format($saldo, 2) }}</p>
                        <x-ui.badge :tone="$saldo <= 0 ? 'success' : 'warning'">
                            {{ $saldo <= 0 ? 'Sin adeudo' : 'Con adeudo' }}
                        </x-ui.badge>
                    </div>
                </x-ui.alert>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
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
            </div>

            <div class="mt-4">
                <x-ui.card :flush="$conceptos->isNotEmpty()">
                    <x-slot:header>
                        <p class="text-sm font-semibold text-brand-text">Detalle de conceptos</p>
                    </x-slot:header>

                    @if ($conceptos->isEmpty())
                        <x-ui.empty-state icon="receipt" title="Sin conceptos"
                            message="Este alumno no tiene conceptos de pago generados." />
                    @else
                        <div class="overflow-x-auto">
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
                                    @foreach ($conceptos as $c)
                                        @php
                                            $pagado = \App\Models\Pago::where('concepto_pago_id', $c->id)->where('estatus', 'VALIDADO')->sum('monto');
                                            $ajustesC = \App\Models\AjusteFinanciero::where('concepto_pago_id', $c->id)
                                                ->get()
                                                ->sum(fn ($ajuste) => $ajuste->efectoSobreSaldo());
                                            $saldoC = $c->monto_total - $pagado - $ajustesC;
                                        @endphp
                                        <tr wire:key="concepto-cna-{{ $c->id }}">
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
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui.card>
            </div>

            <div class="mt-4 flex justify-end">
                <x-ui.button icon="eye" wire:click="$set('modalCartaNoAdeudoAbierto', true)">
                    Ver carta de no adeudo
                </x-ui.button>
            </div>
        @endif
    </x-ui.card>
</div>
