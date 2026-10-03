<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">Docentes</h1>
        <button type="button" class="btn btn-primary" wire:click="crear">
            <i class="bi bi-plus-lg me-1"></i> Nuevo Docente
        </button>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" wire:model.debounce.300ms="busqueda" placeholder="Nombre, apellidos, clave o CURP...">
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
            @if($docentes->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-person-badge display-4"></i>
                    <p class="mt-2">No hay docentes registrados.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Clave</th>
                                <th>Nombre</th>
                                <th>CURP</th>
                                <th>Especialidad</th>
                                <th>Grado</th>
                                <th>Cédula</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($docentes as $d)
                                <tr>
                                    <td>{{ $d->clave }}</td>
                                    <td>{{ $d->nombreCompleto() }}</td>
                                    <td>{{ $d->curp }}</td>
                                    <td>{{ $d->especialidad ?? '—' }}</td>
                                    <td>{{ $d->grado_estudios ?? '—' }}</td>
                                    <td>{{ $d->cedula_profesional ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $d->activo ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $d->activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" wire:click="editar({{ $d->id }})" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn {{ $d->activo ? 'btn-outline-secondary' : 'btn-outline-success' }}" wire:click="alternarActivo({{ $d->id }})" title="{{ $d->activo ? 'Desactivar' : 'Activar' }}">
                                                <i class="bi {{ $d->activo ? 'bi-pause' : 'bi-play' }}"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" wire:click="confirmarEliminacion({{ $d->id }})" title="Eliminar">
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
                    {{ $docentes->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Crear/Editar -->
    <div class="modal fade" tabindex="-1" wire:model="formulario">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $registroId ? 'Editar Docente' : 'Nuevo Docente' }}</h5>
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
                                <input type="text" class="form-control" wire:model="clave" maxlength="20" placeholder="Ej. DOC-001" pattern="[A-Z0-9_-]+" required>
                                @error('clave') <div class="text-danger small">{{ $message }}</div> @enderror
                                <div class="form-text">Solo mayúsculas, números, guión y guion bajo.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">CURP *</label>
                                <input type="text" class="form-control" wire:model="curp" maxlength="18" placeholder="18 caracteres" required>
                                @error('curp') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Nombre(s) *</label>
                                <input type="text" class="form-control" wire:model="nombre" maxlength="150" required>
                                @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required">Apellidos *</label>
                                <input type="text" class="form-control" wire:model="apellidos" maxlength="150" required>
                                @error('apellidos') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">RFC</label>
                                <input type="text" class="form-control" wire:model="rfc" maxlength="13" placeholder="13 caracteres">
                                @error('rfc') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Correo</label>
                                <input type="email" class="form-control" wire:model="correo" maxlength="150" placeholder="docente@uvz.edu.mx">
                                @error('correo') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" wire:model="telefono" maxlength="20" placeholder="10 dígitos">
                                @error('telefono') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Especialidad</label>
                                <input type="text" class="form-control" wire:model="especialidad" maxlength="150" placeholder="Ej. Matemáticas">
                                @error('especialidad') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Grado de estudios</label>
                                <input type="text" class="form-control" wire:model="grado_estudios" maxlength="100" placeholder="Ej. Maestría">
                                @error('grado_estudios') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Cédula profesional</label>
                                <input type="text" class="form-control" wire:model="cedula_profesional" maxlength="50" placeholder="Número de cédula">
                                @error('cedula_profesional') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" wire:model="activo" id="activo">
                                    <label class="form-check-label" for="activo">Activo</label>
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
    @if(session('eliminarDocenteId'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (confirm('¿Eliminar el docente "{{ session('eliminarDocenteNombre') }}"? Esta acción no se puede deshacer.')) {
                    @this.call('eliminar', {{ session('eliminarDocenteId') }})
                }
            });
        </script>
    @endif
</div>