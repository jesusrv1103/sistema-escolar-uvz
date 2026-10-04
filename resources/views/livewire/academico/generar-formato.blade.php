<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">Generar Formatos Académicos</h1>
    </div>

    <!-- Seleccionar tipo de formato -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">1. Seleccionar tipo de formato</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($tiposFormato as $tipo)
                    <div class="col-md-4 col-sm-6">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipoFormato" id="tipo_{{ $tipo->value }}" value="{{ $tipo->value }}" wire:model="tipoFormato" {{ $tipo->requiereFolio() ? 'data-folio="true"' : '' }}>
                            <label class="form-check-label w-100 d-flex flex-column p-3 border rounded h-100 {{ $tipoFormato === $tipo->value ? 'border-primary bg-light' : '' }}" for="tipo_{{ $tipo->value }}">
                                <strong>{{ $tipo->label() }}</label>
                                <small class="text-muted">{{ $tipo->requiereFolio() ? 'Genera folio (CI-YYYY-########)' : 'Sin folio' }}</small>
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('tipoFormato')
                <div class="text-danger small mt-2">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <!-- Seleccionar alumno -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">2. Seleccionar alumno</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="abrirModalAlumno" {{ !$tipoFormato ? 'disabled' : '' }}>
                <i class="bi bi-search me-1"></i> Buscar alumno
            </button>
        </div>
        <div class="card-body">
            @if($alumnoSeleccionado)
                <div class="alert alert-success d-flex justify-content-between align-items-center">
                    <div>
                        <strong>{{ $alumnoSeleccionado->nombre_completo }}</strong>
                        <span class="badge bg-secondary ms-2">{{ $alumnoSeleccionado->curp }}</span>
                        <span class="badge bg-info ms-2">{{ $alumnoSeleccionado->numero_control ?? 'Sin NC' }}</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="limpiarSeleccion">
                        <i class="bi bi-x-lg me-1"></i> Cambiar
                    </button>
                </div>

                @if($matriculaSeleccionada)
                    <div class="alert alert-info mt-2 mb-0">
                        <strong>Matrícula activa:</strong> {{ $matriculaSeleccionada->periodo->clave }} - {{ $matriculaSeleccionada->periodo->nombre }}
                        <span class="badge bg-{{ match($matriculaSeleccionada->estatus->value) { 'ACTIVA' => 'success', 'BAJA_TEMPORAL' => 'warning text-dark', 'BAJA_DEFINITIVA' => 'danger', 'EGRESADO' => 'primary', default => 'secondary' }} ms-2">{{ $matriculaSeleccionada->estatus->label() }}</span>
                        <span class="badge bg-secondary ms-2">{{ $matriculaSeleccionada->programa->clave }} - {{ $matriculaSeleccionada->programa->nombre }}</span>
                    </div>
                @else
                    <div class="text-muted small mt-2">El alumno no tiene matrícula activa.</div>
                @endif
            @else
                <div class="text-muted">Seleccione un alumno para continuar.</div>
            @endif

            <!-- Modal buscar alumno -->
            <div class="modal fade" tabindex="-1" wire:model="modalAlumnoAbierto">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Buscar alumno</h5>
                            <button type="button" class="btn-close" wire:click="$set('modalAlumnoAbierto', false)"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <input type="text" class="form-control" wire:model.debounce.300ms="busquedaAlumno" placeholder="Nombre, apellidos, CURP o número de control">
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nombre</th>
                                            <th>CURP</th>
                                            <th>Número de control</th>
                                            <th>Correo</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($alumnos as $alumno)
                                            <tr wire:click="alumnoSeleccionado({{ $alumno->id }})" style="cursor: pointer;">
                                                <td>{{ $alumno->nombre_completo }}</td>
                                                <td>{{ $alumno->curp }}</td>
                                                <td>{{ $alumno->numero_control ?? '—' }}</td>
                                                <td>{{ $alumno->correo ?? '—' }}</td>
                                                <td><button type="button" class="btn btn-sm btn-primary" wire:click.stop="alumnoSeleccionado({{ $alumno->id }})">Seleccionar</button></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Seleccionar grupo (para horarios y lista de grupo) -->
    @if(in_array($tipoFormato, ['HORARIO', 'LISTA_GRUPO']))
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">3. Seleccionar grupo</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="abrirModalGrupo">
                <i class="bi bi-search me-1"></i> Buscar grupo
            </button>
        </div>
        <div class="card-body">
            @if($grupoSeleccionado)
                <div class="alert alert-success d-flex justify-content-between align-items-center">
                    <div>
                        <strong>{{ $grupoSeleccionado->clave }}</strong> - {{ $grupoSeleccionado->materia->clave }} - {{ $grupoSeleccionado->materia->nombre }}
                        <span class="badge bg-secondary ms-2">{{ $grupoSeleccionado->periodo->clave }}</span>
                        <span class="badge bg-info ms-2">{{ $grupoSeleccionado->docente?->nombreCompleto() ?? 'Sin docente' }}</span>
                        <span class="badge bg-secondary ms-2">{{ $grupoSeleccionado->aula?->nombre ?? 'Sin aula' }}</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="$set('grupoSeleccionado', null)">
                        <i class="bi bi-x-lg me-1"></i> Cambiar
                    </button>
                </div>
            @else
                <div class="text-muted">Seleccione un grupo para continuar.</div>
            @endif

            <!-- Modal buscar grupo -->
            <div class="modal fade" tabindex="-1" wire:model="modalGrupoAbierto">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Buscar grupo</h5>
                            <button type="button" class="btn-close" wire:click="$set('modalGrupoAbierto', false)"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <input type="text" class="form-control" wire:model.debounce.300ms="busquedaGrupo" placeholder="Clave, materia, docente...">
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Clave</th>
                                            <th>Materia</th>
                                            <th>Periodo</th>
                                            <th>Docente</th>
                                            <th>Aula</th>
                                            <th>Cupo</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($grupos as $grupo)
                                            <tr wire:click="grupoSeleccionado({{ $grupo->id }})" style="cursor: pointer;">
                                                <td>{{ $grupo->clave }}</td>
                                                <td>{{ $grupo->materia->clave }} - {{ $grupo->materia->nombre }}</td>
                                                <td>{{ $grupo->periodo->clave }}</td>
                                                <td>{{ $grupo->docente?->nombreCompleto() ?? '—' }}</td>
                                                <td>{{ $grupo->aula?->nombre ?? '—' }}</td>
                                                <td>{{ $grupo->cupo }}</td>
                                                <td><button type="button" class="btn btn-sm btn-primary" wire:click.stop="grupoSeleccionado({{ $grupo->id }})">Seleccionar</button></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Generar -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Generar formato</h5>
                <button type="button" class="btn btn-primary btn-lg" wire:click="generar" :disabled="generando || !tipoFormato || !alumnoSeleccionado || (in_array(tipoFormato, ['HORARIO', 'LISTA_GRUPO']) && !grupoSeleccionado)">
                    @if($generando)
                        <span class="spinner-border spinner-border-sm me-2"></span> Generando...
                    @else
                        <i class="bi bi-file-earmark-pdf me-2"></i> Generar PDF
                    @endif
                </button>
            </div>

            @error('generar')
                <div class="alert alert-danger mt-3">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <!-- Script para descargar PDF -->
    @this.on('pdf-generado', function (path) {
        window.open('{{ route("documentos.generados.descargar", ["path" => "__PATH__"]) }}'.replace('__PATH__', encodeURIComponent(path)), '_blank');
    })
</div>

@push('scripts')
<script>
    // Manejar descarga de PDF
    document.addEventListener('livewire:load', function() {
        Livewire.on('pdf-generado', (path) => {
            window.open('/documentos/generados/descargar/' + encodeURIComponent(path), '_blank');
        });
    });
</script>
@endpush