<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">Equivalencias / Revalidaciones</h1>
        <button type="button" class="btn btn-primary" wire:click="abrirModalCrear">
            <i class="bi bi-plus-lg me-1"></i> Nueva Equivalencia
        </button>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" wire:model.debounce.300ms="busqueda" placeholder="Alumno, origen...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estatus</label>
                    <select class="form-select" wire:model="filtroEstatus">
                        <option value="">Todos</option>
                        @foreach(\App\Enums\EstatusEquivalencia::cases() as $e)
                            <option value="{{ $e->value }}">{{ $e->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Listado -->
    <div class="card">
        <div class="card-body p-0">
            @if($equivalencias->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-arrow-left-right display-4"></i>
                    <p class="mt-2">No hay equivalencias registradas.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Alumno</th>
                                <th>Materia UVZ</th>
                                <th>Origen</th>
                                <th>Créditos</th>
                                <th>Estatus</th>
                                <th>Dictamen</th>
                                <th>Autorizada por</th>
                                <th>Fecha</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($equivalencias as $eq)
                                <tr>
                                    <td>{{ $eq->alumno->nombre_completo }}</td>
                                    <td>{{ $eq->materia->clave ?? 'Crédito libre' }} - {{ $eq->materia->nombre ?? 'Sin materia UVZ' }}</td>
                                    <td>{{ Str::limit($eq->origen, 40) }}</td>
                                    <td>{{ $eq->creditos }}</td>
                                    <td>
                                        <span class="badge {{ match($eq->estatus->value) {
                                            'APROBADA' => 'bg-success',
                                            'RECHAZADA' => 'bg-danger',
                                            default => 'bg-warning text-dark'
                                        }}">{{ $eq->estatus->label() }}</span>
                                    </td>
                                    <td>{{ Str::limit($eq->dictamen ?? '', 40) }}</td>
                                    <td>{{ $eq->autorizadaPor?->name ?? '—' }}</td>
                                    <td>{{ $eq->fecha_dictamen?->format('d/m/Y') ?? $eq->created_at->format('d/m/Y') }}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="abrirModalEditar({{ $eq->id }})">
                                            <i class="bi bi-pencil me-1"></i> Editar
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $equivalencias->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Crear/Editar -->
    @if($modalAbierto)
    <div class="modal fade show d-block" tabindex="-1" role="dialog" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $equivalencia ? 'Editar' : 'Registrar' }} Equivalencia</h5>
                    <button type="button" class="btn-close" wire:click="$set('modalAbierto', false)"></button>
                </div>
                <form wire:submit.prevent="guardar">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Alumno</label>
                                <select class="form-select" wire:model="alumno" required>
                                    <option value="">Seleccionar alumno</option>
                                    @foreach(\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                                        <option value="{{ $a->id }}">{{ $a->nombre_completo }} ({{ $a->curp }})</option>
                                    @endforeach
                                </select>
                                @error('alumno') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Materia UVZ (opcional)</label>
                                <select class="form-select" wire:model="materia">
                                    <option value="">Sin materia UVZ (crédito libre)</option>
                                    @foreach(\App\Models\Materia::orderBy('clave')->get() as $m)
                                        <option value="{{ $m->id }}">{{ $m->clave }} - {{ $m->nombre }} ({{ $m->creditos }} créditos)</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Créditos</label>
                                <input type="number" class="form-control" wire:model="creditos" min="0" max="20" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estatus</label>
                                <select class="form-select" wire:model="estatus" required>
                                    @foreach(\App\Enums\EstatusEquivalencia::cases() as $e)
                                        <option value="{{ $e->value }}">{{ $e->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Institución / Origen</label>
                                <input type="text" class="form-control" wire:model="origen" maxlength="500" required placeholder="Nombre de la institución, país, programa, etc.">
                                @error('origen') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Dictamen / Observaciones</label>
                                <textarea class="form-control" wire:model="dictamen" rows="3" maxlength="2000" placeholder="Fundamento técnico, normativa, motivos de aprobación/rechazo..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('modalAbierto', false)">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> {{ $equivalencia ? 'Actualizar' : 'Registrar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>