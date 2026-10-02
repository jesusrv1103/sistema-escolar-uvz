<div>
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-brand-text">Periodos</h1>
            <p class="text-sm text-brand-muted">El cierre (<code class="text-xs">cerrado_at</code>) bloquea inscripciones, no operaciones académicas.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-brand-primary hover:text-brand-secondary">Dashboard</a>
            <button type="button" wire:click="crear"
                class="rounded-md bg-brand-primary px-3 py-2 text-sm font-semibold text-white hover:bg-brand-secondary">
                Nuevo periodo
            </button>
        </div>
    </div>

    @if (session('catalogo'))
        <div class="mb-4 rounded-md border border-brand-line bg-brand-surface px-4 py-3 text-sm text-brand-text">
            {{ session('catalogo') }}
        </div>
    @endif

    @if ($formulario)
        <div class="mb-6 rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-brand-text">{{ $registroId ? 'Editar periodo' : 'Nuevo periodo' }}</h2>

            <form wire:submit="guardar" class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="clave" class="block text-sm font-medium text-brand-text">Clave</label>
                    <input id="clave" type="text" wire:model="clave" maxlength="20" placeholder="2026-1"
                        class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                    @error('clave') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="nombre" class="block text-sm font-medium text-brand-text">Nombre</label>
                    <input id="nombre" type="text" wire:model="nombre" maxlength="100"
                        class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                    @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="fecha_inicio" class="block text-sm font-medium text-brand-text">Fecha de inicio</label>
                    <input id="fecha_inicio" type="date" wire:model="fecha_inicio"
                        class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                    @error('fecha_inicio') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="fecha_fin" class="block text-sm font-medium text-brand-text">Fecha de fin</label>
                    <input id="fecha_fin" type="date" wire:model="fecha_fin"
                        class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                    @error('fecha_fin') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-end gap-4 pb-1">
                    <label class="flex items-center gap-2 text-sm text-brand-text">
                        <input type="checkbox" wire:model="activo" value="1"> Activo
                    </label>
                </div>

                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit"
                        class="rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-secondary">Guardar</button>
                    <button type="button" wire:click="cancelar"
                        class="rounded-md border border-brand-line px-4 py-2 text-sm font-medium text-brand-text hover:bg-brand-surface">Cancelar</button>
                </div>
            </form>
        </div>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-brand-line bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="border-b border-brand-line bg-brand-surface text-left text-xs uppercase text-brand-muted">
                <tr>
                    <th class="px-4 py-3">Clave</th>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Inicio</th>
                    <th class="px-4 py-3">Fin</th>
                    <th class="px-4 py-3">Estatus</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-line">
                @forelse ($periodos as $periodo)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-brand-primary">{{ $periodo->clave }}</td>
                        <td class="px-4 py-3">{{ $periodo->nombre }}</td>
                        <td class="px-4 py-3">{{ $periodo->fecha_inicio?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $periodo->fecha_fin?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $periodo->activo ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-600' }}">
                                {{ $periodo->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                            @if ($periodo->cerrado_at)
                                <span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">Cerrado</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="editar({{ $periodo->id }})" class="font-medium text-brand-primary hover:underline">Editar</button>
                            <button wire:click="alternarActivo({{ $periodo->id }})" class="ml-3 font-medium text-brand-highlight hover:underline">
                                {{ $periodo->activo ? 'Desactivar' : 'Activar' }}
                            </button>
                            @if ($periodo->cerrado_at)
                                <button wire:click="reabrir({{ $periodo->id }})" class="ml-3 font-medium text-brand-secondary hover:underline">Reabrir</button>
                            @else
                                <button wire:click="cerrar({{ $periodo->id }})" class="ml-3 font-medium text-brand-secondary hover:underline">Cerrar</button>
                            @endif
                            <button wire:click="eliminar({{ $periodo->id }})" wire:confirm="¿Eliminar este periodo? (borrado lógico)"
                                class="ml-3 font-medium text-red-600 hover:underline">Eliminar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-brand-muted">Sin periodos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
