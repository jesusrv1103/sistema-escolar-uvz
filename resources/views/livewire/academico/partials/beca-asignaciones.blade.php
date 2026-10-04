<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Asignaciones de Becas</h5>
        <button type="button" class="btn btn-primary btn-sm" wire:click="abrirModalAsignacion">
            <i class="bi bi-person-plus me-1"></i> Asignar Beca
        </button>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <input type="text" class="form-control" wire:model.debounce.300ms="busqueda" placeholder="Buscar alumno...">
            </div>
            <div class="col-md-3">
                <select class="form-select" wire:model="filtroEstatus">
                    <option value="">Todos</option>
                    @foreach(\App\Enums\EstatusBeca::cases() as $e)
                        <option value="{{ $e->value }}">{{ $e->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" wire:model="filtroPeriodo">
                    <option value="">Todos</option>
                    @foreach($periodos as $p)
                        <option value="{{ $p->id }}">{{ $p->clave }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-primary w-100" wire:click="abrirModalAsignacion">
                    <i class="bi bi-person-plus me-1"></i> Asignar
                </button>
            </div>
        </div>

        @if($asignaciones->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-person-check display-4"></i>
                <p class="mt-2">No hay asignaciones registradas.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Beca</th>
                            <th>Alumno</th>
                            <th>Periodo</th>
                            <th>Estatus</th>
                            <th>Asignada por</th>
                            <th>Aprobada por</th>
                            <th>Fecha Aprob.</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($asignaciones as $a)
                            <tr>
                                <td>{{ $a->beca->clave }} - {{ $a->beca->nombre }}</td>
                                <td>{{ $a->alumno->nombre_completo }}</td>
                                <td>{{ $a->periodo->clave }}</td>
                                <td>
                                    <span class="badge {{ match($a->estatus->value) {
                                        'APROBADA' => 'bg-success',
                                        'RECHAZADA' => 'bg-danger',
                                        default => 'bg-warning text-dark'
                                    }}">{{ $a->estatus->label() }}</span>
                                </td>
                                <td>{{ $a->asignadaPor?->name ?? '—' }}</td>
                                <td>{{ $a->aprobadaPor?->name ?? '—' }}</td>
                                <td>{{ $a->fecha_aprobacion?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="text-end">
                                    @if($a->esPendiente())
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-success" wire:click="aprobar({{ $a->id }})" title="Aprobar">
                                                <i class="bi bi-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger" wire:click="rechazar({{ $a->id }})" title="Rechazar">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-muted small">Resuelta</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $asignaciones->links() }}
            </div>
        @endif
    </div>
</div>