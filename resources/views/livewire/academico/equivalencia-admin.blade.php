<div>
    <x-ui.page-header title="Equivalencias / Revalidaciones"
        description="Materias acreditadas en otra institución: dictamen, créditos y estatus.">
        <x-slot:actions>
            @can('equivalencia.crear')
                <x-ui.button icon="plus" wire:click="abrirModalCrear">Nueva Equivalencia</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ------------------------- Filtros compactos ------------------------- --}}
    <div class="mt-5">
        <x-ui.filters>
            <div class="grid gap-3 md:grid-cols-3">
                <x-ui.field label="Buscar">
                    <x-ui.input type="text" wire:model.live.debounce.300ms="busqueda"
                        placeholder="Alumno, origen…" aria-label="Buscar equivalencias" />
                </x-ui.field>

                <x-ui.field label="Estatus">
                    <x-ui.select wire:model.live="filtroEstatus" aria-label="Filtrar por estatus">
                        <option value="">Todos</option>
                        @foreach (\App\Enums\EstatusEquivalencia::cases() as $e)
                            <option value="{{ $e->value }}">{{ $e->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            </div>

            <div class="mt-3 flex justify-end border-t border-brand-line pt-3">
                <x-ui.button size="sm" variant="ghost" icon="x-mark" wire:click="limpiarFiltros">
                    Limpiar filtros
                </x-ui.button>
            </div>
        </x-ui.filters>
    </div>

    {{-- ------------------------------ Listado ------------------------------ --}}
    <div class="mt-4">
        <x-ui.card :flush="count($equivalencias) > 0">
            @if (session('success'))
                <div class="px-5 pt-4"><x-ui.alert tone="success">{{ session('success') }}</x-ui.alert></div>
            @endif

            @if (count($equivalencias) === 0)
                <x-ui.empty-state icon="scale" title="Sin equivalencias registradas"
                    message="Ajusta los filtros o registra la primera equivalencia.">
                    <x-slot:action>
                        @can('equivalencia.crear')
                            <x-ui.button icon="plus" wire:click="abrirModalCrear">Nueva Equivalencia</x-ui.button>
                        @endcan
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th scope="col">Alumno</th>
                                <th scope="col">Materia UVZ</th>
                                <th scope="col">Origen</th>
                                <th scope="col" class="text-right">Créditos</th>
                                <th scope="col">Estatus</th>
                                <th scope="col">Dictamen</th>
                                <th scope="col">Autorizada por</th>
                                <th scope="col">Fecha</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($equivalencias as $eq)
                                <tr wire:key="equivalencia-{{ $eq->id }}">
                                    <td class="font-medium text-brand-text">{{ $eq->alumno->nombre_completo }}</td>
                                    <td>
                                        {{ $eq->materia->clave ?? 'Crédito libre' }}
                                        <span class="text-brand-muted">- {{ $eq->materia->nombre ?? 'Sin materia UVZ' }}</span>
                                    </td>
                                    <td class="max-w-[240px] text-brand-muted">{{ \Illuminate\Support\Str::limit($eq->origen, 40) }}</td>
                                    <td class="text-right tabular-nums">{{ $eq->creditos }}</td>
                                    <td>
                                        <x-ui.badge :tone="match ($eq->estatus->value) {
                                            'APROBADA' => 'success',
                                            'RECHAZADA' => 'danger',
                                            default => 'warning',
                                        }">
                                            {{ $eq->estatus->label() }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="max-w-[220px] text-brand-muted">{{ \Illuminate\Support\Str::limit($eq->dictamen ?? '', 40) }}</td>
                                    <td class="text-brand-muted">{{ $eq->autorizadaPor?->name ?? '—' }}</td>
                                    <td class="whitespace-nowrap text-brand-muted">
                                        {{ $eq->fecha_dictamen?->format('d/m/Y') ?? $eq->created_at->format('d/m/Y') }}
                                    </td>
                                    <td class="text-right">
                                        <div class="ui-row-actions" role="group"
                                            aria-label="Acciones de la equivalencia de {{ $eq->alumno->nombre_completo }}">
                                            @can('equivalencia.actualizar', $eq)
                                                <x-ui.icon-button label="Editar equivalencia" icon="pencil"
                                                    wire:click="abrirModalEditar({{ $eq->id }})" />
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-brand-line px-5 py-3">{{ $equivalencias->links() }}</div>
            @endif
        </x-ui.card>
    </div>

    {{-- --------------------- Modal: crear / editar equivalencia --------------------- --}}
    @if ($modalAbierto)
        <x-ui.modal id="modal-equivalencia" size="lg"
            :title="($equivalencia ? 'Editar' : 'Registrar') . ' Equivalencia'"
            :open="true" close="$set('modalAbierto', false)">
            <form novalidate id="form-equivalencia" wire:submit="guardar">
                <div class="grid gap-3 md:grid-cols-2">
                    @if ($errors->any())
                        <div class="md:col-span-2">
                            <x-ui.alert tone="danger">
                                <ul class="list-inside list-disc space-y-0.5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </x-ui.alert>
                        </div>
                    @endif

                    <x-ui.field label="Alumno" for="eq-alumno" required :error="$errors->first('alumno')">
                        <x-ui.select id="eq-alumno" wire:change="seleccionarAlumno($event.target.value)">
                            <option value="">Seleccionar alumno</option>
                            @foreach (\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                                <option value="{{ $a->id }}" @selected($alumno?->getKey() === $a->id)>
                                    {{ $a->nombre_completo }} ({{ $a->curp }})
                                </option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field label="Materia UVZ (opcional)" for="eq-materia" :error="$errors->first('materia')">
                        <x-ui.select id="eq-materia" wire:change="seleccionarMateria($event.target.value)">
                            <option value="">Sin materia UVZ (crédito libre)</option>
                            @foreach (\App\Models\Materia::orderBy('clave')->get() as $m)
                                <option value="{{ $m->id }}" @selected($materia?->getKey() === $m->id)>
                                    {{ $m->clave }} - {{ $m->nombre }} ({{ $m->creditos }} créditos)
                                </option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field label="Créditos" for="eq-creditos" required :error="$errors->first('creditos')">
                        <x-ui.input id="eq-creditos" type="number" wire:model="creditos" min="0" max="20" />
                    </x-ui.field>

                    <x-ui.field label="Estatus" for="eq-estatus" required :error="$errors->first('estatus')">
                        <x-ui.select id="eq-estatus" wire:model="estatus">
                            @foreach (\App\Enums\EstatusEquivalencia::cases() as $e)
                                <option value="{{ $e->value }}">{{ $e->label() }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <div class="md:col-span-2">
                        <x-ui.field label="Institución / Origen" for="eq-origen" required
                            :error="$errors->first('origen')">
                            <x-ui.input id="eq-origen" type="text" wire:model="origen" maxlength="500"
                                placeholder="Nombre de la institución, país, programa, etc." />
                        </x-ui.field>
                    </div>

                    <div class="md:col-span-2">
                        <x-ui.field label="Dictamen / Observaciones" for="eq-dictamen"
                            :error="$errors->first('dictamen')">
                            <textarea id="eq-dictamen" wire:model="dictamen" rows="3" maxlength="2000"
                                class="ui-textarea"
                                placeholder="Fundamento técnico, normativa, motivos de aprobación/rechazo…"></textarea>
                        </x-ui.field>
                    </div>
                </div>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="$set('modalAbierto', false)">Cancelar</x-ui.button>
                    <x-ui.button type="submit" form="form-equivalencia" icon="check"
                        wire:loading.attr="disabled" wire:target="guardar">
                        <span wire:loading.remove wire:target="guardar">
                            {{ $equivalencia ? 'Actualizar' : 'Registrar' }}
                        </span>
                        <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                    </x-ui.button>
                </x-slot:footer>
            </form>
        </x-ui.modal>
    @endif
</div>
