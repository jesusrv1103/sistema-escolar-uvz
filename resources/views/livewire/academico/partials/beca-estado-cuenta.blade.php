<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Estado de Cuenta del Alumno</h5>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Alumno</label>
                <select class="form-select" wire:model="alumnoEstadoCuenta" required>
                    <option value="">Seleccionar alumno</option>
                    @foreach(\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                        <option value="{{ $a->id }}">{{ $a->nombre_completo }} ({{ $a->curp }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="button" class="btn btn-primary w-100" wire:click="abrirEstadoCuenta($alumnoEstadoCuenta)" @if(!$alumnoEstadoCuenta) disabled @endif>
                    <i class="bi bi-search me-1"></i> Consultar
                </button>
            </div>
        </div>

        @if($alumnoEstadoCuenta)
            @php
                $service = app(\App\Services\HistorialAcademicoService::class);
                $conceptos = \App\Models\ConceptoPago::where('inscripcion.matriculaPeriodo.alumno_id', $alumnoEstadoCuenta->id)
                    ->with('inscripcion.matriculaPeriodo.alumno')
                    ->orderBy('fecha_vencimiento')
                    ->get();
                $pagos = \App\Models\Pago::where('concepto_pago.inscripcion.matriculaPeriodo.alumno_id', $alumnoEstadoCuenta->id)
                    ->with('conceptoPago')
                    ->orderByDesc('fecha_pago')
                    ->get();
                $ajustes = \App\Models\AjusteFinanciero::where('alumno_id', $alumnoEstadoCuenta->id)
                    ->with('conceptoPago', 'becaAlumno.beca')
                    ->orderByDesc('registrado_at')
                    ->get();

                $totalCargos = $conceptos->sum('monto_total');
                $totalPagos = $pagos->where('estatus', 'VALIDADO')->sum('monto');
                $totalAjustes = $ajustes->sum('efectoSobreSaldo');
                $saldo = $totalCargos - $totalPagos - $totalAjustes;
            @endphp

            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <div class="h4 mb-0">${{ number_format($totalCargos, 2) }}</div>
                            <small>Total Cargos</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <div class="h4 mb-0">${{ number_format($totalPagos, 2) }}</div>
                            <small>Pagos Validados</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <div class="h4 mb-0">${{ number_format($totalAjustes, 2) }}</div>
                            <small>Ajustes/Descuentos</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card {{ $saldo > 0 ? 'bg-danger' : 'bg-success' }} text-white">
                        <div class="card-body text-center">
                            <div class="h4 mb-0">${{ number_format($saldo, 2) }}</div>
                            <small>Saldo Exigible</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Detalle de Cargos</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Concepto</th>
                                    <th>Periodo</th>
                                    <th>Monto</th>
                                    <th>Descuento</th>
                                    <th>Total</th>
                                    <th>Estatus</th>
                                    <th>Pagos</th>
                                    <th>Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($conceptos as $c)
                                    @php
                                        $pagosConcepto = $pagos->where('concepto_pago_id', $c->id)->where('estatus', 'VALIDADO');
                                        $totalPagado = $pagosConcepto->sum('monto');
                                        $ajustesConcepto = $ajustes->where('concepto_pago_id', $c->id)->sum('efectoSobreSaldo');
                                        $saldoConcepto = $c->monto_total - $totalPagado - $ajustesConcepto;
                                    @endphp
                                    <tr>
                                        <td>{{ $c->tipo }} {{ $c->numero ?? '' }}</td>
                                        <td>{{ $c->inscripcion->periodo->clave ?? '—' }}</td>
                                        <td>${{ number_format($c->monto, 2) }}</td>
                                        <td>${{ number_format($c->descuento, 2) }}</td>
                                        <td>${{ number_format($c->monto_total, 2) }}</td>
                                        <td><span class="badge {{ $c->estatus->labelClass() }}">{{ $c->estatus->label() }}</span></td>
                                        <td>${{ number_format($totalPagado, 2) }}</td>
                                        <td class="{{ $saldoConcepto > 0 ? 'text-danger' : 'text-success' }} fw-bold">${{ number_format($saldoConcepto, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Ajustes / Descuentos Aplicados</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Concepto</th>
                                        <th>Monto</th>
                                        <th>Origen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ajustes as $a)
                                        <tr>
                                            <td>{{ $a->registrado_at->format('d/m/Y H:i') }}</td>
                                            <td><span class="badge {{ $a->esDescuentoBeca() ? 'bg-success' : 'bg-danger' }}">{{ $a->tipo->label() }}</span></td>
                                            <td>{{ $a->conceptoPago->tipo ?? '—' }} {{ $a->conceptoPago->numero ?? '' }}</td>
                                            <td class="{{ $a->esDescuentoBeca() ? 'text-success' : 'text-danger' }} fw-bold">${{ number_format($a->monto, 2) }}</td>
                                            <td>{{ $a->becaAlumno?->beca->nombre ?? 'Manual' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>