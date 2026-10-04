@if($modalAsignacionAbierto)
<div class="modal fade show d-block" tabindex="-1" role="dialog" style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Asignar Beca</h5>
                <button type="button" class="btn-close" wire:click="$set('modalAsignacionAbierto', false)"></button>
            </div>
            <form wire:submit.prevent="asignarBeca">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Beca</label>
                            <select class="form-select" wire:model="becaAsignar" required>
                                <option value="">Seleccionar beca</option>
                                @foreach(\App\Models\Beca::where('activo', true)->get() as $b)
                                    <option value="{{ $b->id }}">{{ $b->clave }} - {{ $b->nombre }} ({{ $b->tipo->label() }}: {{ $b->tipo->value === 'PORCENTAJE' ? $b->valor . '%' : '$' . number_format($b->valor, 2) }})</option>
                                @endforeach
                            </select>
                            @error('becaAsignar') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alumno</label>
                            <select class="form-select" wire:model="alumnoAsignar" required>
                                <option value="">Seleccionar alumno</option>
                                @foreach(\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                                    <option value="{{ $a->id }}">{{ $a->nombre_completo }} ({{ $a->curp }})</option>
                                @endforeach
                            </select>
                            @error('alumnoAsignar') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Periodo</label>
                            <select class="form-select" wire:model="periodoAsignar" required>
                                <option value="">Seleccionar periodo</option>
                                @foreach(\App\Models\Periodo::where('activo', true)->orderByDesc('fecha_inicio')->get() as $p)
                                    <option value="{{ $p->id }}">{{ $p->clave }} - {{ $p->nombre }}</option>
                                @endforeach
                            </select>
                            @error('periodoAsignar') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observación</label>
                            <textarea class="form-control" wire:model="observacion" rows="3" maxlength="1000" placeholder="Motivo de la asignación, condiciones especiales..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="$set('modalAsignacionAbierto', false)">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-plus me-1"></i> Asignar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif