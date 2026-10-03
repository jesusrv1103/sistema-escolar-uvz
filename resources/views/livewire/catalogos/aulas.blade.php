<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">Aulas</h1>
        <button type="button" class="btn btn-primary" wire:click="crear">
            <i class="bi bi-plus-lg me-1"></i> Nueva Aula
        </button>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" wire:model.debounce.300ms="busqueda" placeholder="Nombre, clave o edificio...">
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
            @if($aulas->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-building display-4"></i>
                    <p class="mt-2">No hay aulas registradas.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Clave</th>
                                <th>Nombre</th>
                                <th>Edificio</th>
                                <th>Capacidad</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($aulas as $a)
                                <tr>
                                    <td>{{ $a->clave }}</td>
                                    <td>{{ $a->nombre }}</td>
                                    <td>{{ $a->edificio }}</td>
                                    <td>{{ $a->capacidad }}</td>
                                    <td>
                                        <span class="badge bg-info text-dark">{{ $a->tipo?->label() ?? $a->tipo }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $a->activo ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $a->activo ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" wire:click="editar({{ $a->id }})" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn {{ $a->activo ? 'btn-outline-secondary' : 'btn-outline-success' }}" wire:click="alternarActivo({{ $a->id }})" title="{{ $a->activo ? 'Desactivar' : 'Activar' }}">
                                                <i class="bi {{ $a->activo ? 'bi-pause' : 'bi-play' }}"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" wire:click="confirmarEliminacion({{ $a->id }})" title="Eliminar">
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
                    {{ $aulas->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Crear/Editar -->
    <div class="modal fade" tabindex="-1" wire:model="formulario">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $registroId ? 'Editar Aula' : 'Nueva Aula' }}</h5>
                    <button type="button" class="btn-close" wire:click="cancelar"></button>
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
                                <input type="text" class="form-control" wire:model="clave" maxlength="20" placeholder="Ej. A-101" pattern="[A-Z0-9_-]+" required>
                                @error('clave') <div class="text-danger small">{{ $message }}</div> @enderror
                                <div class="form-text">Solo mayúsculas, números, guión y guion bajo.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Nombre *</label>
                                <input type="text" class="form-control" wire:model="nombre" maxlength="100" required>
                                @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Edificio *</label>
                                <input type="text" class="form-control" wire:model="edificio" maxlength="100" required>
                                @error('edificio') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Capacidad *</label>
                                <input type="number" class="form-control" wire:model="capacidad" min="1" max="500" required>
                                @error('capacidad') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Tipo *</label>
                                <select class="form-select" wire:model="tipo" required>
                                    <option value="TEORICA">Teórica</option>
                                    <option value="PRACTICA">Práctica</option>
                                    <option value="MIXTA">Mixta</option>
                                    <option value="LABORATORIO">Laboratorio</option>
                                </select>
                                @error('tipo') <div class="text-danger small">{{ $message }}</div> @enderror
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
                        <button type="button" class="btn btn-secondary" wire:click="cancelar">Cancelar</button>
                        <button type="submit" class="btn btn-primary">{{ $registroId ? 'Actualizar' : 'Crear' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirmación Eliminar -->
    @if(session('eliminarAulaId'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (confirm('¿Eliminar el aula "{{ session('eliminarAulaNombre') }}"? Esta acción no se puede deshacer.')) {
                    @this.call('eliminar', {{ session('eliminarAulaId') }})
                }
            });
        </script>
    @endif
</div>