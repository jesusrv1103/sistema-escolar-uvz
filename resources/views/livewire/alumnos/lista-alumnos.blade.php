<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-brand-text">Alumnos</h1>
            <p class="text-sm text-brand-muted">Expediente permanente del alumno (independiente de cada inscripción)</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-brand-primary hover:text-brand-secondary">Dashboard</a>
            @can('alumno.create')
                <a href="{{ route('alumnos.nuevo') }}"
                    class="rounded-md bg-brand-primary px-3 py-2 text-sm font-semibold text-white hover:bg-brand-secondary">Nuevo alumno</a>
            @endcan
        </div>
    </div>

    @if (session('expediente'))
        <div class="mb-4 rounded-md border border-brand-line bg-brand-surface px-4 py-3 text-sm text-brand-text">
            {{ session('expediente') }}
        </div>
    @endif

    <div class="mb-4 rounded-2xl border border-brand-line bg-white p-4 shadow-sm">
        <label for="busqueda" class="block text-sm font-medium text-brand-text">Buscar</label>
        <input id="busqueda" type="search" wire:model.live.debounce.300ms="busqueda"
            placeholder="Nombre, apellidos, CURP o número de control"
            class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
        <p class="mt-1 text-xs text-brand-muted">Máximo 50 resultados. La información médica no aparece en este listado.</p>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-brand-line bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="border-b border-brand-line bg-brand-surface text-left text-xs uppercase text-brand-muted">
                <tr>
                    <th class="px-4 py-3">Núm. control</th>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">CURP</th>
                    <th class="px-4 py-3">Nacimiento</th>
                    <th class="px-4 py-3">Correo / teléfono</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-line">
                @forelse ($alumnos as $alumno)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-brand-primary">{{ $alumno->numero_control ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $alumno->apellidos }}, {{ $alumno->nombre }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $alumno->curp }}</td>
                        <td class="px-4 py-3">{{ $alumno->fecha_nacimiento?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-xs text-brand-muted">
                            {{ $alumno->correo ?? '—' }}<br>{{ $alumno->telefono ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                                <a href="{{ route('alumnos.ver', $alumno) }}"
                                    class="font-medium text-brand-primary hover:underline">
                                    @can('update', $alumno)
                                        Ver / editar expediente
                                    @else
                                        Ver expediente
                                    @endcan
                                </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-brand-muted">
                        {{ $busqueda === '' ? 'Sin alumnos registrados.' : 'Sin resultados para la búsqueda.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
