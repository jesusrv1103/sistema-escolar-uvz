<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">
            Evaluaciones
            @if($grupo)
                <span class="text-muted fw-normal fs-6"> — {{ $grupo->clave }} ({{ $grupo->materia->clave }})</span>
            @endif
        </h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" wire:click="abrirModalCrear" @if(!$grupo) disabled @endif>
                <i class="bi bi-plus-lg me-1"></i> Nueva Evaluación
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" wire:model.debounce.300ms="busqueda" placeholder="Nombre de la evaluación...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Grupo</label>
                    <select class="form-select" wire:model="filtroGrupo">
                        <option value="">Todos</option>
                        @foreach($grupos as $g)
                            <option value="{{ $g->id }}">{{ $g->clave }} - {{ $g->materia->clave }} ({{ $g->periodo->clave }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" wire:model="filtroTipo">
                        <option value="">Todos</option>
                        @foreach($tipos as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card">
        <div class="card-body p-0">
            @if($evaluaciones->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-list-check display-4"></i>
                    <p class="mt-2">No hay evaluaciones registradas{{ $grupo ? ' para este grupo' : '' }}.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Ponderación</th>
                                <th>Fecha</th>
                                <th>Grupo</th>
                                <th>Materia</th>
                                <th>Creada por</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($evaluaciones as $e)
                                <tr>
                                    <td>{{ $e->nombre }}</td>
                                    <td>
                                        <span class="badge bg-info text-dark">{{ $e->tipo }}</span>
                                    </td>
                                    <td>{{ $e->ponderacion }}%</td>
                                    <td>{{ $e->fecha?->format('d/m/Y') }}</td>
                                    <td>{{ $e->grupo->clave ?? '—' }}</td>
                                    <td>{{ $e->grupo->materia->clave ?? '—' }}</td>
                                    <td>{{ $e->creadaPor?->name ?? '—' }}</td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" wire:click="abrirModalEditar({{ $e->id }})" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" wire:click="eliminar({{ $e->id }})" title="Eliminar">
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
                    {{ $evaluaciones->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Crear Evaluación -->
    @livewire('academico.crear-evaluacion')

    <!-- Modal Editar Evaluación -->
    @livewire('academico.editar-evaluacion')
</div>