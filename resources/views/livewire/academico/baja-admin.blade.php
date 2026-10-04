<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">Bajas y Equivalencias</h1>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" wire:model.debounce.300ms="busqueda" placeholder="Alumno, motivo...">
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

    <!-- Botones de acción -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                @foreach(\App\Enums\TipoBaja::cases() as $tipo)
                    <button type="button" class="btn btn-outline-primary" wire:click="abrirModalBaja('{{ $tipo->value }}')">
                        <i class="bi bi-dash-circle me-1"></i> {{ $tipo->label() }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Listado de bajas -->
    <div class="card">
        <div class="card-body p-0">
            @if($bajas->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-slash-circle display-4"></i>
                    <p class="mt-2">No hay bajas registradas.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Alumno</th>
                                <th>Materia</th>
                                <th>Motivo</th>
                                <th>Registrado por</th>
                                <th>Estatus</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bajas as $baja)
                                <tr>
                                    <td>{{ $baja->fecha->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="badge {{ match($baja->tipo->value) {
                                            'TEMPORAL' => 'bg-warning text-dark',
                                            'DEFINITIVA' => 'bg-danger',
                                            'MATERIA' => 'bg-info text-dark',
                                            default => 'bg-secondary'
                                        }}">{{ $baja->tipo->label() }}</span>
                                    </td>
                                    <td>{{ $baja->alumno->nombre_completo }}</td>
                                    <td>{{ $baja->inscripcionMateria?->materia->clave ?? '—' }}</td>
                                    <td>{{ Str::limit($baja->motivo, 50) }}</td>
                                    <td>{{ $baja->registradoPor?->name ?? '—' }}</td>
                                    <td>
                                        @if($baja->esTemporal())
                                            @if($baja->tieneReingreso())
                                                <span class="badge bg-success">Reingresado</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Vigente</span>
                                            @endif
                                        @elseif($baja->esDefinitiva())
                                            <span class="badge bg-danger">Definitiva</span>
                                        @else
                                            <span class="badge bg-info text-dark">Materia</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($baja->esTemporal() && !$baja->tieneReingreso())
                                            <button type="button" class="btn btn-sm btn-success" wire:click="abrirModalReingreso({{ $baja->id }})">
                                                <i class="bi bi-door-open me-1"></i> Reingreso
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $bajas->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Registrar Baja -->
    @if($modalBajaAbierto)
    <div class="modal fade show d-block" tabindex="-1" role="dialog" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Baja {{ $tipoBaja }}</h5>
                    <button type="button" class="btn-close" wire:click="$set('modalBajaAbierto', false)"></button>
                </div>
                <form wire:submit.prevent="registrarBaja">
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
                            @if($tipoBaja === 'MATERIA')
                            <div class="col-md-6">
                                <label class="form-label">Materia (Inscripción)</label>
                                <select class="form-select" wire:model="inscripcion" required>
                                    <option value="">Seleccionar inscripción</option>
                                    @foreach(\App\Models\InscripcionMateria::with('grupo.materia', 'matriculaPeriodo.alumno')->where('estatus', '!=', 'BAJA')->get() as $i)
                                        <option value="{{ $i->id }}">{{ $i->matriculaPeriodo->alumno->nombre_completo }} - {{ $i->grupo->materia->clave }}</option>
                                    @endforeach
                                </select>
                                @error('inscripcion') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            @endif
                            <div class="col-md-6">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" wire:model="fecha" required max="{{ now()->format('Y-m-d') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Motivo</label>
                                <textarea class="form-control" wire:model="motivo" rows="3" required minlength="5" maxlength="2000" placeholder="Motivo de la baja (mínimo 5 caracteres, máximo 2000)"></textarea>
                                @error('motivo') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('modalBajaAbierto', false)">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Registrar Baja
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- Modal Reingreso -->
    @if($modalReingresoAbierto)
    <div class="modal fade show d-block" tabindex="-1" role="dialog" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Autorizar Reingreso</h5>
                    <button type="button" class="btn-close" wire:click="$set('modalReingresoAbierto', false)"></button>
                </div>
                <form wire:submit.prevent="autorizarReingreso">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-1"></i>
                            Autorizando reingreso para <strong>{{ $baja?->alumno->nombre_completo }}</strong> (Baja {{ $baja?->tipo->label() }} del {{ $baja?->fecha->format('d/m/Y') }}).
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fecha y hora de reingreso</label>
                            <input type="datetime-local" class="form-control" wire:model="reingresoAt" required max="{{ now()->format('Y-m-d\TH:i') }}">
                            @error('reingresoAt') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('modalReingresoAbierto', false)">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-door-open me-1"></i> Autorizar Reingreso
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>