@if($modalAbierto)
<div class="modal fade show d-block" tabindex="-1" role="dialog" style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    {{ $asistenciaExistente ? 'Editar' : 'Registrar' }} Asistencia
                </h5>
                <button type="button" class="btn-close" wire:click="$set('modalAbierto', false)"></button>
            </div>
            <form wire:submit.prevent="guardar">
                <div class="modal-body">
                    <input type="hidden" name="inscripcion_id" value="{{ $inscripcion->id }}">

                    <div class="mb-3">
                        <label class="form-label">Alumno</label>
                        <input type="text" class="form-control" value="{{ $inscripcion->matriculaPeriodo->alumno->nombre_completo }}" readonly>
                        <div class="form-text">{{ $inscripcion->matriculaPeriodo->alumno->numero_control ?? 'Sin número de control' }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" class="form-control" wire:model="fecha" required>
                        @error('fecha') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Asistencia</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" wire:model="presente" id="presente">
                            <label class="form-check-label ms-2" for="presente">
                                {{ $presente ? 'Presente' : 'Ausente' }}
                            </label>
                        </div>
                    </div>

                    @if($asistenciaExistente)
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle me-1"></i>
                            Editando registro existente (ID: {{ $asistenciaExistente->id }})
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="$set('modalAbierto', false)">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif