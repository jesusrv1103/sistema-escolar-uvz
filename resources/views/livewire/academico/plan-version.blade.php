<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">
            Versiones de Plan
            @if($plan)
                <span class="text-muted fw-normal fs-6"> — {{ $plan->nombre }} ({{ $plan->clave }})</span>
            @endif
        </h1>
        <button type="button" class="btn btn-primary" wire:click="abrirModalCrear"
            @if(!$plan) disabled @endif>
            <i class="bi bi-plus-lg me-1"></i> Nueva Versión
        </button>
    </div>

    @if(!$plan)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Seleccione un plan de estudio desde el listado principal para gestionar sus versiones.
        </div>
    @endif

    <!-- Tabla -->
    <div class="card">
        <div class="card-body p-0">
            @if($versiones->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-journal-text display-4"></i>
                    <p class="mt-2">No hay versiones registradas{{ $plan ? ' para este plan' : '' }}.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Versión</th>
                                <th>Fecha Vigencia</th>
                                <th>Estado</th>
                                <th>Aprobado por</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($versiones as $v)
                                <tr>
                                    <td>{{ $v->version }}</td>
                                    <td>{{ $v->fecha_vigencia?->format('d/m/Y') ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $v->estatus->labelClass() }}">
                                            {{ $v->estatus->label() ?? $v->estatus }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($v->aprobadoPor)
                                            {{ $v->aprobadoPor->name }}<br>
                                            <small class="text-muted">{{ $v->aprobado_at?->format('d/m/Y H:i') }}</small>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            @if($v->esPropuesta())
                                                <button type="button" class="btn btn-outline-primary" wire:click="abrirModalEditar({{ $v->id }})" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-success" wire:click="abrirModalAprobar({{ $v->id }})" title="Aprobar (Propuesta → Vigente)">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" wire:click="confirmarEliminacion({{ $v->id }})" title="Eliminar">
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
                    {{ $versiones->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Crear/Editar -->
    <div class="modal fade" tabindex="-1" wire:model="modalAbierto">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $versionEnEdicion ? 'Editar Versión' : 'Nueva Versión' }}</h5>
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
                                <label class="form-label required">Versión *</label>
                                <input type="text" class="form-control" wire:model="version" maxlength="20" placeholder="Ej. 1.0, 2024-1" required>
                                @error('version') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Fecha de Vigencia</label>
                                <input type="date" class="form-control" wire:model="fechaVigencia">
                                <div class="form-text">Opcional para Propuesta; obligatoria al aprobar.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="cerrarModal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">{{ $versionEnEdicion ? 'Actualizar' : 'Crear' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Aprobar -->
    <div class="modal fade" tabindex="-1" wire:model="modalAprobarAbierto">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Confirmar Aprobación</h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="cerrarModalAprobar"></button>
                </div>
                <div class="modal-body">
                    <p>¿Aprobar la versión <strong>{{ $versionAAprobar->version ?? '' }}</strong>?</p>
                    <p class="text-muted">Esto cambiará su estado de <span class="badge bg-warning text-dark">Propuesta</span> a <span class="badge bg-success">Vigente</span>.</p>
                    @if($versionAAprobar?->fecha_vigencia)
                        <p>Fecha de vigencia: <strong>{{ $versionAAprobar->fecha_vigencia->format('d/m/Y') }}</strong></p>
                    @endif
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Si ya existe una versión Vigente para este plan, pasará automáticamente a <span class="badge bg-secondary">Superada</span>.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="cerrarModalAprobar">Cancelar</button>
                    <button type="button" class="btn btn-success" wire:click="confirmarAprobar">
                        <i class="bi bi-check-circle me-1"></i> Aprobar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>