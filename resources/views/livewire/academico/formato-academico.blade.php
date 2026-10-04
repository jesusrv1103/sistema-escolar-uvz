<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0">Formatos Académicos II</h1>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Generar Formato Académico</h5>
        </div>
        <div class="card-body">
            <form wire:submit.prevent="generarFormato">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tipo de Formato</label>
                        <select class="form-select" wire:model="tipo" required>
                            @foreach($tipos as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('tipo') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Alumno</label>
                        <select class="form-select" wire:model="alumno" required>
                            <option value="">Seleccionar alumno</option>
                            @foreach(\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                                <option value="{{ $a->id }}">{{ $a->nombre_completo }} ({{ $a->curp }})</option>
                            @endforeach
                        </select>
                        @error('alumno') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Acta</label>
                        <select class="form-select" wire:model="acta">
                            <option value="">Seleccionar acta (solo para Acta de Calificaciones)</option>
                            @foreach(\App\Models\Acta::with('grupo.materia')->orderByDesc('created_at')->get() as $a)
                                <option value="{{ $a->id }}">{{ $a->grupo->clave }} - {{ $a->grupo->materia->nombre }} ({{ $a->periodo->clave }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Generar PDF
                    </button>
                    <button type="button" class="btn btn-secondary" wire:click="$reset('tipo', 'alumno', 'acta')">
                        <i class="bi bi-x-circle me-1"></i> Limpiar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Descarga directa para testing -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
</div>