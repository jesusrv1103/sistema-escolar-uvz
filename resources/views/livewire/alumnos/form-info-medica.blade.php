<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-brand-text">Información médica — {{ $alumno->apellidos }}, {{ $alumno->nombre }}</h1>
            <p class="text-sm text-brand-muted">Acceso restringido (RB-18): solo ADMIN y ADMISIONES.</p>
        </div>
        <a href="{{ route('alumnos.ver', $alumno) }}" class="text-sm font-medium text-brand-primary hover:text-brand-secondary">Volver al expediente</a>
    </div>

    @if (session('expediente'))
        <div class="mb-4 rounded-md border border-brand-line bg-brand-surface px-4 py-3 text-sm text-brand-text">
            {{ session('expediente') }}
        </div>
    @endif

    <div class="rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
        <div class="flex items-start gap-2 rounded-md bg-brand-surface px-4 py-3 text-xs text-brand-muted">
            <span aria-hidden="true">🔒</span>
            <p>Información sensible: no aparece en listados, búsquedas, dashboard ni logs. Se guarda con tu usuario como responsable de la actualización.</p>
        </div>

        <form wire:submit="guardar" class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="tipo_sangre" class="block text-sm font-medium text-brand-text">Tipo de sangre</label>
                <select id="tipo_sangre" wire:model="tipo_sangre"
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                    <option value="">No capturado</option>
                    @foreach ($tiposSangre as $valor)
                        <option value="{{ $valor }}">{{ $valor }}</option>
                    @endforeach
                </select>
                @error('tipo_sangre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-end gap-4 pb-2">
                <label class="flex items-center gap-2 text-sm text-brand-text">
                    <input type="checkbox" wire:model.live="tiene_discapacidad" value="1"> Presenta discapacidad
                </label>
            </div>
            @error('tiene_discapacidad') <p class="mt-1 text-xs text-red-600 sm:col-span-2">{{ $message }}</p> @enderror

            <div class="sm:col-span-2">
                <label for="alergias" class="block text-sm font-medium text-brand-text">Alergias</label>
                <textarea id="alergias" wire:model="alergias" rows="2" maxlength="2000" placeholder="Ninguna / describir"
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"></textarea>
                @error('alergias') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="condiciones" class="block text-sm font-medium text-brand-text">Condiciones médicas previas</label>
                <textarea id="condiciones" wire:model="condiciones" rows="2" maxlength="2000"
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"></textarea>
                @error('condiciones') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="medicamentos" class="block text-sm font-medium text-brand-text">Medicamentos actuales</label>
                <textarea id="medicamentos" wire:model="medicamentos" rows="2" maxlength="2000"
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"></textarea>
                @error('medicamentos') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            @if ($tiene_discapacidad)
                <div class="sm:col-span-2">
                    <label for="detalle_discapacidad" class="block text-sm font-medium text-brand-text">Detalle de la discapacidad</label>
                    <textarea id="detalle_discapacidad" wire:model="detalle_discapacidad" rows="2" maxlength="2000"
                        class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"></textarea>
                    @error('detalle_discapacidad') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="flex gap-2 sm:col-span-2">
                <button type="submit"
                    class="rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-secondary">Guardar</button>
            </div>
        </form>
    </div>
</div>
