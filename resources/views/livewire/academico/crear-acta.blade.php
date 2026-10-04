@if($modalAbierto)
<div class="modal fade show d-block" tabindex="-1" role="dialog" style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crear Acta de Calificaciones</h5>
                <button type="button" class="btn-close" wire:click="$set('modalAbierto', false)"></button>
            </div>
            <form wire:submit.prevent="crear">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Periodo</label>
                        <select class="form-select" wire:model="periodo" required>
                            <option value="">Seleccionar periodo</option>
                            @foreach($periodos as $p)
                                <option value="{{ $p->id }}">{{ $p->clave }} - {{ $p->nombre }}</option>
                            @endforeach
                        </select>
                        @error('periodo') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Grupo</label>
                        <select class="form-select" wire:model="grupo" required>
                            <option value="">Seleccionar grupo</option>
                            @foreach($grupos as $g)
                                <option value="{{ $g->id }}">{{ $g->clave }} - {{ $g->materia->clave }} ({{ $g->materia->nombre }})</option>
                            @endforeach
                        </select>
                        @error('grupo') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle me-1"></i>
                        El acta se crea en estado <strong>ABIERTA</strong> sin folio.
                        El folio <strong>ACT-YYYY-########</strong> se genera automáticamente
                        al cerrar el acta (four-eyes: DOCENTE captura, CONTROL_ESCOLAR cierra).
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="$set('modalAbierto', false)">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Crear acta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif