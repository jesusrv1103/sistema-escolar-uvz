<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">Historial Académico</h1>
    </div>

    <!-- Búsqueda de alumno -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Buscar alumno</label>
                    <div class="input-group">
                        <input type="text" class="form-control" wire:model.debounce.300ms="busqueda" placeholder="Nombre, apellidos, CURP, número de control...">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filtrar por periodo</label>
                    <select class="form-select" wire:model="filtroPeriodo">
                        <option value="">Todos los periodos</option>
                        @foreach($periodos as $p)
                            <option value="{{ $p->id }}">{{ $p->clave }} - {{ $p->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Listado de alumnos -->
    @if(!$alumno)
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Seleccione un alumno</h5>
            </div>
            <div class="card-body p-0">
                @if($alumnos->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-people display-4"></i>
                        <p class="mt-2">No hay alumnos registrados{{ $busqueda ? ' con ese criterio' : '' }}.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Número de Control</th>
                                    <th>Alumno</th>
                                    <th>CURP</th>
                                    <th>Programa</th>
                                    <th>Periodo Activo</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($alumnos as $a)
                                    <tr wire:click="alumnoSeleccionado({{ $a->id }})" style="cursor: pointer;">
                                        <td>{{ $a->numero_control ?? '<span class="text-muted">Por asignar</span>' }}</td>
                                        <td>{{ $a->nombre_completo }}</td>
                                        <td>{{ $a->curp }}</td>
                                        <td>{{ $a->matriculasPeriodo->first()?->programa->clave ?? '—' }}</td>
                                        <td>{{ $a->matriculasPeriodo->first()?->periodo->clave ?? '—' }}</td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-primary" wire:click.prevent="alumnoSeleccionado({{ $a->id }})">
                                                <i class="bi bi-clipboard-data me-1"></i> Ver historial
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        {{ $alumnos->links() }}
                    </div>
                @endif
            </div>
        </div>
    @else
        <!-- Resumen académico -->
        @php
            $service = app(\App\Services\HistorialAcademicoService::class);
            $resumen = $service->resumenAcademico($alumno);
            $tira = $service->tiraMaterias($alumno);
        @endphp

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <div class="display-4">{{ $resumen['promedio'] ?? '—' }}</div>
                        <div class="text-white-50">Promedio Ponderado</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <div class="display-4">{{ $resumen['porcentaje_avance'] }}%</div>
                        <div class="text-white-50">Avance Académico</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <div class="display-4">{{ $resumen['creditos_aprobados'] }} / {{ $resumen['creditos_totales'] }}</div>
                        <div class="text-white-50">Créditos Aprobados / Totales</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <div class="display-4">{{ $resumen['creditos_pendientes'] }}</div>
                        <div class="text-white-50">Créditos Pendientes</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Datos del alumno -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    {{ $alumno->nombre_completo }}
                    @if($alumno->numero_control)
                        <span class="text-muted fw-normal fs-6 ms-2">{{ $alumno->numero_control }}</span>
                    @endif
                </h5>
                <div class="btn-group btn-group-sm">
                    <a href="{{ route('documentos.generados.descargar', ['tipo' => 'kardex', 'alumno' => $alumno->id]) }}" class="btn btn-outline-primary" target="_blank">
                        <i class="bi bi-file-earmark-text me-1"></i> Kardex PDF
                    </a>
                    <a href="{{ route('documentos.generados.descargar', ['tipo' => 'boleta', 'alumno' => $alumno->id]) }}" class="btn btn-outline-primary" target="_blank">
                        <i class="bi bi-file-earmark-text me-1"></i> Boleta PDF
                    </a>
                    <a href="{{ route('documentos.generados.descargar', ['tipo' => 'historial', 'alumno' => $alumno->id]) }}" class="btn btn-outline-primary" target="_blank">
                        <i class="bi bi-file-earmark-text me-1"></i> Historial PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- Tira de materias / Historial por periodos -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Tira de Materias / Historial Académico</h5>
            </div>
            <div class="card-body p-0">
                @if($tira->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-book display-4"></i>
                        <p class="mt-2">No hay materias registradas en el plan de estudios.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Clave</th>
                                    <th>Materia</th>
                                    <th>Créditos</th>
                                    <th>Estatus</th>
                                    <th>Calificación</th>
                                    <th>Observación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tira as $m)
                                    <tr class="{{ $m['es_aprobada'] ? 'table-success' : ($m['es_reprobada'] ? 'table-danger' : ($m['estatus'] === 'CURSANDO' ? 'table-warning' : '')) }}">
                                        <td>{{ $m['materia']['clave'] }}</td>
                                        <td>{{ $m['materia']['nombre'] }}</td>
                                        <td>{{ $m['materia']['creditos'] }}</td>
                                        <td>
                                            <span class="badge {{ match($m['estatus']) {
                                                'APROBADA' => 'bg-success',
                                                'REPROBADA' => 'bg-danger',
                                                'EXTRAORDINARIO' => 'bg-warning text-dark',
                                                'CURSANDO' => 'bg-warning text-dark',
                                                default => 'bg-secondary'
                                            }}">{{ $m['estatus'] }}</span>
                                        </td>
                                        <td>{{ $m['calificacion_final'] ?? '—' }}</td>
                                        <td>
                                            @if($m['es_extraordinario'])
                                                <span class="badge bg-warning text-dark"><i class="bi bi-star me-1"></i> Extraordinario</span>
                                            @endif
                                            @if($m['es_equivalencia'])
                                                <span class="badge bg-info text-dark"><i class="bi bi-arrow-left-right me-1"></i> Equivalencia</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- Detalle por periodos -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Detalle por Periodos</h5>
            </div>
            <div class="card-body p-0">
                @php
                    $historialPorPeriodo = $resumen['historial']->groupBy('periodo.clave');
                @endphp
                @foreach($historialPorPeriodo as $periodoClave => $materias)
                    <div class="card-header bg-light">
                        <h6 class="mb-0">{{ $periodoClave }}</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 15%;">Clave</th>
                                    <th>Materia</th>
                                    <th style="width: 8%;">Créditos</th>
                                    <th style="width: 12%;">Estatus</th>
                                    <th style="width: 10%;">Calificación</th>
                                    <th>Observación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($materias as $m)
                                    <tr class="{{ $m['es_aprobada'] ? 'table-success' : ($m['es_reprobada'] ? 'table-danger' : ($m['estatus'] === 'CURSANDO' ? 'table-warning' : '')) }}">
                                        <td>{{ $m['materia']['clave'] }}</td>
                                        <td>{{ $m['materia']['nombre'] }}</td>
                                        <td>{{ $m['materia']['creditos'] }}</td>
                                        <td>
                                            <span class="badge {{ match($m['estatus']) {
                                                'APROBADA' => 'bg-success',
                                                'REPROBADA' => 'bg-danger',
                                                'EXTRAORDINARIO' => 'bg-warning text-dark',
                                                'CURSANDO' => 'bg-warning text-dark',
                                                default => 'bg-secondary'
                                            }}">{{ $m['estatus'] }}</span>
                                        </td>
                                        <td>{{ $m['calificacion_final'] ?? '—' }}</td>
                                        <td>
                                            @if($m['es_extraordinario'])
                                                <span class="badge bg-warning text-dark"><i class="bi bi-star me-1"></i> Extraordinario</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="{{ url()->previous() }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver a la lista
            </a>
        </div>
    @endif
</div>