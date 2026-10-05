<div>
    <x-ui.page-header title="Alumnos"
        description="Expediente permanente del alumno, independiente de cada inscripción.">
        <x-slot:actions>
            @can('alumno.create')
                <x-ui.button href="{{ route('alumnos.nuevo') }}" icon="plus">Nuevo alumno</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('expediente'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('expediente') }}</x-ui.alert></div>
    @endif

    <div class="mt-5">
        <x-ui.filters>
            <div class="grid gap-3 sm:grid-cols-3">
                <x-ui.field label="Buscar" class="sm:col-span-2"
                    hint="Máximo 50 resultados. La información médica no aparece en este listado.">
                    <x-ui.input id="busqueda" type="search" wire:model.live.debounce.300ms="busqueda"
                        placeholder="Nombre, apellidos, CURP o número de control"
                        aria-label="Buscar alumnos" />
                </x-ui.field>
            </div>
        </x-ui.filters>
    </div>

    <div class="mt-4">
        <x-ui.card :flush="count($alumnos) > 0">
            @if (count($alumnos) === 0)
                <x-ui.empty-state icon="users"
                    :title="$busqueda === '' ? 'Sin alumnos registrados' : 'Sin resultados para la búsqueda'"
                    message="Prueba con otro nombre, CURP o número de control.">
                    <x-slot:action>
                        @if ($busqueda !== '')
                            <x-ui.button variant="secondary" wire:click="$set('busqueda', '')">
                                Limpiar búsqueda
                            </x-ui.button>
                        @elseif (auth()->user()->can('alumno.create'))
                            <x-ui.button href="{{ route('alumnos.nuevo') }}" icon="plus">Nuevo alumno</x-ui.button>
                        @endif
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Listado de alumnos</caption>
                        <thead>
                            <tr>
                                <th scope="col">Núm. control</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">CURP</th>
                                <th scope="col">Nacimiento</th>
                                <th scope="col">Correo / teléfono</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($alumnos as $alumno)
                                <tr wire:key="alumno-{{ $alumno->id }}">
                                    <td class="font-semibold text-brand-primary">
                                        {{ $alumno->numero_control ?? '—' }}
                                    </td>
                                    <td class="font-medium text-brand-text">
                                        {{ $alumno->apellidos }}, {{ $alumno->nombre }}
                                    </td>
                                    <td class="font-mono text-xs text-brand-muted">{{ $alumno->curp }}</td>
                                    <td class="text-brand-muted">
                                        {{ $alumno->fecha_nacimiento?->format('d/m/Y') ?? '—' }}
                                    </td>
                                    <td class="text-xs text-brand-muted">
                                        <span class="block">{{ $alumno->correo ?? '—' }}</span>
                                        <span class="block">{{ $alumno->telefono ?? '—' }}</span>
                                    </td>
                                    <td class="text-right">
                                        <x-ui.button size="sm" variant="secondary" icon="arrow-right"
                                            href="{{ route('alumnos.ver', $alumno) }}">
                                            @can('update', $alumno)
                                                Ver / editar expediente
                                            @else
                                                Ver expediente
                                            @endcan
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
