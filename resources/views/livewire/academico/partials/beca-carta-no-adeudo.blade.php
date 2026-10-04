<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Carta de No Adeudo</h5>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Alumno</label>
                <select class="form-select" wire:model="alumnoCarta" required>
                    <option value="">Seleccionar alumno</option>
                    @foreach(\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                        <option value="{{ $a->id }}">{{ $a->nombre_completo }} ({{ $a->curp }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="button" class="btn btn-primary w-100" wire:click="abrirCartaNoAdeudo($alumnoCarta)" @if(!$alumnoCarta) disabled @endif>
                    <i class="bi bi-file-earmark-check me-1"></i> Generar Carta
                </button>
            </div>
        </div>

        @if($alumnoCarta)
            @php
                $service = app(\App\Services\HistorialAcademicoService::class);
                $conceptos = \App\Models\ConceptoPago::where('inscripcion.matriculaPeriodo.alumno_id', $alumnoCarta->id)
                    ->with('inscripcion.matriculaPeriodo.alumno')
                    ->get();
                $pagos = \App\Models\Pago::where('concepto_pago.inscripcion.matriculaPeriodo.alumno_id', $alumnoCarta->id)
                    ->where('estatus', 'VALIDADO')
                    ->with('conceptoPago')
                    ->get();
                $ajustes = \App\Models\AjusteFinanciero::where('alumno_id', $alumnoCarta->id)
                    ->get();

                $totalCargos = $conceptos->sum('monto_total');
                $totalPagos = $pagos->sum('monto');
                $totalAjustes = $ajustes->sum('efectoSobreSaldo');
                $saldo = $totalCargos - $totalPagos - $totalAjustes;
            @endphp

            <div class="alert alert-{{ $saldo <= 0 ? 'success' : 'warning' }} mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Saldo Exigible: ${{ number_format($saldo, 2) }}</strong>
                    </div>
                    <span class="badge {{ $saldo <= 0 ? 'bg-success' : 'bg-warning text-dark' }} fs-6">
                        {{ $saldo <= 0 ? 'SIN ADEUDO' : 'CON ADEUDO' }}
                    </span>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Detalle del Saldo</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <div class="h5 mb-0">${{ number_format($totalCargos, 2) }}</div>
                                    <small>Total Cargos</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <div class="h5 mb-0">${{ number_format($totalPagos, 2) }}</div>
                                    <small>Pagos Validados</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <div class="h5 mb-0">${{ number_format($totalAjustes, 2) }}</div>
                                    <small>Ajustes/Descuentos</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="mb-3">Detalle de Conceptos</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Concepto</th>
                                    <th>Vencimiento</th>
                                    <th>Monto</th>
                                    <th>Descuento</th>
                                    <th>Total</th>
                                    <th>Pagado</th>
                                    <th>Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(\App\Models\ConceptoPago::where('inscripcion.matriculaPeriodo.alumno_id', $alumnoCarta->id)->get() as $c)
                                    @php
                                        $pagado = \App\Models\Pago::where('concepto_pago_id', $c->id)->where('estatus', 'VALIDADO')->sum('monto');
                                        $ajustesC = \App\Models\AjusteFinanciero::where('concepto_pago_id', $c->id)->sum('efectoSobreSaldo');
                                        $saldoC = $c->monto_total - $pagado - $ajustesC;
                                    @endphp
                                    <tr class="{{ $saldoC > 0 ? 'table-danger' : 'table-success' }}">
                                        <td>{{ $c->tipo }} {{ $c->numero ?? '' }}</td>
                                        <td>{{ $c->fecha_vencimiento?->format('d/m/Y') }}</td>
                                        <td>${{ number_format($c->monto, 2) }}</td>
                                        <td>${{ number_format($c->descuento, 2) }}</td>
                                        <td>${{ number_format($c->monto_total, 2) }}</td>
                                        <td>${{ number_format($pagado, 2) }}</td>
                                        <td class="{{ $saldoC > 0 ? 'text-danger' : 'text-success' }} fw-bold">${{ number_format($saldoC, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-primary" wire:click="$set('modalCartaNoAdeudoAbierto', true)">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Generar PDF
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>