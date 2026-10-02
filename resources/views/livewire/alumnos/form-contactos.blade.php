<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-brand-text">Contactos — {{ $alumno->apellidos }}, {{ $alumno->nombre }}</h1>
            <p class="text-sm text-brand-muted">Tutor y contacto de emergencia pueden ser personas distintas.</p>
        </div>
        <a href="{{ route('alumnos.ver', $alumno) }}" class="text-sm font-medium text-brand-primary hover:text-brand-secondary">Volver al expediente</a>
    </div>

    @if (session('expediente'))
        <div class="mb-4 rounded-md border border-brand-line bg-brand-surface px-4 py-3 text-sm text-brand-text">
            {{ session('expediente') }}
        </div>
    @endif

    @if (! $puedeEditar)
        <div class="mb-4 rounded-md border border-brand-line bg-brand-surface px-4 py-3 text-sm text-brand-muted">
            Solo lectura: tu rol no puede modificar los contactos.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach (['tutor' => 'Contacto de tutor', 'emergencia' => 'Contacto de emergencia'] as $propiedad => $titulo)
            <div class="rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-brand-text">{{ $titulo }}</h2>

                <form wire:submit="guardar{{ $propiedad === 'tutor' ? 'Tutor' : 'Emergencia' }}" class="mt-4 space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-brand-text">Nombre completo</label>
                        <input type="text" wire:model="{{ $propiedad }}.nombre" maxlength="150" @disabled(! $puedeEditar)
                            class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                        @error($propiedad.'.nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-brand-text">Parentesco</label>
                            <input type="text" wire:model="{{ $propiedad }}.parentesco" maxlength="60" placeholder="Madre, Padre…"
                                @disabled(! $puedeEditar)
                                class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                            @error($propiedad.'.parentesco') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-brand-text">Teléfono</label>
                            <input type="text" wire:model="{{ $propiedad }}.telefono" maxlength="20" placeholder="5512345678"
                                @disabled(! $puedeEditar)
                                class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                            @error($propiedad.'.telefono') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-brand-text">Teléfono alterno</label>
                            <input type="text" wire:model="{{ $propiedad }}.telefono_alt" maxlength="20" @disabled(! $puedeEditar)
                                class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                            @error($propiedad.'.telefono_alt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-brand-text">Correo</label>
                            <input type="email" wire:model.blur="{{ $propiedad }}.correo" maxlength="150" @disabled(! $puedeEditar)
                                class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                            @error($propiedad.'.correo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-brand-text">Notas</label>
                        <textarea wire:model="{{ $propiedad }}.notas" rows="2" @disabled(! $puedeEditar)
                            class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary"></textarea>
                        @error($propiedad.'.notas') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($puedeEditar)
                        <div class="flex gap-2 pt-1">
                            <button type="submit"
                                class="rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-secondary">Guardar</button>
                            @if ($ids[$propiedad] ?? null)
                                <button type="button" wire:click="eliminar({{ $ids[$propiedad] }})"
                                    wire:confirm="¿Eliminar este contacto?"
                                    class="rounded-md border border-brand-line px-4 py-2 text-sm font-medium text-red-600 hover:bg-brand-surface">Eliminar</button>
                            @endif
                        </div>
                    @endif
                </form>
            </div>
        @endforeach
    </div>
</div>
