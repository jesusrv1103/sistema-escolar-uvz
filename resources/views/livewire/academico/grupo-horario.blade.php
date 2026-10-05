<div>
    <x-ui.page-header title="Horarios de grupo"
        :description="$grupo
            ? $grupo->clave . ' · ' . $grupo->materia?->clave . ' · asignación semanal de días y horas.'
            : 'Selecciona un grupo desde el listado principal para gestionar sus horarios.'">
        <x-slot:actions>
            <x-ui.button wire:click="abrirModalCrear" icon="plus" :disabled="!$grupo">Nuevo horario</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (! $grupo)
        <div class="mt-4">
            <x-ui.alert tone="warning">
                Selecciona un grupo desde el listado principal para gestionar sus horarios.
            </x-ui.alert>
        </div>
    @endif

    @if (session('success'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('success') }}</x-ui.alert></div>
    @endif

    <div class="mt-5">
        <x-ui.filters>
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" wire:click="limpiarFiltros">Limpiar filtros</x-ui.button>
            </x-slot:actions>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.field label="Buscar día">
                    <x-ui.input type="search" wire:model.debounce.300ms="busqueda"
                        placeholder="Día…" aria-label="Buscar horarios" />
                </x-ui.field>

                <x-ui.field label="Día">
                    <x-ui.select wire:model.live="filtroDia" aria-label="Filtrar por día">
                        <option value="">Todos</option>
                        @foreach ($dias as $d)
                            <option value="{{ $d->value }}">{{ $d->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                @unless ($grupo)
                    <x-ui.field label="Grupo">
                        <x-ui.select wire:model.live="filtroGrupo" aria-label="Filtrar por grupo">
                            <option value="">Todos</option>
                            @foreach ($grupos as $g)
                                <option value="{{ $g->id }}">{{ $g->clave }} — {{ $g->materia?->clave }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                @endunless
            </div>
        </x-ui.filters>
    </div>

    <div class="mt-4">
        <x-ui.card :flush="$horarios->isNotEmpty()">
            @if ($horarios->isEmpty())
                <x-ui.empty-state icon="calendar"
                    :title="$grupo ? 'No hay horarios para este grupo' : 'No hay horarios registrados'"
                    message="Define el primer bloque de horario para que el grupo tenga disponibilidad de aula.">
                    <x-slot:action>
                        <x-ui.button wire:click="abrirModalCrear" icon="plus" :disabled="!$grupo">Nuevo horario
                        </x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Horarios registrados</caption>
                        <thead>
                            <tr>
                                <th scope="col">Día</th>
                                <th scope="col">Hora inicio</th>
                                <th scope="col">Hora fin</th>
                                @unless ($grupo)
                                    <th scope="col">Grupo</th>
                                @endunless
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($horarios as $h)
                                <tr wire:key="horario-{{ $h->id }}">
                                    <td class="font-medium text-brand-text">{{ $h->dia->label() }}</td>
                                    <td class="font-medium text-brand-primary">{{ $h->hora_inicio->format('H:i') }}</td>
                                    <td class="font-medium text-brand-primary">{{ $h->hora_fin->format('H:i') }}</td>
                                    @unless ($grupo)
                                        <td class="text-brand-muted">
                                            {{ $h->grupo?->clave ?? '—' }} — {{ $h->grupo?->materia?->clave ?? '—' }}
                                        </td>
                                    @endunless
                                    <td>
                                        <div class="ui-row-actions">
                                            <x-ui.icon-button label="Editar horario" icon="pencil"
                                                wire:click="abrirModalEditar({{ $h->id }})" />
                                            <x-ui.icon-button label="Eliminar horario" icon="x-mark" variant="danger"
                                                wire:click="eliminar({{ $h->id }})"
                                                wire:confirm="¿Eliminar el horario del {{ $h->dia->label() }} ({{ $h->hora_inicio->format('H:i') }}–{{ $h->hora_fin->format('H:i') }})?" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-brand-line px-5 py-3">
                    {{ $horarios->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>

    {{-- ======================= MODAL CREAR / EDITAR ======================= --}}
    <x-ui.modal id="modal-horario" size="sm"
        :title="$horarioEnEdicion ? 'Editar horario' : 'Nuevo horario'"
        :open="$modalAbierto" close="cerrarModal">
        <form wire:submit="guardar" class="space-y-4">
            @if ($errors->any())
                <x-ui.alert tone="danger">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            @if ($grupo)
                <p class="text-sm text-brand-muted">
                    Grupo <strong class="text-brand-text">{{ $grupo->clave }}</strong> —
                    {{ $grupo->materia?->clave }} · {{ $grupo->periodo?->clave }}
                </p>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Día" for="horario-dia" required class="sm:col-span-2"
                    :error="$errors->first('dia')">
                    <x-ui.select id="horario-dia" wire:model="dia">
                        <option value="">Seleccione un día</option>
                        @foreach ($dias as $d)
                            <option value="{{ $d->value }}">{{ $d->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Hora inicio" for="horario-inicio" required :error="$errors->first('horaInicio')">
                    <x-ui.input id="horario-inicio" type="time" wire:model="horaInicio" />
                </x-ui.field>

                <x-ui.field label="Hora fin" for="horario-fin" required :error="$errors->first('horaFin')">
                    <x-ui.input id="horario-fin" type="time" wire:model="horaFin" />
                </x-ui.field>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-brand-line pt-4">
                <x-ui.button variant="secondary" wire:click="cerrarModal">Cancelar</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">
                        {{ $horarioEnEdicion ? 'Guardar cambios' : 'Crear horario' }}
                    </span>
                    <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
