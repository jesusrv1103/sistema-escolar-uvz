<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">Grupos Académicos</h1>
        <button type="button" class="btn btn-primary" wire:click="abrirModalCrear">
            <i class="bi bi-plus-lg me-1"></i> Nuevo Grupo
        </button>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" wire:model.debounce.300ms="busqueda" placeholder="Nombre, clave, materia...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Periodo</label>
                    <select class="form-select" wire:model="filtroPeriodo">
                        <option value="">Todos</option>
                        @foreach($periodos as $p)
                            <option value="{{ $p->id }}">{{ $p->clave }} - {{ $p->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Materia</label>
                    <select class="form-select" wire:model="filtroMateria">
                        <option value="">Todas</option>
                        @foreach($materias as $m)
                            <option value="{{ $m->id }}">{{ $m->clave }} - {{ $m->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Docente</label>
                    <select class="form-select" wire:model="filtroDocente">
                        <option value="">Todos</option>
                        @foreach($docentes as $d)
                            <option value="{{ $d->id }}">{{ $d->nombreCompleto() }} ({{ $d->clave }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estatus</label>
                    <select class="form-select" wire:model="filtroEstatus">
                        <option value="">Todos</option>
                        @foreach(\App\Enums\EstatusGrupo::cases() as $e)
                            <option value="{{ $e->value }}">{{ $e->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" wire:model="filtroActivo">
                        <option value="">Todos</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card">
        <div class="card-body p-0">
            @if($grupos->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people display-4"></i>
                    <p class="mt-2">No hay grupos registrados.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Clave</th>
                                <th>Materia</th>
                                <th>Periodo</th>
                                <th>Docente</th>
                                <th>Aula</th>
                                <th>Cupo</th>
                                <th>Turno</th>
                                <th>Estatus</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($grupos as $g)
                                <tr>
                                    <td>{{ $g->clave }}</td>
                                    <td>{{ $g->materia->clave }} - {{ $g->materia->nombre }}</td>
                                    <td>{{ $g->periodo->clave }}</td>
                                    <td>{{ $g->docente->nombreCompleto() ?? '—' }}</td>
                                    <td>{{ $g->aula->nombre ?? '—' }}</td>
                                    <td>{{ $g->cupo }}</td>
                                    <td>{{ $g->turno->label() ?? $g->turno }}</td>
                                    <td>
                                        <span class="badge {{ $g->estatus->labelClass() }}">
                                            {{ $g->estatus->label() }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            @if($g->esCreado())
                                                <button type="button" class="btn btn-outline-primary" wire:click="abrirModalEditar({{ $g->id }})" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-info" wire:click="abrirModalHorarios({{ $g->id }})" title="Horarios">
                                                    <i class="bi bi-calendar3"></i>
                                                </button>
                                                <button type="button" class="btn {{ $g->activo ? 'btn-outline-secondary' : 'btn-outline-success' }}" wire:click="alternarActivo({{ $g->id }})" title="{{ $g->activo ? 'Desactivar' : 'Activar' }}">
                                                    <i class="bi {{ $g->activo ? 'bi-pause' : 'bi-play' }}"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" wire:click="confirmarEliminacion({{ $g->id }})" title="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $grupos->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Crear/Editar -->
    <div class="modal fade" tabindex="-1" wire:model="modalAbierto">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $grupoEnEdicion ? 'Editar Grupo' : 'Nuevo Grupo' }}</h5>
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
                                <label class="form-label required">Clave *</label>
                                <input type="text" class="form-control" wire:model="clave" maxlength="20" placeholder="Ej. GRP-001" pattern="[A-Z0-9_-]+" required>
                                @error('clave') <div class="text-danger small">{{ $message }}</div> @enderror
                                <div class="form-text">Solo mayúsculas, números, guión y guion bajo.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Materia *</label>
                                <select class="form-select" wire:model="materiaId" required>
                                    <option value="">Seleccione una materia</option>
                                    @foreach($materias as $m)
                                        <option value="{{ $m->id }}">{{ $m->clave }} - {{ $m->nombre }} (v{{ $m->planVersion->version }})</option>
                                    @endforeach
                                </select>
                                @error('materiaId') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Periodo *</label>
                                <select class="form-select" wire:model="periodoId" required>
                                    <option value="">Seleccione un periodo</option>
                                    @foreach($periodos as $p)
                                        <option value="{{ $p->id }}">{{ $p->clave }} - {{ $p->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('periodoId') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Cupo *</label>
                                <input type="number" class="form-control" wire:model="cupo" min="1" max="500" required>
                                @error('cupo') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Turno *</label>
                                <select class="form-select" wire:model="turno" required>
                                    @foreach(\App\Enums\TurnoGrupo::cases() as $t)
                                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                    @endforeach
                                </select>
                                @error('turno') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Docente</label>
                                <select class="form-select" wire:model="docenteId">
                                    <option value="">Sin docente asignado</option>
                                    @foreach($docentes as $d)
                                        <option value="{{ $d->id }}">{{ $d->nombreCompleto() }} ({{ $d->clave }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Aula</label>
                                <select class="form-select" wire:model="aulaId">
                                    <option value="">Sin aula asignada</option>
                                    @foreach($aulas as $a)
                                        <option value="{{ $a->id }}">{{ $a->clave }} - {{ $a->nombre }} ({{ $a->edificio }}, cap. {{ $a->capacidad }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Estatus *</label>
                                <select class="form-select" wire:model="estatus" required>
                                    @foreach(\App\Enums\EstatusGrupo::cases() as $e)
                                        <option value="{{ $e->value }}">{{ $e->label() }}</option>
                                    @endforeach
                                </select>
                                @error('estatus') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">Nombre del grupo</label>
                                <input type="text" class="form-control" wire:model="nombre" maxlength="150" placeholder="Se genera automáticamente si se deja vacío">
                                @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="cerrarModal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">{{ $grupoEnEdicion ? 'Actualizar' : 'Crear' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Horarios -->
    <div class="modal fade" tabindex="-1" wire:model="modalHorariosAbierto">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Horarios de <strong>{{ $grupoHorarios->clave ?? '' }}</strong>
                        <span class="badge bg-info text-dark ms-2">{{ $grupoHorarios->materia->clave }}</span>
                    </h5>
                    <button type="button" class="btn-close" wire:click="cerrarModalHorarios"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between mb-3">
                        <h6>Horarios registrados</h6>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="abrirModalHorarioCrear">
                            <i class="bi bi-plus-lg me-1"></i> Agregar Horario
                        </button>
                    </div>

                    @if($grupoHorarios->horarios->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-calendar3 display-4"></i>
                            <p class="mt-2">No hay horarios registrados para este grupo.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Día</th>
                                        <th>Hora Inicio</th>
                                        <th>Hora Fin</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($grupoHorarios->horarios as $h)
                                        <tr>
                                            <td>{{ $h->dia->label() }}</td>
                                            <td>{{ $h->hora_inicio->format('H:i') }}</td>
                                            <td>{{ $h->hora_fin->format('H:i') }}</td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" wire:click="abrirModalEditarHorario({{ $h->id }})" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" wire:click="confirmarEliminacionHorario({{ $h->id }})" title="Eliminar">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
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
        </div>
    </div>

    <!-- Modal Horario Crear/Editar -->
    <div class="modal fade" tabindex="-1" wire:model="modalHorarioAbierto">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $horarioEnEdicion ? 'Editar Horario' : 'Nuevo Horario' }}</h5>
                    <button type="button" class="btn-close" wire:click="cerrarModalHorario"></button>
                </div>
                <form wire:submit="guardarHorario">
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
                                <label class="form-label required">Día *</label>
                                <select class="form-select" wire:model="horarioDia" required>
                                    <option value="">Seleccione un día</option>
                                    @foreach(\App\Enums\DiaSemana::cases() as $d)
                                        <option value="{{ $d->value }}">{{ $d->label() }}</option>
                                    @endforeach
                                </select>
                                @error('horarioDia') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Hora Inicio *</label>
                                <input type="time" class="form-control" wire:model="horarioHoraInicio" required>
                                @error('horarioHoraInicio') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Hora Fin *</label>
                                <input type="time" class="form-control" wire:model="horarioHoraFin" required>
                                @error('horarioHoraFin') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="cerrarModalHorario">Cancelar</button>
                        <button type="submit" class="btn btn-primary">{{ $horarioEnEdicion ? 'Actualizar' : 'Crear' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirmación Eliminar Grupo -->
    @if(session('eliminarGrupoId'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (confirm('¿Eliminar el grupo "{{ session('eliminarGrupoNombre') }}"? Esta acción no se puede deshacer.')) {
                    @this.call('eliminar', {{ session('eliminarGrupoId') }})
                }
            });
        </script>
    @endif

    <!-- Confirmación Eliminar Horario -->
    @if(session('eliminarHorarioId'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (confirm('¿Eliminar el horario "{{ session('eliminarHorarioDia') }}"? Esta acción no se puede deshacer.')) {
                    @this.call('eliminarHorario', {{ session('eliminarHorarioId') }})
                }
            });
        </script>
    @endif
</div>