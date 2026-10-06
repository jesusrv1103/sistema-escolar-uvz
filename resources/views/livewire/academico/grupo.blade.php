<div>
    <x-ui.page-header title="Grupos académicos"
        description="Agrupaciones por materia y periodo: cupo, docente, aula y estatus operativo.">
        <x-slot:actions>
            <x-ui.button wire:click="abrirModalCrear" icon="plus">Nuevo grupo</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('success'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('success') }}</x-ui.alert></div>
    @endif

    @php
        $tonos = [
            'CREADO' => 'neutral',
            'ABIERTO' => 'info',
            'EN_CURSO' => 'primary',
            'CERRADO' => 'warning',
            'CANCELADO' => 'danger',
        ];
    @endphp

    <div class="mt-5">
        <x-ui.filters>
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" wire:click="limpiarFiltros">Limpiar filtros</x-ui.button>
            </x-slot:actions>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.field label="Buscar" class="lg:col-span-2">
                    <x-ui.input type="search" wire:model.debounce.300ms="busqueda"
                        placeholder="Nombre, clave o materia…" aria-label="Buscar grupos" />
                </x-ui.field>

                <x-ui.field label="Periodo">
                    <x-ui.select wire:model.live="filtroPeriodo" aria-label="Filtrar por periodo">
                        <option value="">Todos</option>
                        @foreach ($periodos as $p)
                            <option value="{{ $p->id }}">{{ $p->clave }} — {{ $p->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Materia">
                    <x-ui.select wire:model.live="filtroMateria" aria-label="Filtrar por materia">
                        <option value="">Todas</option>
                        @foreach ($materias as $m)
                            <option value="{{ $m->id }}">{{ $m->clave }} — {{ $m->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Docente">
                    <x-ui.select wire:model.live="filtroDocente" aria-label="Filtrar por docente">
                        <option value="">Todos</option>
                        @foreach ($docentes as $d)
                            <option value="{{ $d->id }}">{{ $d->nombreCompleto() }} ({{ $d->clave }})</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Estatus">
                    <x-ui.select wire:model.live="filtroEstatus" aria-label="Filtrar por estatus">
                        <option value="">Todos</option>
                        @foreach (\App\Enums\EstatusGrupo::cases() as $e)
                            <option value="{{ $e->value }}">{{ $e->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Estado">
                    <x-ui.select wire:model.live="filtroActivo" aria-label="Filtrar por estado">
                        <option value="">Todos</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                    </x-ui.select>
                </x-ui.field>
            </div>
        </x-ui.filters>
    </div>

    <div class="mt-4">
        <x-ui.card :flush="$grupos->isNotEmpty()">
            @if ($grupos->isEmpty())
                <x-ui.empty-state icon="users" title="No hay grupos registrados"
                    message="Crea el primer grupo para poder abrir inscripciones y asignar horarios.">
                    <x-slot:action>
                        <x-ui.button wire:click="abrirModalCrear" icon="plus">Nuevo grupo</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Listado de grupos académicos</caption>
                        <thead>
                            <tr>
                                <th scope="col">Clave</th>
                                <th scope="col">Materia</th>
                                <th scope="col">Periodo</th>
                                <th scope="col">Docente</th>
                                <th scope="col">Aula</th>
                                <th scope="col">Cupo</th>
                                <th scope="col">Turno</th>
                                <th scope="col">Estatus</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grupos as $g)
                                <tr wire:key="grupo-{{ $g->id }}">
                                    <td class="font-medium text-brand-primary">{{ $g->clave }}</td>
                                    <td>
                                        <span class="block font-medium text-brand-text">{{ $g->materia->nombre }}</span>
                                        <span class="block text-xs text-brand-muted">{{ $g->materia->clave }}</span>
                                    </td>
                                    <td class="text-brand-muted">{{ $g->periodo->clave }}</td>
                                    <td class="text-brand-muted">{{ $g->docente?->nombreCompleto() ?? '—' }}</td>
                                    <td class="text-brand-muted">{{ $g->aula?->nombre ?? '—' }}</td>
                                    <td class="font-medium text-brand-text">{{ $g->cupo }}</td>
                                    <td class="text-brand-muted">{{ \App\Enums\TurnoGrupo::tryFrom($g->turno)?->label() ?? $g->turno }}</td>
                                    <td>
                                        <span class="inline-flex flex-wrap gap-1">
                                            <x-ui.badge :tone="$tonos[$g->estatus->value] ?? 'neutral'">
                                                {{ $g->estatus->label() }}
                                            </x-ui.badge>
                                            @unless ($g->activo)
                                                <x-ui.badge tone="neutral">Inactivo</x-ui.badge>
                                            @endunless
                                        </span>
                                    </td>
                                    <td>
                                        <div class="ui-row-actions">
                                            @if ($g->esCreado())
                                                <x-ui.icon-button label="Editar grupo {{ $g->clave }}" icon="pencil"
                                                    wire:click="abrirModalEditar({{ $g->id }})" />
                                                <x-ui.icon-button label="Horarios de {{ $g->clave }}" icon="calendar"
                                                    variant="secondary"
                                                    :href="route('admin.grupos.horarios', $g)" />
                                                <x-ui.icon-button
                                                    :label="$g->activo ? 'Desactivar' : 'Activar'"
                                                    :icon="$g->activo ? 'no-symbol' : 'check-circle'"
                                                    :variant="$g->activo ? 'secondary' : 'primary'"
                                                    wire:click="alternarActivo({{ $g->id }})" />
                                            @else
                                                <span class="text-sm text-brand-muted"
                                                    aria-label="Sin acciones disponibles">—</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-brand-line px-5 py-3">
                    {{ $grupos->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>

    {{-- ======================= MODAL CREAR / EDITAR ======================= --}}
    <x-ui.modal id="modal-grupo" size="lg"
        :title="$grupoEnEdicion ? 'Editar grupo' : 'Nuevo grupo'"
        :open="$modalAbierto" close="cerrarModal">
        <form novalidate wire:submit="guardar" class="space-y-4">
            @if ($errors->any())
                <x-ui.alert tone="danger">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Clave" for="grupo-clave" required
                    hint="Solo mayúsculas, números, guión y guion bajo." :error="$errors->first('clave')">
                    <x-ui.input id="grupo-clave" wire:model="clave" maxlength="20" placeholder="Ej. GRP-001" />
                </x-ui.field>

                <x-ui.field label="Materia" for="grupo-materia" required :error="$errors->first('materiaId')">
                    <x-ui.select id="grupo-materia" wire:model="materiaId">
                        <option value="">Seleccione una materia</option>
                        @foreach ($materias as $m)
                            <option value="{{ $m->id }}">
                                {{ $m->clave }} — {{ $m->nombre }} (v{{ $m->planVersion->version }})
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Periodo" for="grupo-periodo" required :error="$errors->first('periodoId')">
                    <x-ui.select id="grupo-periodo" wire:model="periodoId">
                        <option value="">Seleccione un periodo</option>
                        @foreach ($periodos as $p)
                            <option value="{{ $p->id }}">{{ $p->clave }} — {{ $p->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Cupo" for="grupo-cupo" required :error="$errors->first('cupo')">
                    <x-ui.input id="grupo-cupo" type="number" min="1" max="500" wire:model="cupo" />
                </x-ui.field>

                <x-ui.field label="Turno" for="grupo-turno" required :error="$errors->first('turno')">
                    <x-ui.select id="grupo-turno" wire:model="turno">
                        @foreach (\App\Enums\TurnoGrupo::cases() as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Estatus" for="grupo-estatus" required :error="$errors->first('estatus')">
                    <x-ui.select id="grupo-estatus" wire:model="estatus">
                        @foreach (\App\Enums\EstatusGrupo::cases() as $e)
                            <option value="{{ $e->value }}">{{ $e->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Docente" for="grupo-docente" hint="Opcional mientras se asigna la plantilla.">
                    <x-ui.select id="grupo-docente" wire:model="docenteId">
                        <option value="">Sin docente asignado</option>
                        @foreach ($docentes as $d)
                            <option value="{{ $d->id }}">{{ $d->nombreCompleto() }} ({{ $d->clave }})</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Aula" for="grupo-aula" hint="Opcional: se puede asignar después.">
                    <x-ui.select id="grupo-aula" wire:model="aulaId">
                        <option value="">Sin aula asignada</option>
                        @foreach ($aulas as $a)
                            <option value="{{ $a->id }}">
                                {{ $a->clave }} — {{ $a->nombre }} ({{ $a->edificio }}, cap. {{ $a->capacidad }})
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <div class="sm:col-span-2">
                    <x-ui.field label="Nombre del grupo" for="grupo-nombre"
                        hint="Se genera automáticamente si se deja vacío." :error="$errors->first('nombre')">
                        <x-ui.input id="grupo-nombre" wire:model="nombre" maxlength="150" />
                    </x-ui.field>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-brand-line pt-4">
                <x-ui.button variant="secondary" wire:click="cerrarModal">Cancelar</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">
                        {{ $grupoEnEdicion ? 'Guardar cambios' : 'Crear grupo' }}
                    </span>
                    <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
