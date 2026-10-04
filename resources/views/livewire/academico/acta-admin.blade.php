<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">Actas de Calificaciones</h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" wire:click="abrirModalCrear">
                <i class="bi bi-plus-lg me-1"></i> Nueva Acta
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" wire:model.debounce.300ms="busqueda" placeholder="Clave de grupo...">
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
                <div class="col-md-2">
                    <label class="form-label">Estatus</label>
                    <select class="form-select" wire:model="filtroEstatus">
                        <option value="">Todos</option>
                        <option value="ABIERTA">Abierta</option>
                        <option value="CERRADA">Cerrada</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card">
        <div class="card-body p-0">
            @if($actas->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-text display-4"></i>
                    <p class="mt-2">No hay actas registradas.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Grupo</th>
                                <th>Materia</th>
                                <th>Periodo</th>
                                <th>Estatus</th>
                                <th>Folio</th>
                                <th>Cerrada por</th>
                                <th>Cerrada el</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($actas as $acta)
                                <tr>
                                    <td>{{ $acta->grupo->clave }}</td>
                                    <td>{{ $acta->grupo->materia->clave }} - {{ $acta->grupo->materia->nombre }}</td>
                                    <td>{{ $acta->periodo->clave }}</td>
                                    <td>
                                        <span class="badge {{ $acta->esCerrada() ? 'bg-success' : 'bg-warning text-dark' }}">
                                            {{ $acta->estatus }}
                                        </span>
                                    </td>
                                    <td>{{ $acta->folio ?? '<span class="text-muted">—</span>' }}</td>
                                    <td>{{ $acta->cerradaPor?->name ?? '—' }}</td>
                                    <td>{{ $acta->cerrada_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            @if($acta->esAbierta())
                                                <button type="button" class="btn btn-success" wire:click="cerrar({{ $acta->id }})" title="Cerrar acta">
                                                    <i class="bi bi-check-circle me-1"></i> Cerrar
                                                </button>
                                            @else
                                                <span class="text-muted small">Acta cerrada (inmutable)</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $actas->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Crear Acta -->
    @livewire('academico.crear-acta')
</div>