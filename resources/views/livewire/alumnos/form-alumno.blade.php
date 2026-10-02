<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-brand-text">
                {{ $alumno ? 'Expediente: '.$alumno->apellidos.', '.$alumno->nombre : 'Nuevo alumno' }}
            </h1>
            <p class="text-sm text-brand-muted">
                Datos personales
                @if ($alumno && $alumno->numero_control)
                    · Núm. control <span class="font-semibold text-brand-primary">{{ $alumno->numero_control }}</span>
                @elseif($alumno)
                    · Núm. control: pendiente (se asigna al finalizar la primera inscripción)
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('alumnos.lista') }}" class="text-sm font-medium text-brand-primary hover:text-brand-secondary">Volver a alumnos</a>
        </div>
    </div>

    @if (session('expediente'))
        <div class="mb-4 rounded-md border border-brand-line bg-brand-surface px-4 py-3 text-sm text-brand-text">
            {{ session('expediente') }}
        </div>
    @endif

    @if ($alumno)
        <div class="mb-6 flex flex-wrap gap-2 text-sm">
            <a href="{{ route('alumnos.contactos', $alumno) }}"
                class="rounded-md border border-brand-line px-3 py-1.5 font-medium text-brand-primary hover:bg-brand-surface">Contactos</a>
            @can('informacionMedica.edit')
                <a href="{{ route('alumnos.info-medica', $alumno) }}"
                    class="rounded-md border border-brand-line px-3 py-1.5 font-medium text-brand-primary hover:bg-brand-surface">Información médica</a>
            @endcan
        </div>
    @endif

    <div class="rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-brand-text">Datos personales</h2>
        @if (! $puedeEditar)
            <p class="mt-1 text-xs text-brand-muted">Solo lectura: tu rol no puede modificar el expediente.</p>
        @endif

        <form wire:submit="guardar" class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="nombre" class="block text-sm font-medium text-brand-text">Nombre(s)</label>
                <input id="nombre" type="text" wire:model="nombre" maxlength="100" @disabled(! $puedeEditar)
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="apellidos" class="block text-sm font-medium text-brand-text">Apellidos</label>
                <input id="apellidos" type="text" wire:model="apellidos" maxlength="100" @disabled(! $puedeEditar)
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                @error('apellidos') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="curp" class="block text-sm font-medium text-brand-text">CURP</label>
                <input id="curp" type="text" wire:model.blur="curp" maxlength="18" placeholder="18 caracteres"
                    @disabled(! $puedeEditar)
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 font-mono text-sm uppercase focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                <p class="mt-1 text-xs text-brand-muted">Validación sintáctica local (sin consulta RENAPO).</p>
                @error('curp') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="fecha_nacimiento" class="block text-sm font-medium text-brand-text">Fecha de nacimiento</label>
                <input id="fecha_nacimiento" type="date" wire:model="fecha_nacimiento" @disabled(! $puedeEditar)
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                @error('fecha_nacimiento') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="sexo" class="block text-sm font-medium text-brand-text">Sexo</label>
                <select id="sexo" wire:model="sexo" @disabled(! $puedeEditar)
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                    <option value="">Selecciona…</option>
                    @foreach ($sexos as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
                @error('sexo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="nacionalidad" class="block text-sm font-medium text-brand-text">Nacionalidad</label>
                <input id="nacionalidad" type="text" wire:model="nacionalidad" maxlength="60" @disabled(! $puedeEditar)
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                @error('nacionalidad') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="correo" class="block text-sm font-medium text-brand-text">Correo electrónico</label>
                <input id="correo" type="email" wire:model.blur="correo" maxlength="150" @disabled(! $puedeEditar)
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                @error('correo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="telefono" class="block text-sm font-medium text-brand-text">Teléfono</label>
                <input id="telefono" type="text" wire:model="telefono" maxlength="20" placeholder="5512345678" @disabled(! $puedeEditar)
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                @error('telefono') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2 border-t border-brand-line pt-4">
                <h3 class="text-xs font-semibold uppercase text-brand-muted">Domicilio</h3>
            </div>

            <div>
                <label for="calle" class="block text-sm font-medium text-brand-text">Calle</label>
                <input id="calle" type="text" wire:model="calle" maxlength="150" @disabled(! $puedeEditar)
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                @error('calle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="colonia" class="block text-sm font-medium text-brand-text">Colonia</label>
                <input id="colonia" type="text" wire:model="colonia" maxlength="120" @disabled(! $puedeEditar)
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                @error('colonia') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="cp" class="block text-sm font-medium text-brand-text">Código postal</label>
                <input id="cp" type="text" wire:model="cp" maxlength="10" placeholder="98000" @disabled(! $puedeEditar)
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                @error('cp') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="municipio" class="block text-sm font-medium text-brand-text">Municipio</label>
                <input id="municipio" type="text" wire:model="municipio" maxlength="120" @disabled(! $puedeEditar)
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                @error('municipio') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="estado" class="block text-sm font-medium text-brand-text">Estado</label>
                <input id="estado" type="text" wire:model="estado" maxlength="120" @disabled(! $puedeEditar)
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                @error('estado') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="pais" class="block text-sm font-medium text-brand-text">País</label>
                <input id="pais" type="text" wire:model="pais" maxlength="80" @disabled(! $puedeEditar)
                    class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                @error('pais') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            @if ($puedeEditar)
                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit"
                        class="rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-secondary">Guardar</button>
                </div>
            @endif
        </form>
    </div>
</div>
