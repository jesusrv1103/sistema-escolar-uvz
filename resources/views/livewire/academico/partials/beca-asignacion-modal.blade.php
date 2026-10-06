@if ($modalAsignacionAbierto)
    <x-ui.modal id="modal-asignar-beca" size="lg" title="Asignar beca"
        :open="true" close="$set('modalAsignacionAbierto', false)">
        <form novalidate id="form-beca-asignacion" wire:submit="asignarBeca">
            <div class="grid gap-3 sm:grid-cols-2">
                @if ($errors->any())
                    <div class="sm:col-span-2">
                        <x-ui.alert tone="danger">
                            <ul class="list-inside list-disc space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </x-ui.alert>
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <x-ui.field label="Beca" for="asig-beca" required :error="$errors->first('becaAsignar')">
                        <x-ui.select id="asig-beca" wire:change="seleccionarBecaAsignar($event.target.value)">
                            <option value="">Seleccionar beca</option>
                            @foreach (\App\Models\Beca::where('activo', true)->get() as $b)
                                <option value="{{ $b->id }}" @selected($becaAsignar?->getKey() === $b->id)>
                                    {{ $b->clave }} - {{ $b->nombre }} ({{ $b->tipo->label() }}:
                                    {{ $b->tipo->value === 'PORCENTAJE' ? $b->valor . '%' : '$' . number_format($b->valor, 2) }})
                                </option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>

                <div class="sm:col-span-2">
                    <x-ui.field label="Alumno" for="asig-alumno" required :error="$errors->first('alumnoAsignar')">
                        <x-ui.select id="asig-alumno" wire:change="seleccionarAlumnoAsignar($event.target.value)">
                            <option value="">Seleccionar alumno</option>
                            @foreach (\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                                <option value="{{ $a->id }}" @selected($alumnoAsignar?->getKey() === $a->id)>
                                    {{ $a->nombre_completo }} ({{ $a->curp }})
                                </option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>

                <div class="sm:col-span-2">
                    <x-ui.field label="Periodo" for="asig-periodo" required :error="$errors->first('periodoAsignar')">
                        <x-ui.select id="asig-periodo" wire:change="seleccionarPeriodoAsignar($event.target.value)">
                            <option value="">Seleccionar periodo</option>
                            @foreach (\App\Models\Periodo::where('activo', true)->orderByDesc('fecha_inicio')->get() as $p)
                                <option value="{{ $p->id }}" @selected($periodoAsignar?->getKey() === $p->id)>
                                    {{ $p->clave }} - {{ $p->nombre }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>

                <div class="sm:col-span-2">
                    <x-ui.field label="Observación" for="asig-obs" :error="$errors->first('observacion')">
                        <textarea id="asig-obs" wire:model="observacion" rows="3" maxlength="1000"
                            class="ui-textarea"
                            placeholder="Motivo de la asignación, condiciones especiales…"></textarea>
                    </x-ui.field>
                </div>

                <div class="sm:col-span-2">
                    <x-ui.alert tone="info">
                        La asignación queda en estado <strong>Pendiente</strong>: debe ser aprobada
                        por Administración antes de aplicar el descuento.
                    </x-ui.alert>
                </div>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="$set('modalAsignacionAbierto', false)">Cancelar</x-ui.button>
                <x-ui.button type="submit" form="form-beca-asignacion" icon="plus" wire:loading.attr="disabled" wire:target="asignarBeca">
                    <span wire:loading.remove wire:target="asignarBeca">Asignar</span>
                    <span wire:loading wire:target="asignarBeca"><x-ui.loading label="Asignando…" /></span>
                </x-ui.button>
            </x-slot:footer>
        </form>
    </x-ui.modal>
@endif
