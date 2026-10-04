<div class="modal fade" tabindex="-1" wire:model="modalAbierto">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Evaluación</h5>
                <button type="button" class="btn-close" wire:click="cerrarModal"></button>
            </div>
            <form wire:submit="guardar">
                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <input type="hidden" wire:model="grupoId">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label required">Nombre *</label>
                            <input type="text" class="form-control" wire:model="nombre" maxlength="100" placeholder="Ej. Primer Parcial, Examen Final, Proyecto Integrador" required>
                            @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
                            <div class="form-text">Ej. Primer Parcial, Examen Final, Proyecto Integrador</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">Tipo *</label>
                            <select class="form-select" wire:model="tipo" required>
                                <option value="">Seleccione un tipo</option>
                                @foreach($tipos as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                            @error('tipo') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">Ponderación (%) *</label>
                            <input type="number" class="form-control" wire:model="ponderacion" min="1" max="100" required>
                            @error('ponderacion') <div class="text-danger small">{{ $message }}</div> @enderror
                            <div class="form-text">Entre 1 y 100. Suma total no puede exceder 100%.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">Fecha *</label>
                            <input type="date" class="form-control" wire:model="fecha" required>
                            @error('fecha') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" wire:model="descripcion" rows="3" maxlength="1000" placeholder="Descripción opcional de la evaluación..."></textarea>
                            @error('descripcion') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="cerrarModal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">{{ $modalAbierto ? 'Crear' : 'Guardar' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>