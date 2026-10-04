<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">
            Captura de Calificaciones
            @if($grupo)
                <span class="text-muted fw-normal fs-6"> — {{ $grupo->clave }} ({{ $grupo->materia->clave }})</span>
            @endif
        </h1>
    </div>

    @if(!$grupo)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Seleccione un grupo desde el listado de evaluaciones para capturar calificaciones.
        </div>
    @else
        <!-- Resumen de ponderación -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Configuración del Grupo</h6>
                        <p class="mb-1"><strong>Grupo:</strong> {{ $grupo->clave }} - {{ $grupo->materia->clave }} - {{ $grupo->materia->nombre }}</p>
                        <p class="mb-1"><strong>Docente:</strong> {{ $grupo->docente?->nombreCompleto() ?? 'Sin asignar' }}</p>
                        <p class="mb-1"><strong>Periodo:</strong> {{ $grupo->periodo->clave }} - {{ $grupo->periodo->nombre }}</p>
                        <p class="mb-1"><strong>Cupo:</strong> {{ $grupo->cupo }} | <strong>Inscritos:</strong> {{ $grupo->inscripcionesMaterias()->where('estatus', 'CURSANDO')->count() }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Ponderación de Evaluaciones</h6>
                        @foreach($evaluaciones as $eval)
                            <div class="d-flex justify-content-between mb-1">
                                <span>{{ $eval->nombre }} ({{ $eval->tipo }})</span>
                                <strong>{{ $eval->ponderacion }}%</strong>
                            </div>
                        @endforeach
                        <hr>
                        <div class="d-flex justify-content-between fw-bold">
                            <span>Total Ponderación:</span>
                            <span class="{{ \App\Models\Evaluacion::ponderacionTotalGrupo($grupo->id) == 100 ? 'text-success' : 'text-danger' }}">
                                {{ \App\Models\Evaluacion::ponderacionTotalGrupo($grupo->id) }}%
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Matriz de Calificaciones -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Matriz de Calificaciones</h5>
                @if(\App\Models\Evaluacion::ponderacionTotalGrupo($grupo->id) == 100)
                    <button type="button" class="btn btn-success btn-sm" wire:click="consolidar({{ $grupo->inscripcionesMaterias()->where('estatus', 'CURSANDO')->first()?->id ?? 0 }})" @if($inscripciones->isEmpty()) disabled @endif>
                        <i class="bi bi-check-circle me-1"></i> Consolidar Calificaciones Finales
                    </button>
                @endif
            </div>
            <div class="card-body p-0">
                @if($inscripciones->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-people display-4"></i>
                        <p class="mt-2">No hay alumnos inscritos en este grupo.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 150px;">Alumno</th>
                                    <th style="min-width: 100px;">NC</th>
                                    @foreach($evaluaciones as $eval)
                                        <th style="min-width: 80px;" title="{{ $eval->nombre }} ({{ $eval->tipo }})">
                                            {{ $eval->nombre }}<br>
                                            <small class="text-muted">{{ $eval->ponderacion }}%</small>
                                        </th>
                                    @endforeach
                                    <th style="min-width: 100px;">Promedio</th>
                                    <th style="min-width: 100px;">Estatus</th>
                                    <th class="text-end" style="min-width: 120px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($inscripciones as $inscripcion)
                                    @php
                                        $calificaciones = [];
                                        $totalPonderado = 0;
                                        $totalPonderacion = 0;
                                        foreach($evaluaciones as $eval) {
                                            $cal = $inscripcion->calificaciones->where('evaluacion_id', $eval->id)->first();
                                            if ($cal) {
                                                $ponderacion = $eval->ponderacion / 100;
                                                $totalPonderado += $cal->valor * $ponderacion;
                                                $totalPonderacion += $eval->ponderacion / 100;
                                            }
                                        }
                                        $promedio = $totalPonderacion > 0 ? $totalPonderado / $totalPonderacion : 0;
                                    @endphp
                                    <tr>
                                        <td>{{ $inscripcion->matriculaPeriodo->alumno->nombre_completo }}</td>
                                        <td>{{ $inscripcion->matriculaPeriodo->alumno->numero_control ?? '—' }}</td>
                                        @foreach($evaluaciones as $eval)
                                            @php $cal = $inscripcion->calificaciones->where('evaluacion_id', $eval->id)->first(); @endphp
                                            <td>
                                                @if($cal = $inscripcion->calificaciones->where('evaluacion_id', $eval->id)->first())
                                                    <span class="badge bg-{{ $cal->valor >= 60 ? 'success' : 'danger' }}">{{ number_format($cal->valor, 2) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td>
                                            @if($totalPonderacion > 0)
                                                <strong>{{ number_format($totalPonderado / $totalPonderacion, 2) }}</strong>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $inscripcion->estatus->value === 'APROBADA' ? 'success' : ($inscripcion->estatus->value === 'REPROBADA' ? 'danger' : 'secondary') }}">
                                                {{ $inscripcion->estatus->label() }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" wire:click="abrirModalCapturar({{ $inscripcion->id }}, {{ $evaluaciones->first()?->id ?? 0 }})" title="Capturar calificaciones">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                @if(\App\Models\Evaluacion::ponderacionTotalGrupo($grupo->id) == 100 && $inscripcion->estatus->value === 'CURSANDO')
                                                    <button type="button" class="btn btn-outline-success" wire:click="consolidar({{ $inscripcion->id }})" title="Consolidar calificación final">
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Modal Capturar Calificación -->
    <div class="modal fade" tabindex="-1" wire:model="modalCalificacionAbierto">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Capturar Calificación</h5>
                    <button type="button" class="btn-close" wire:click="cerrarModalCalificacion"></button>
                </div>
                <form wire:submit="guardarCalificacion">
                    <div class="modal-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        <input type="hidden" wire:model="dispatch">

                        <div class="mb-3">
                            <label class="form-label required">Calificación *</label>
                            <input type="number" class="form-control" wire:model="valor" min="0" max="100" step="0.01" required>
                            @error('valor') <div class="text-danger small">{{ $message }}</div> @enderror
                            <div class="form-text">Entre 0.00 y 100.00</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Observación</label>
                            <textarea class="form-control" wire:model="observacion" rows="3" maxlength="1000" placeholder="Observación opcional..."></textarea>
                            @error('observacion') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="cerrarModalCalificacion">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>