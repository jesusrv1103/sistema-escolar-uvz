@if($modalBecaAbierto)
<div class="modal fade show d-block" tabindex="-1" role="dialog" style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ $beca ? 'Editar' : 'Crear' }} Beca</h5>
                <button type="button" class="btn-close" wire:click="$set('modalBecaAbierto', false)"></button>
            </div>
            <form wire:submit.prevent="guardarBeca">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Clave</label>
                            <input type="text" class="form-control" wire:model="clave" maxlength="20" required>
                            @error('clave') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" wire:model="nombre" maxlength="150" required>
                            @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" wire:model="tipo" required>
                                @foreach(\App\Enums\TipoBeca::cases() as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Valor</label>
                            <div class="input-group">
                                <input type="number" class="form-control" wire:model="valor" step="0.01" min="0" required>
                                <span class="input-group-text" wire:loading.attr="disabled">
                                    {{ $tipo->value === 'PORCENTAJE' ? '%' : '$' }}
                                </span>
                            </div>
                            @error('valor') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Alcance</label>
                            <select class="form-select" wire:model="alcance" required>
                                @foreach(\App\Enums\AlcanceBeca::cases() as $a)
                                    <option value="{{ $a->value }}">{{ $a->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Periodo</label>
                            <select class="form-select" wire:model="periodo" required>
                                <option value="">Seleccionar periodo</option>
                                @foreach(\App\Models\Periodo::orderBy('fecha_inicio', 'desc')->get() as $p)
                                    <option value="{{ $p->id }}">{{ $p->clave }} - {{ $p->nombre }}</option>
                                @endforeach
                            </select>
                            @error('periodo') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" wire:model="fecha_inicio" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" wire:model="fecha_fin" required>
                            @error('fecha_fin') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" wire:model="activo" id="activo">
                                <label class="form-check-label ms-2" for="activo">Activa</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="$set('modalBecaAbierto', false)">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> {{ $beca ? 'Actualizar' : 'Crear' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif