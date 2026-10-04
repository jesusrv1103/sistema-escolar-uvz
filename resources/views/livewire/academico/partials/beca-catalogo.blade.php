<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Catálogo de Becas</h5>
        <button type="button" class="btn btn-primary btn-sm" wire:click="abrirModalBeca">
            <i class="bi bi-plus-lg me-1"></i> Nueva Beca
        </button>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <input type="text" class="form-control" wire:model.debounce.300ms="busqueda" placeholder="Buscar por nombre o clave...">
            </div>
            <div class="col-md-3">
                <select class="form-select" wire:model="filtroEstatus">
                    <option value="">Todos los estatus</option>
                    <option value="ACTIVA">Activas</option>
                    <option value="INACTIVA">Inactivas</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" wire:model="filtroPeriodo">
                    <option value="">Todos los periodos</option>
                    @foreach($periodos as $p)
                        <option value="{{ $p->id }}">{{ $p->clave }} - {{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($becas->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-award display-4"></i>
                <p class="mt-2">No hay becas registradas.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Clave</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                            <th>Alcance</th>
                            <th>Periodo</th>
                            <th>Vigencia</th>
                            <th>Estatus</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($becas as $b)
                            <tr>
                                <td>{{ $b->clave }}</td>
                                <td>{{ $b->nombre }}</td>
                                <td>
                                    <span class="badge {{ $b->tipo->value === 'PORCENTAJE' ? 'bg-info text-dark' : 'bg-primary' }}">{{ $b->tipo->label() }}</span>
                                </td>
                                <td>{{ $b->tipo->value === 'PORCENTAJE' ? $b->valor . '%' : '$' . number_format($b->valor, 2) }}</td>
                                <td>{{ $b->alcance->label() }}</td>
                                <td>{{ $b->periodo->clave }}</td>
                                <td>{{ $b->fecha_inicio?->format('d/m/Y') }} - {{ $b->fecha_fin?->format('d/m/Y') }}</td>
                                <td>
                                    <span class="badge {{ $b->activo ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $b->activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" wire:click="abrirModalEditar({{ $b->id }})" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $becas->links() }}
            </div>
        @endif
    </div>
</div>