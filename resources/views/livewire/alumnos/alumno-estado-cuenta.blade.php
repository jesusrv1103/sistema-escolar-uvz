<div>
    <x-ui.page-header title="Estado de cuenta" description="Cargos, pagos y saldo de su expediente.">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('alumno.inicio') }}" icon="arrow-left">
                Volver al inicio
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($alumno === null || $estado === null)
        <div class="mt-5">
            <x-ui.card>
                <x-ui.empty-state icon="user" title="Sin expediente vinculado"
                    message="La cuenta no tiene un alumno asociado." />
            </x-ui.card>
        </div>
    @else
        {{-- Mismos indicadores y misma fórmula que la vista administrativa --}}
        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="ui-card p-5">
                <p class="tabular-nums text-2xl font-semibold text-brand-text">
                    ${{ number_format($estado['total_cargos'], 2) }}
                </p>
                <p class="mt-1 text-xs text-brand-muted">Total cargos</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-2xl font-semibold text-success-ink">
                    ${{ number_format($estado['total_pagos'], 2) }}
                </p>
                <p class="mt-1 text-xs text-brand-muted">Pagos validados</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-2xl font-semibold text-brand-primary">
                    ${{ number_format($estado['total_ajustes'], 2) }}
                </p>
                <p class="mt-1 text-xs text-brand-muted">Ajustes / descuentos</p>
            </div>
            <div class="ui-card p-5">
                <p class="tabular-nums text-2xl font-semibold {{ $estado['saldo'] > 0 ? 'text-danger-ink' : 'text-success-ink' }}">
                    ${{ number_format($estado['saldo'], 2) }}
                </p>
                <p class="mt-1 text-xs text-brand-muted">Saldo exigible</p>
            </div>
        </div>

        {{-- Detalle de cargos --}}
        <div class="mt-5">
            <x-ui.card :flush="$estado['conceptos']->isNotEmpty()">
                <x-slot:header>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-brand-text">Detalle de cargos</p>
                        <x-ui.badge :tone="$estado['saldo'] > 0 ? 'danger' : 'success'">
                            {{ $estado['saldo'] > 0 ? 'Con adeudo' : 'Sin adeudo' }}
                        </x-ui.badge>
                    </div>
                </x-slot:header>

                @if ($estado['conceptos']->isEmpty())
                    <x-ui.empty-state icon="receipt" title="Sin cargos"
                        message="No tiene conceptos de pago generados." />
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
                                @foreach ($estado['filas'] as $fila)
                                    @php($concepto = $fila['concepto'])
                                    <tr wire:key="concepto-alumno-{{ $concepto->id }}">
                                        <td class="font-medium text-brand-text">
                                            {{ $concepto->tipo->label() }} {{ $concepto->numero ?? '' }}
                                        </td>
                                        <td class="text-brand-muted">{{ $concepto->inscripcion->periodo->clave ?? '—' }}</td>
                                        <td class="text-right tabular-nums">${{ number_format($concepto->monto, 2) }}</td>
                                        <td class="text-right tabular-nums">${{ number_format($concepto->descuento, 2) }}</td>
                                        <td class="text-right tabular-nums">${{ number_format($concepto->monto_total, 2) }}</td>
                                        <td>
                                            <x-ui.badge :tone="match ($concepto->estatus->value) {
                                                'PAGADO' => 'success',
                                                'CANCELADO' => 'neutral',
                                                default => 'warning',
                                            }">
                                                {{ $concepto->estatus->label() }}
                                            </x-ui.badge>
                                        </td>
                                        <td class="text-right tabular-nums">${{ number_format($fila['pagado'], 2) }}</td>
                                        <td class="text-right tabular-nums font-semibold {{ $fila['saldo'] > 0 ? 'text-danger-ink' : 'text-success-ink' }}">
                                            ${{ number_format($fila['saldo'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>

        {{-- Ajustes / descuentos --}}
        <div class="mt-4">
            <x-ui.card :flush="$estado['ajustes']->isNotEmpty()">
                <x-slot:header>
                    <p class="text-sm font-semibold text-brand-text">Ajustes / descuentos aplicados</p>
                </x-slot:header>

                @if ($estado['ajustes']->isEmpty())
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
                                @foreach ($estado['ajustes'] as $ajuste)
                                    <tr wire:key="ajuste-alumno-{{ $ajuste->id }}">
                                        <td class="whitespace-nowrap">{{ $ajuste->registrado_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <x-ui.badge :tone="$ajuste->esDescuentoBeca() ? 'success' : 'danger'">
                                                {{ $ajuste->tipo->label() }}
                                            </x-ui.badge>
                                        </td>
                                        <td class="text-brand-muted">
                                            {{ $ajuste->conceptoPago?->tipo->label() ?? '—' }} {{ $ajuste->conceptoPago?->numero ?? '' }}
                                        </td>
                                        <td class="text-right tabular-nums font-semibold {{ $ajuste->esDescuentoBeca() ? 'text-success-ink' : 'text-danger-ink' }}">
                                            ${{ number_format($ajuste->monto, 2) }}
                                        </td>
                                        <td class="text-brand-muted">{{ $ajuste->becaAlumno?->beca->nombre ?? 'Manual' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>
    @endif
</div>
