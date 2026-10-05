<div>
    <x-ui.page-header title="Docentes"
        description="Plantilla docente: identificación, especialidad y grado académico.">
        <x-slot:actions>
            <x-ui.button wire:click="crear" icon="plus">Nuevo docente</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('catalogo'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('catalogo') }}</x-ui.alert></div>
    @endif

    <div class="mt-5">
        <x-ui.filters>
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" wire:click="limpiarFiltros">Limpiar filtros</x-ui.button>
            </x-slot:actions>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field label="Buscar" class="lg:col-span-2">
                    <x-ui.input type="search" wire:model.debounce.300ms="busqueda"
                        placeholder="Nombre, apellidos, clave o CURP…" aria-label="Buscar docentes" />
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
        <x-ui.card :flush="$docentes->isNotEmpty()">
            @if ($docentes->isEmpty())
                <x-ui.empty-state icon="user" title="No hay docentes"
                    message="Agrega al primer docente para poder asignarle grupos y evaluaciones.">
                    <x-slot:action>
                        <x-ui.button wire:click="crear" icon="plus">Nuevo docente</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Plantilla de docentes</caption>
                        <thead>
                            <tr>
                                <th scope="col">Clave</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">CURP</th>
                                <th scope="col">Especialidad</th>
                                <th scope="col">Grado</th>
                                <th scope="col">Cédula</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($docentes as $d)
                                <tr wire:key="docente-{{ $d->id }}">
                                    <td class="font-medium text-brand-primary">{{ $d->clave }}</td>
                                    <td class="font-medium text-brand-text">{{ $d->nombreCompleto() }}</td>
                                    <td class="text-brand-muted">{{ $d->curp }}</td>
                                    <td class="text-brand-muted">{{ $d->especialidad ?? '—' }}</td>
                                    <td class="text-brand-muted">{{ $d->grado_estudios ?? '—' }}</td>
                                    <td class="text-brand-muted">{{ $d->cedula_profesional ?? '—' }}</td>
                                    <td>
                                        <x-ui.badge :tone="$d->activo ? 'success' : 'neutral'">
                                            {{ $d->activo ? 'Activo' : 'Inactivo' }}
                                        </x-ui.badge>
                                    </td>
                                    <td>
                                        <div class="ui-row-actions">
                                            <x-ui.icon-button label="Editar {{ $d->nombreCompleto() }}" icon="cog"
                                                wire:click="editar({{ $d->id }})" />
                                            <x-ui.icon-button
                                                :label="$d->activo ? 'Desactivar' : 'Activar'"
                                                :icon="$d->activo ? 'no-symbol' : 'check-circle'"
                                                :variant="$d->activo ? 'secondary' : 'primary'"
                                                wire:click="alternarActivo({{ $d->id }})" />
                                            <x-ui.icon-button label="Eliminar {{ $d->nombreCompleto() }}" icon="x-mark"
                                                variant="danger" wire:click="eliminar({{ $d->id }})"
                                                wire:confirm="¿Eliminar a {{ $d->nombreCompleto() }}? (borrado lógico)" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-brand-line px-5 py-3">
                    {{ $docentes->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>

    <x-ui.modal id="modal-docente" size="lg"
        :title="$registroId ? 'Editar docente' : 'Nuevo docente'"
        :open="$formulario" close="cancelar">
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

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Clave" for="docente-clave" required
                    hint="Solo mayúsculas, números, guión y guion bajo." :error="$errors->first('clave')">
                    <x-ui.input id="docente-clave" wire:model="clave" maxlength="20" placeholder="Ej. DOC-001" />
                </x-ui.field>

                <x-ui.field label="CURP" for="docente-curp" required hint="18 caracteres."
                    :error="$errors->first('curp')">
                    <x-ui.input id="docente-curp" wire:model="curp" maxlength="18" />
                </x-ui.field>

                <x-ui.field label="Nombre(s)" for="docente-nombre" required :error="$errors->first('nombre')">
                    <x-ui.input id="docente-nombre" wire:model="nombre" maxlength="150" />
                </x-ui.field>

                <x-ui.field label="Apellidos" for="docente-apellidos" required :error="$errors->first('apellidos')">
                    <x-ui.input id="docente-apellidos" wire:model="apellidos" maxlength="150" />
                </x-ui.field>

                <x-ui.field label="RFC" for="docente-rfc" hint="13 caracteres." :error="$errors->first('rfc')">
                    <x-ui.input id="docente-rfc" wire:model="rfc" maxlength="13" />
                </x-ui.field>

                <x-ui.field label="Correo" for="docente-correo" :error="$errors->first('correo')">
                    <x-ui.input id="docente-correo" type="email" wire:model="correo" maxlength="150"
                        placeholder="docente@uvz.edu.mx" />
                </x-ui.field>

                <x-ui.field label="Teléfono" for="docente-telefono" :error="$errors->first('telefono')">
                    <x-ui.input id="docente-telefono" wire:model="telefono" maxlength="20" placeholder="10 dígitos" />
                </x-ui.field>

                <x-ui.field label="Especialidad" for="docente-especialidad" :error="$errors->first('especialidad')">
                    <x-ui.input id="docente-especialidad" wire:model="especialidad" maxlength="150"
                        placeholder="Ej. Matemáticas" />
                </x-ui.field>

                <x-ui.field label="Grado de estudios" for="docente-grado" :error="$errors->first('grado_estudios')">
                    <x-ui.input id="docente-grado" wire:model="grado_estudios" maxlength="100"
                        placeholder="Ej. Maestría" />
                </x-ui.field>

                <x-ui.field label="Cédula profesional" for="docente-cedula" :error="$errors->first('cedula_profesional')">
                    <x-ui.input id="docente-cedula" wire:model="cedula_profesional" maxlength="50"
                        placeholder="Número de cédula" />
                </x-ui.field>
            </div>

            <label class="inline-flex cursor-pointer items-center gap-2.5">
                <input type="checkbox" id="docente-activo" wire:model="activo"
                    class="h-4 w-4 rounded border-brand-line accent-brand-primary" />
                <span class="text-sm font-medium text-brand-text">Activo</span>
            </label>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-brand-line pt-4">
                <x-ui.button variant="secondary" wire:click="cancelar">Cancelar</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">Guardar</span>
                    <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
