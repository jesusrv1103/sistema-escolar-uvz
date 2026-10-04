<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">Asistencia</h1>
    </div>

    <!-- Selección de grupo y fecha -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Grupo</label>
                    <select class="form-select" wire:model="grupo">
                        <option value="">Seleccionar grupo</option>
                        @foreach($grupos as $g)
                            <option value="{{ $g->id }}">{{ $g->clave }} - {{ $g->materia->clave }} ({{ $g->periodo->clave }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" class="form-control" wire:model="fecha" max="{{ $grupo ? $grupo->periodo->fecha_fin->format('Y-m-d') : now()->format('Y-m-d') }}" min="{{ $grupo ? $grupo->periodo->fecha_inicio->format('Y-m-d') : '2000-01-01' }}">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-primary w-100" wire:click="cargarLista" @if(!$grupo) disabled @endif>
                        <i class="bi bi-search me-1"></i> Cargar lista
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if($grupo)
    <!-- Lista de alumnos y asistencia -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $grupo->clave }} - {{ $grupo->materia->nombre }} ({{ $grupo->periodo->clave }})</h5>
            <span class="badge bg-info text-dark">{{ $fecha }}</span>
        </div>
        <div class="card-body p-0">
            @if(empty($asistencias))
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people display-4"></i>
                    <p class="mt-2">Haga clic en "Cargar lista" para ver los alumnos.</p>
                </div>
            @else
                <form wire:submit.prevent="guardar">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 20%;">Número de Control</th>
                                    <th style="width: 35%;">Alumno</th>
                                    <th style="width: 15%;">Asistencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $i = 1; @endphp
                                @foreach($asistencias as $item)
                                    <tr>
                                        <td>{{ $i++ }}</td>
                                        <td>{{ $item['inscripcion']->matriculaPeriodo->alumno->numero_control ?? 'Por asignar' }}</td>
                                        <td>{{ $item['inscripcion']->matriculaPeriodo->alumno->nombre_completo }}</td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" wire:model="asistencias.{{ $item['inscripcion']->id }}.presente" @if($item['id']) data-id="{{ $item['id'] }}" @endif>
                                                <label class="form-check-label ms-2">
                                                    {{ $item['presente'] ? 'Presente' : 'Ausente' }}
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" wire:click="cargarLista">
                                <i class="bi bi-arrow-clockwise me-1"></i> Recargar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Guardar asistencia
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
    @endif

    <!-- Modal Registrar Asistencia Individual (opcional) -->
    @livewire('academico.asistencia-form')
</div>