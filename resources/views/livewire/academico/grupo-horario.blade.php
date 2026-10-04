<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">
            Horarios de Grupos
            @if($grupo)
                <span class="text-muted fw-normal fs-6"> — {{ $grupo->clave }} ({{ $grupo->materia?->clave ?? '—' }})</span>
            @endif
        </h1>
        <button type="button" class="btn btn-primary" wire:click="abrirModalCrear"
            @if(!$grupo) disabled @endif>
            <i class="bi bi-plus-lg me-1"></i> Nuevo Horario
        </button>
    </div>

    @if(!$grupo)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Seleccione un grupo desde el listado principal para gestionar sus horarios.
        </div>
    @endif

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" wire:model.debounce.300ms="busqueda" placeholder="Día...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Día</label>
                    <select class="form-select" wire:model="filtroDia">
                        <option value="">Todos</option>
                        @foreach(\App\Enums\DiaSemana::cases() as $d)
                            <option value="{{ $d->value }}">{{ $d->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Grupo</label>
                    <select class="form-select" wire:model="filtroGrupo">
                        <option value="">Todos</option>
                        @if($grupos)
                            @foreach($grupos as $g)
                                <option value="{{ $g->id }}">{{ $g->clave }} - {{ $g->materia->clave }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card">
        <div class="card-body p-0">
            @if($horarios->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-calendar3 display-4"></i>
                    <p class="mt-2">No hay horarios registrados{{ $grupo ? ' para este grupo' : '' }}.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Grupo</th>
                                <th>Materia</th>
                                <th>Día</th>
                                <th>Hora Inicio</th>
                                <th>Hora Fin</th>
                                <th>Aula</th>
                                <th>Docente</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($horarios as $h)
                                <tr>
                                    <td>{{ $h->grupo->clave }}</td>
                                    <td>{{ $h->grupo->materia->clave }} - {{ $h->grupo->materia->nombre }}</td>
                                    <td>
                                        <span class="badge bg-info text-dark">{{ $h->dia->label() }}</span>
                                    </td>
                                    <td>{{ $h->hora_inicio->format('H:i') }}</td>
                                    <td>{{ $h->hora_fin->format('H:i') }}</td>
                                    <td>{{ $h->grupo->aula->nombre ?? '—' }}</td>
                                    <td>{{ $h->grupo->docente->nombreCompleto() ?? '—' }}</td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" wire:click="abrirModalEditar({{ $h->id }})" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" wire:click="confirmarEliminacion({{ $h->id }})" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $horarios->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Crear/Editar -->
    <div class="modal fade" tabindex="-1" wire:model="modalAbierto">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $horarioEnEdicion ? 'Editar Horario' : 'Nuevo Horario' }}</h5>
                    <button type="button" class="btn-close" wire:click="cerrarModal"></button>
                </div>
                <form wire:submit="guardar">
                    <div class="modal-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">Grupo *</label>
                                <select class="form-select" wire:model="grupoId" required>
                                    <option value="">Seleccione un grupo</option>
                                    @foreach($grupos as $g)
                                        <option value="{{ $g->id }}">{{ $g->clave }} - {{ $g->materia->clave }} ({{ $g->periodo->clave }})</option>
                                    @endforeach
                                </select>
                                @error('grupoId') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Día *</label>
                                <select class="form-select" wire:model="dia" required>
                                    <option value="">Seleccione un día</option>
                                    @foreach(\App\Enums\DiaSemana::cases() as $d)
                                        <option value="{{ $d->value }}">{{ $d->label() }}</option>
                                    @endforeach
                                </select>
                                @error('dia') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Hora Inicio *</label>
                                <input type="time" class="form-control" wire:model="horaInicio" required>
                                @error('horaInicio') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Hora Fin *</label>
                                <input type="time" class="form-control" wire:model="horaFin" required>
                                @error('horaFin') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="cerrarModal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">{{ $horarioEnEdicion ? 'Actualizar' : 'Crear' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirmación Eliminar -->
    @if(session('eliminarHorarioId'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (confirm('¿Eliminar el horario? Esta acción no se puede deshacer.')) {
                    @this.call('eliminar', {{ session('eliminarHorarioId') }})
                }
            });
        </script>
    @endif
</div>