<div>
    <x-ui.page-header title="Egreso"
        description="Verificación de requisitos de egreso con los datos académicos existentes y registro del egreso. La deuda no es requisito.">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('titulacion.index') }}" icon="academic-cap">
                Ir a Titulación
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ------------------- Situación de egreso del alumno ------------------- --}}
    <div class="mt-5">
        <x-ui.card>
            <x-slot:header>
                <p class="text-sm font-semibold text-brand-text">Situación de egreso</p>
            </x-slot:header>

            @if (session('success'))
                <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
            @endif

            @if ($errors->has('registrar'))
                <x-ui.alert tone="danger">{{ $errors->first('registrar') }}</x-ui.alert>
            @endif

            <div class="grid items-end gap-3 md:grid-cols-3">
                <div class="md:col-span-2">
                    <x-ui.field label="Alumno" for="eg-alumno">
                        <x-ui.select id="eg-alumno" wire:change="seleccionarAlumno($event.target.value)">
                            <option value="">Seleccionar alumno</option>
                            @foreach (\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                                <option value="{{ $a->id }}" @selected($alumno?->getKey() === $a->id)>
                                    {{ $a->nombre_completo }} ({{ $a->numero_control ?? $a->curp }})
                                </option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>

                <div>
                    <x-ui.field label="Estado">
                        @if ($verificacion === null)
                            <x-ui.badge tone="neutral">Sin consulta</x-ui.badge>
                        @elseif ($verificacion['egresado'])
                            <x-ui.badge tone="success">Egresado</x-ui.badge>
                        @else
                            <x-ui.badge tone="warning">No egresado</x-ui.badge>
                        @endif
                    </x-ui.field>
                </div>
            </div>

            @if ($verificacion !== null)
                <div class="mt-5 border-t border-brand-line pt-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-muted">
                        Requisitos evaluables
                    </p>

                    <ul class="mt-3 space-y-2">
                        @foreach ($verificacion['requisitos'] as $requisito)
                            <li class="flex items-start gap-2 text-sm"
                                id="requisito-{{ $requisito['clave'] }}">
                                <span class="mt-0.5 {{ $requisito['cumplido'] ? 'text-emerald-600' : 'text-red-600' }}">
                                    <x-ui.icon :name="$requisito['cumplido'] ? 'check' : 'x-mark'" :size="16" />
                                </span>
                                <span>
                                    <span class="font-medium text-brand-text">{{ $requisito['nombre'] }}</span>
                                    <span class="block text-xs text-brand-muted">{{ $requisito['detalle'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-brand-line pt-4">
                        @if ($verificacion['egresado'])
                            <x-ui.badge tone="success">Egresado</x-ui.badge>
                            <span class="text-sm text-brand-muted">
                                Registrado el {{ $verificacion['egreso']->registrado_at?->format('d/m/Y') ?? '—' }}
                                por {{ $verificacion['egreso']->registradoPor?->name ?? '—' }}
                            </span>
                            <x-ui.button size="sm" variant="secondary" href="{{ route('titulacion.index') }}"
                                icon="arrow-right">Continuar a Titulación</x-ui.button>
                        @elseif ($verificacion['puede_registrar'])
                            <x-ui.button icon="check" wire:click="registrarEgreso"
                                wire:confirm="¿Registrar el egreso del alumno? Esta acción queda en auditoría.">
                                Registrar egreso
                            </x-ui.button>
                            <span class="text-xs text-brand-muted">Todos los requisitos evaluables están cumplidos.</span>
                        @else
                            <x-ui.alert tone="warning">
                                No se puede registrar el egreso. Faltan:
                                {{ implode(', ', $verificacion['faltantes']) }}.
                            </x-ui.alert>
                        @endif
                    </div>
                </div>
            @endif
        </x-ui.card>
    </div>

    {{-- ------------------------- Egresados registrados ------------------------- --}}
    <div class="mt-5">
        <x-ui.filters>
            <div class="grid gap-3 md:grid-cols-3">
                <x-ui.field label="Buscar egresado">
                    <x-ui.input type="text" wire:model.live.debounce.300ms="busqueda"
                        placeholder="Alumno, número de control…" aria-label="Buscar egresados" />
                </x-ui.field>
            </div>
            <div class="mt-3 flex justify-end border-t border-brand-line pt-3">
                <x-ui.button size="sm" variant="ghost" icon="x-mark" wire:click="limpiarFiltros">
                    Limpiar filtros
                </x-ui.button>
            </div>
        </x-ui.filters>

        <div class="mt-4">
            <x-ui.card :flush="count($egresados) > 0">
                @if (count($egresados) === 0)
                    <x-ui.empty-state icon="academic-cap" title="Sin egresos registrados"
                        message="Cuando un alumno cumpla todos los requisitos evaluables aparecerá aquí." />
                @else
                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th scope="col">Alumno</th>
                                    <th scope="col">Plan</th>
                                    <th scope="col" class="text-right">Créditos</th>
                                    <th scope="col">Registrado</th>
                                    <th scope="col">Titulación</th>
                                    <th scope="col" class="text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($egresados as $egreso)
                                    <tr wire:key="egreso-{{ $egreso->id }}" id="egreso-{{ $egreso->id }}">
                                        <td class="font-medium text-brand-text">
                                            {{ $egreso->alumno?->nombre_completo }}
                                            <span class="block text-xs text-brand-muted">
                                                {{ $egreso->alumno?->numero_control ?? '—' }}
                                            </span>
                                        </td>
                                        <td class="text-brand-muted">
                                            {{ $egreso->planVersion?->plan?->clave ?? '—' }}
                                            v{{ $egreso->planVersion?->version ?? '—' }}
                                        </td>
                                        <td class="text-right tabular-nums">
                                            {{ $egreso->creditos_acumulados }} / {{ $egreso->creditos_requeridos }}
                                        </td>
                                        <td class="whitespace-nowrap text-brand-muted">
                                            {{ $egreso->registrado_at?->format('d/m/Y') ?? '—' }}
                                        </td>
                                        <td>
                                            @if ($egreso->titulacion)
                                                <x-ui.badge
                                                    :tone="$egreso->titulacion->esTitulado() ? 'success' : 'info'">
                                                    {{ $egreso->titulacion->estatus->label() }}
                                                </x-ui.badge>
                                            @else
                                                <span class="text-brand-muted">Sin expediente</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <div class="ui-row-actions" role="group"
                                                aria-label="Acciones del egreso de {{ $egreso->alumno?->nombre_completo }}">
                                                <x-ui.icon-button label="Verificar alumno" icon="eye"
                                                    wire:click="seleccionarAlumno({{ $egreso->alumno_id }})" />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-brand-line px-5 py-3">{{ $egresados->links() }}</div>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
