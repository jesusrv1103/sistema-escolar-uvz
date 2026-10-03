<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">
            Materias
            @if($version)
                <span class="text-muted fw-normal fs-6">
                    — {{ $version->plan->nombre ?? '' }} v{{ $version->version }}
                    <span class="badge {{ match($version->estatus) {
                        'VIGENTE' => 'bg-success',
                        'PROPUESTA' => 'bg-warning text-dark',
                        'SUPERADA' => 'bg-secondary',
                        default => 'bg-secondary'
                    }} ms-2">
                        {{ $version->estatus->label() ?? $version->estatus }}
                    </span>
                </span>
            @endif
        </h1>
        <button type="button" class="btn btn-primary" wire:click="abrirModalCrear"
            @if(!$version) disabled @endif>
            <i class="bi bi-plus-lg me-1"></i> Nueva Materia
        </button>
    </div>

    @if(!$version)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Seleccione una versión de plan desde el listado de versiones para gestionar sus materias.
        </div>
    @endif

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" wire:model.debounce.300ms="busqueda" placeholder="Nombre o clave...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cuatrimestre (Periodo orden)</label>
                    <select class="form-select" wire:model="filtroPeriodo">
                        <option value="">Todos</option>
                        @foreach($periodos as $p)
                            <option value="{{ $p }}">Cuatrimestre {{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" wire:model="filtroActivo">
                        <option value="">Todos</option>
                        <option value="1">Activas</option>
                        <option value="0">Inactivas</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card">
        <div class="card-body p-0">
            @if($materias->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-book display-4"></i>
                    <p class="mt-2">No hay materias registradas{{ $version ? ' para esta versión' : '' }}.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Clave</th>
                                <th>Nombre</th>
                                <th>Créditos</th>
                                <th>Cuatrimestre</th>
                                <th>Bloque</th>
                                <th>Obligatoria</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($materias as $m)
                                <tr>
                                    <td>{{ $m->clave }}</td>
                                    <td>{{ $m->nombre }}</td>
                                    <td>{{ $m->creditos }}</td>
                                    <td>
                                        <span class="badge bg-info text-dark">Cuatrimestre {{ $m->periodo_orden }}</span>
                                    </td>
                                    <td>{{ $m->bloque ?? '—' }}</td>
                                    <td>
                                        @if($m->obligatoria)
                                            <span class="badge bg-danger">Obligatoria</span>
                                        @else
                                            <span class="badge bg-info">Optativa</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $m->activo ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $m->activo ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            @if($version?->esPropuesta())
                                                <button type="button" class="btn btn-outline-primary" wire:click="abrirModalEditar({{ $m->id }})" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-info" wire:click="abrirModalPrerreq({{ $m->id }})" title="Prerrequisitos">
                                                    <i class="bi bi-diagram-3"></i>
                                                </button>
                                                <button type="button" class="btn {{ $m->activo ? 'btn-outline-secondary' : 'btn-outline-success' }}" wire:click="alternarActivo({{ $m->id }})" title="{{ $m->activo ? 'Desactivar' : 'Activar' }}">
                                                    <i class="bi {{ $m->activo ? 'bi-pause' : 'bi-play' }}"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" wire:click="confirmarEliminacion({{ $m->id }})" title="Eliminar">
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
                    {{ $materias->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Crear/Editar -->
    <div class="modal fade" tabindex="-1" wire:model="modalAbierto">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $materiaEnEdicion ? 'Editar Materia' : 'Nueva Materia' }}</h5>
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
                                <input type="text" class="form-control" wire:model="clave" maxlength="20" placeholder="Ej. MAT-101" pattern="[A-Z0-9_-]+" required>
                                @error('clave') <div class="text-danger small">{{ $message }}</div> @enderror
                                <div class="form-text">Solo mayúsculas, números, guión y guion bajo.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Cuatrimestre *</label>
                                <input type="number" class="form-control" wire:model="periodoOrden" min="1" required>
                                @error('periodoOrden') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Créditos *</label>
                                <input type="number" class="form-control" wire:model="creditos" min="1" required>
                                @error('creditos') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Horas Teóricas</label>
                                <input type="number" class="form-control" wire:model="horasTeoricas" min="0">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Horas Prácticas</label>
                                <input type="number" class="form-control" wire:model="horasPracticas" min="0">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Bloque</label>
                                <input type="text" class="form-control" wire:model="bloque" maxlength="30" placeholder="Ej. TRONCO_COMUN">
                            </div>

                            <div class="col-12">
                                <label class="form-label required">Nombre *</label>
                                <input type="text" class="form-control" wire:model="nombre" maxlength="150" required>
                                @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" wire:model="obligatoria" id="obligatoria">
                                    <label class="form-check-label" for="obligatoria">Obligatoria</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" wire:model="activo" id="activo">
                                    <label class="form-check-label" for="activo">Activa</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="cerrarModal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">{{ $materiaEnEdicion ? 'Actualizar' : 'Crear' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Prerrequisitos -->
    <div class="modal fade" tabindex="-1" wire:model="modalPrerreqAbierto">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Prerrequisitos de <strong>{{ $materiaPrerreq->nombre ?? '' }}</strong>
                        <span class="badge bg-info text-dark ms-2">{{ $materiaPrerreq->clave }}</span>
                    </h5>
                    <button type="button" class="btn-close" wire:click="cerrarModalPrerreq"></button>
                </div>
                <div class="modal-body">
                    @if ($errors->any('prerreq'))
                        <div class="alert alert-danger">
                            @foreach ($errors->get('prerreq') as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo de prerrequisito</label>
                            <select class="form-select" wire:model="tipoPrerreq">
                                <option value="OBLIGATORIO">Obligatorio</option>
                                <option value="RECOMENDADO">Recomendado</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Buscar materia</label>
                            <input type="text" class="form-control" wire:model.debounce.300ms="busquedaPrerreq" placeholder="Nombre o clave...">
                        </div>
                    </div>

                    <div class="row">
                        <!-- Disponibles para agregar -->
                        <div class="col-md-6">
                            <h6>Disponibles para agregar</h6>
                            <div class="list-group" style="max-height: 300px; overflow-y: auto;">
                                @php
                                    $yaTienen = $materiaPrerreq->prerrequisitos->pluck('id')->toArray();
                                    $disponibles = \App\Models\Materia::where('plan_version_id', $materiaPrerreq->plan_version_id)
                                        ->where('id', '!=', $materiaPrerreq->id)
                                        ->whereNotIn('id', $yaTienen)
                                        ->when($busquedaPrerreq, fn($q) => $q->where('nombre', 'like', "%{$busquedaPrerreq}%")->orWhere('clave', 'like', "%{$busquedaPrerreq}%"))
                                        ->orderBy('periodo_orden')->orderBy('clave')
                                        ->get();
                                @endphp
                                @if($disponibles->isEmpty())
                                    <div class="text-muted text-center py-3">Todas las materias disponibles ya son prerrequisitos.</div>
                                @else
                                    @foreach($disponibles as $d)
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>{{ $d->clave }}</strong> {{ $d->nombre }}
                                                <br><small class="text-muted">Cuatrimestre {{ $d->periodo_orden }} • {{ $d->creditos }} créditos</small>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-primary" wire:click="agregarPrerrequisito({{ $d->id }})">
                                                <i class="bi bi-plus-circle me-1"></i> Agregar
                                            </button>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>

                        <!-- Ya agregados -->
                        <div class="col-md-6">
                            <h6>Prerrequisitos actuales</h6>
                            <div class="list-group" style="max-height: 300px; overflow-y: auto;">
                                @if($materiaPrerreq->prerrequisitos->isEmpty())
                                    <div class="text-muted text-center py-3">Sin prerrequisitos.</div>
                                @else
                                    @foreach($materiaPrerreq->prerrequisitos as $p)
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>{{ $p->clave }}</strong> {{ $p->nombre }}
                                                <br><small class="text-muted">Cuatrimestre {{ $p->periodo_orden }}</small>
                                                <span class="badge {{ $p->pivot->tipo === 'OBLIGATORIO' ? 'bg-danger' : 'bg-info' }} ms-2">
                                                    {{ $p->pivot->tipo->label() ?? $p->pivot->tipo }}
                                                </span>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="quitarPrerrequisito({{ $p->id }})" title="Quitar">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="cerrarModalPrerreq">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>