<div>
    <x-ui.page-header title="Materias"
        :description="$version
            ? $version->plan->nombre . ' · versión ' . $version->version . ' · estructura de la planificación académica.'
            : 'Selecciona una versión de plan desde el listado de versiones para gestionar sus materias.'">
        <x-slot:meta>
            @if ($version)
                <x-ui.badge
                    :tone="$version->estatus->value === 'VIGENTE' ? 'success' : ($version->estatus->value === 'PROPUESTA' ? 'warning' : 'neutral')">
                    {{ $version->estatus->label() }}
                </x-ui.badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <x-ui.button wire:click="abrirModalCrear" icon="plus" :disabled="!$version">Nueva materia</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (! $version)
        <div class="mt-4">
            <x-ui.alert tone="warning">
                Selecciona una versión de plan desde el listado de versiones para gestionar sus materias.
            </x-ui.alert>
        </div>
    @endif

    @if (session('success'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('success') }}</x-ui.alert></div>
    @endif

    @if ($errors->any())
        <div class="mt-4">
            <x-ui.alert tone="danger">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        </div>
    @endif

    <div class="mt-5">
        <x-ui.filters>
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" wire:click="limpiarFiltros">Limpiar filtros</x-ui.button>
            </x-slot:actions>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field label="Buscar" class="lg:col-span-2">
                    <x-ui.input type="search" wire:model.debounce.300ms="busqueda"
                        placeholder="Nombre o clave…" aria-label="Buscar materias" />
                </x-ui.field>
                <x-ui.field label="Cuatrimestre">
                    <x-ui.select wire:model.live="filtroPeriodo" aria-label="Filtrar por cuatrimestre">
                        <option value="">Todos</option>
                        @foreach ($periodos as $p)
                            <option value="{{ $p }}">Cuatrimestre {{ $p }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Estado">
                    <x-ui.select wire:model.live="filtroActivo" aria-label="Filtrar por estado">
                        <option value="">Todas</option>
                        <option value="1">Activas</option>
                        <option value="0">Inactivas</option>
                    </x-ui.select>
                </x-ui.field>
            </div>
        </x-ui.filters>
    </div>

    <div class="mt-4">
        <x-ui.card :flush="$materias->isNotEmpty()">
            @if ($materias->isEmpty())
                <x-ui.empty-state icon="academic-cap"
                    :title="$version ? 'No hay materias para esta versión' : 'No hay materias registradas'"
                    message="Agrega la primera materia para estructurar la planificación académica.">
                    <x-slot:action>
                        <x-ui.button wire:click="abrirModalCrear" icon="plus" :disabled="!$version">Nueva materia
                        </x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Materias de la versión del plan</caption>
                        <thead>
                            <tr>
                                <th scope="col">Clave</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Créditos</th>
                                <th scope="col">Cuatrimestre</th>
                                <th scope="col">Bloque</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($materias as $m)
                                <tr wire:key="materia-{{ $m->id }}">
                                    <td class="font-medium text-brand-primary">{{ $m->clave }}</td>
                                    <td class="font-medium text-brand-text">{{ $m->nombre }}</td>
                                    <td class="text-brand-muted">{{ $m->creditos }}</td>
                                    <td><x-ui.badge tone="info">Cuatrimestre {{ $m->periodo_orden }}</x-ui.badge></td>
                                    <td class="text-brand-muted">{{ $m->bloque ?? '—' }}</td>
                                    <td>
                                        <x-ui.badge :tone="$m->obligatoria ? 'danger' : 'neutral'">
                                            {{ $m->obligatoria ? 'Obligatoria' : 'Optativa' }}
                                        </x-ui.badge>
                                    </td>
                                    <td>
                                        <x-ui.badge :tone="$m->activo ? 'success' : 'neutral'">
                                            {{ $m->activo ? 'Activa' : 'Inactiva' }}
                                        </x-ui.badge>
                                    </td>
                                    <td>
                                        <div class="ui-row-actions">
                                            @if ($version?->esPropuesta())
                                                <x-ui.icon-button label="Editar {{ $m->nombre }}" icon="pencil"
                                                    wire:click="abrirModalEditar({{ $m->id }})" />
                                                <x-ui.icon-button label="Prerrequisitos de {{ $m->nombre }}"
                                                    icon="folder" variant="secondary"
                                                    wire:click="abrirModalPrerreq({{ $m->id }})" />
                                                <x-ui.icon-button
                                                    :label="$m->activo ? 'Desactivar' : 'Activar'"
                                                    :icon="$m->activo ? 'no-symbol' : 'check-circle'"
                                                    :variant="$m->activo ? 'secondary' : 'primary'"
                                                    wire:click="alternarActivo({{ $m->id }})" />
                                                <x-ui.icon-button label="Eliminar {{ $m->nombre }}" icon="x-mark"
                                                    variant="danger" wire:click="eliminar({{ $m->id }})"
                                                    wire:confirm="¿Eliminar la materia {{ $m->nombre }}? (borrado lógico)" />
                                            @else
                                                <span class="text-sm text-brand-muted" aria-label="Sin acciones disponibles">—</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-brand-line px-5 py-3">
                    {{ $materias->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>

    {{-- ======================= MODAL CREAR / EDITAR ======================= --}}
    <x-ui.modal id="modal-materia" size="lg"
        :title="$materiaEnEdicion ? 'Editar materia' : 'Nueva materia'"
        :open="$modalAbierto" close="cerrarModal">
        <form wire:submit="guardar" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Clave" for="materia-clave" required
                    hint="Solo mayúsculas, números, guión y guion bajo." :error="$errors->first('clave')">
                    <x-ui.input id="materia-clave" wire:model="clave" maxlength="20" placeholder="Ej. MAT-101" />
                </x-ui.field>

                <x-ui.field label="Nombre" for="materia-nombre" required :error="$errors->first('nombre')">
                    <x-ui.input id="materia-nombre" wire:model="nombre" maxlength="150" />
                </x-ui.field>

                <x-ui.field label="Cuatrimestre" for="materia-periodo" required :error="$errors->first('periodoOrden')">
                    <x-ui.input id="materia-periodo" type="number" min="1" wire:model="periodoOrden" />
                </x-ui.field>

                <x-ui.field label="Créditos" for="materia-creditos" required :error="$errors->first('creditos')">
                    <x-ui.input id="materia-creditos" type="number" min="1" wire:model="creditos" />
                </x-ui.field>

                <x-ui.field label="Horas teóricas" for="materia-teoricas" :error="$errors->first('horasTeoricas')">
                    <x-ui.input id="materia-teoricas" type="number" min="0" wire:model="horasTeoricas" />
                </x-ui.field>

                <x-ui.field label="Horas prácticas" for="materia-practicas" :error="$errors->first('horasPracticas')">
                    <x-ui.input id="materia-practicas" type="number" min="0" wire:model="horasPracticas" />
                </x-ui.field>

                <div class="sm:col-span-2">
                    <x-ui.field label="Bloque" for="materia-bloque" hint="Ej. TRONCO_COMUN, PROFESIONAL."
                        :error="$errors->first('bloque')">
                        <x-ui.input id="materia-bloque" wire:model="bloque" maxlength="30" />
                    </x-ui.field>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-6">
                <label class="inline-flex cursor-pointer items-center gap-2.5">
                    <input type="checkbox" id="materia-obligatoria" wire:model="obligatoria"
                        class="h-4 w-4 rounded border-brand-line accent-brand-primary" />
                    <span class="text-sm font-medium text-brand-text">Obligatoria</span>
                </label>
                <label class="inline-flex cursor-pointer items-center gap-2.5">
                    <input type="checkbox" id="materia-activo" wire:model="activo"
                        class="h-4 w-4 rounded border-brand-line accent-brand-primary" />
                    <span class="text-sm font-medium text-brand-text">Activa</span>
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-brand-line pt-4">
                <x-ui.button variant="secondary" wire:click="cerrarModal">Cancelar</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">
                        {{ $materiaEnEdicion ? 'Guardar cambios' : 'Crear materia' }}
                    </span>
                    <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- ======================= MODAL PRERREQUISITOS ======================= --}}
    <x-ui.modal id="modal-prerrequisitos" size="lg"
        :title="'Prerrequisitos de ' . ($materiaPrerreq->nombre ?? '')"
        :open="$modalPrerreqAbierto" close="cerrarModalPrerreq">
        @if ($errors->get('prerreq'))
            <x-ui.alert tone="danger">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->get('prerreq') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <div class="grid gap-3 sm:grid-cols-2">
            <x-ui.field label="Tipo de prerrequisito" for="prerreq-tipo">
                <x-ui.select id="prerreq-tipo" wire:model.live="tipoPrerreq">
                    <option value="OBLIGATORIO">Obligatorio</option>
                    <option value="RECOMENDADO">Recomendado</option>
                </x-ui.select>
            </x-ui.field>
            <x-ui.field label="Buscar materia" for="prerreq-busqueda">
                <x-ui.input id="prerreq-busqueda" type="search" wire:model.debounce.300ms="busquedaPrerreq"
                    placeholder="Nombre o clave…" />
            </x-ui.field>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            @php
                $yaTienen = $materiaPrerreq?->prerrequisitos->pluck('id')->toArray() ?? [];
                $disponibles = $materiaPrerreq
                    ? \App\Models\Materia::where('plan_version_id', $materiaPrerreq->plan_version_id)
                        ->where('id', '!=', $materiaPrerreq->id)
                        ->whereNotIn('id', $yaTienen)
                        ->when($busquedaPrerreq, fn ($q) => $q->where('nombre', 'like', "%{$busquedaPrerreq}%")
                            ->orWhere('clave', 'like', "%{$busquedaPrerreq}%"))
                        ->orderBy('periodo_orden')->orderBy('clave')
                        ->get()
                    : collect();
            @endphp

            <div class="ui-card border-brand-line">
                <div class="border-b border-brand-line px-4 py-3">
                    <p class="ui-section-title">Disponibles para agregar</p>
                </div>
                <div class="max-h-72 overflow-y-auto divide-y divide-brand-line">
                    @if ($disponibles->isEmpty())
                        <p class="px-4 py-6 text-center text-sm text-brand-muted">
                            Todas las materias disponibles ya son prerrequisitos.
                        </p>
                    @else
                        @foreach ($disponibles as $d)
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-brand-text">
                                        <span class="text-brand-primary">{{ $d->clave }}</span> {{ $d->nombre }}
                                    </p>
                                    <p class="text-xs text-brand-muted">
                                        Cuatrimestre {{ $d->periodo_orden }} · {{ $d->creditos }} créditos
                                    </p>
                                </div>
                                <x-ui.button size="sm" icon="plus" wire:click="agregarPrerrequisito({{ $d->id }})">
                                    Agregar
                                </x-ui.button>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="ui-card border-brand-line">
                <div class="border-b border-brand-line px-4 py-3">
                    <p class="ui-section-title">Prerrequisitos actuales</p>
                </div>
                <div class="max-h-72 overflow-y-auto divide-y divide-brand-line">
                    @if (! $materiaPrerreq || $materiaPrerreq->prerrequisitos->isEmpty())
                        <p class="px-4 py-6 text-center text-sm text-brand-muted">Sin prerrequisitos.</p>
                    @else
                        @foreach ($materiaPrerreq->prerrequisitos as $p)
                            @php $tipoEnum = \App\Enums\TipoPrerrequisito::tryFrom($p->pivot->tipo); @endphp
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-brand-text">
                                        <span class="text-brand-primary">{{ $p->clave }}</span> {{ $p->nombre }}
                                    </p>
                                    <p class="text-xs text-brand-muted">
                                        Cuatrimestre {{ $p->periodo_orden }}
                                        <x-ui.badge :tone="$p->pivot->tipo === 'OBLIGATORIO' ? 'danger' : 'info'">
                                            {{ $tipoEnum?->label() ?? $p->pivot->tipo }}
                                        </x-ui.badge>
                                    </p>
                                </div>
                                <x-ui.icon-button label="Quitar prerrequisito {{ $p->nombre }}" icon="x-mark"
                                    variant="danger" wire:click="quitarPrerrequisito({{ $p->id }})" />
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <x-slot:footer>
            <x-ui.button variant="secondary" wire:click="cerrarModalPrerreq">Cerrar</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
