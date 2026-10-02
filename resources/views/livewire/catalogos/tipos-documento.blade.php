<div>
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-brand-text">Tipos de documento</h1>
            <p class="text-sm text-brand-muted">Solo documentos definidos en la arquitectura aprobada.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-brand-primary hover:text-brand-secondary">Dashboard</a>
            <button type="button" wire:click="crear"
                class="rounded-md bg-brand-primary px-3 py-2 text-sm font-semibold text-white hover:bg-brand-secondary">
                Nuevo tipo
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
            <h2 class="text-sm font-semibold text-brand-text">{{ $registroId ? 'Editar tipo de documento' : 'Nuevo tipo de documento' }}</h2>

            <form wire:submit="guardar" class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="clave" class="block text-sm font-medium text-brand-text">Clave</label>
                    <input id="clave" type="text" wire:model="clave" maxlength="30" placeholder="ACTA_NAC"
                        class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                    @error('clave') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="nombre" class="block text-sm font-medium text-brand-text">Nombre</label>
                    <input id="nombre" type="text" wire:model="nombre" maxlength="120"
                        class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                    @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-end gap-4 pb-1">
                    <label class="flex items-center gap-2 text-sm text-brand-text">
                        <input type="checkbox" wire:model="obligatorio" value="1"> Obligatorio
                    </label>
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
                    <th class="px-4 py-3">Obligatorio</th>
                    <th class="px-4 py-3">Estatus</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-line">
                @forelse ($tipos as $tipo)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-brand-primary">{{ $tipo->clave }}</td>
                        <td class="px-4 py-3">{{ $tipo->nombre }}</td>
                        <td class="px-4 py-3">{{ $tipo->obligatorio ? 'Sí' : 'No' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $tipo->activo ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-600' }}">
                                {{ $tipo->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="editar({{ $tipo->id }})" class="font-medium text-brand-primary hover:underline">Editar</button>
                            <button wire:click="alternarActivo({{ $tipo->id }})" class="ml-3 font-medium text-brand-highlight hover:underline">
                                {{ $tipo->activo ? 'Desactivar' : 'Activar' }}
                            </button>
                            <button wire:click="eliminar({{ $tipo->id }})" wire:confirm="¿Eliminar este tipo? (borrado lógico)"
                                class="ml-3 font-medium text-red-600 hover:underline">Eliminar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-brand-muted">Sin tipos de documento registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
