<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Planes de Estudio</h1>
        <button type="button" class="btn btn-primary" wire:click="abrirModalCrear">
            <i class="bi bi-plus-lg me-1"></i> Nuevo Plan
        </button>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" wire:model.debounce.300ms="busqueda" placeholder="Nombre o clave...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Programa</label>
                    <select class="form-select" wire:model="filtroPrograma">
                        <option value="">Todos</option>
                        @foreach($programas as $programa)
                            <option value="{{ $programa->id }}">{{ $programa->nombre }}</option>
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
            @if($planes->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-journal-text display-4"></i>
                    <p class="mt-2">No hay planes de estudio registrados.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Clave</th>
                                <th>Nombre</th>
                                <th>Programa</th>
                                <th>RVOE</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($planes as $plan)
                                <tr>
                                    <td>{{ $plan->clave }}</td>
                                    <td>{{ $plan->nombre }}</td>
                                    <td>{{ $plan->programa->nombre ?? '—' }}</td>
                                    <td>{{ $plan->resolucion_rvoe ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $plan->activo ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $plan->activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" wire:click="abrirModalEditar({{ $plan->id }})" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn {{ $plan->activo ? 'btn-outline-secondary' : 'btn-outline-success' }}" wire:click="alternarActivo({{ $plan->id }})" title="{{ $plan->activo ? 'Desactivar' : 'Activar' }}">
                                                <i class="bi {{ $plan->activo ? 'bi-pause' : 'bi-play' }}"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" wire:click="confirmarEliminacion({{ $plan->id }})" title="Eliminar">
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
                    {{ $planes->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Crear/Editar -->
    <div class="modal fade" tabindex="-1" wire:model="modalAbierto">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $planEnEdicion ? 'Editar Plan' : 'Nuevo Plan de Estudio' }}</h5>
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
                                <input type="text" class="form-control" wire:model="clave" maxlength="20" placeholder="Ej. DER-2024" pattern="[A-Z0-9_-]+" required>
                                @error('clave') <div class="text-danger small">{{ $message }}</div> @enderror
                                <div class="form-text">Solo mayúsculas, números, guión y guion bajo.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Programa *</label>
                                <select class="form-select" wire:model="programaId" required>
                                    <option value="">Seleccione un programa</option>
                                    @foreach($programas as $programa)
                                        <option value="{{ $programa->id }}">{{ $programa->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('programaId') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label required">Nombre *</label>
                                <input type="text" class="form-control" wire:model="nombre" maxlength="150" required>
                                @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Resolución / RVOE</label>
                                <input type="text" class="form-control" wire:model="resolucionRvoe" maxlength="100" placeholder="Ej. RVOE 20240123">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Fecha de Resolución</label>
                                <input type="date" class="form-control" wire:model="fechaResolucion">
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" wire:model="activo" id="activo">
                                    <label class="form-check-label" for="activo">Activo</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="cerrarModal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">{{ $planEnEdicion ? 'Actualizar' : 'Crear' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirmación Eliminar (SweetAlert o modal simple) -->
    @if(session('eliminarPlanId'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (confirm('¿Eliminar el plan "{{ session('eliminarPlanNombre') }}"? Esta acción no se puede deshacer.')) {
                    @this.call('eliminar', {{ session('eliminarPlanId') }})
                }
            });
        </script>
    @endif
</div>