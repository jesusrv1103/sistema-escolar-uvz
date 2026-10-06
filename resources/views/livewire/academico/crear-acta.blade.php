<div>
    <x-ui.modal id="modal-crear-acta" title="Crear Acta de Calificaciones"
        :open="$modalAbierto" close="$set('modalAbierto', false)">
        <form novalidate id="form-crear-acta" wire:submit="crear">
            <div class="space-y-3">
                @if ($errors->any())
                    <x-ui.alert tone="danger">
                        <ul class="list-inside list-disc space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                @endif

                <x-ui.field label="Periodo" for="acta-periodo" required :error="$errors->first('periodo')">
                    <x-ui.select id="acta-periodo" wire:change="seleccionarPeriodo($event.target.value)">
                        <option value="">Seleccionar periodo</option>
                        @foreach ($periodos as $p)
                            <option value="{{ $p->id }}" @selected($periodo?->getKey() === $p->id)>
                                {{ $p->clave }} - {{ $p->nombre }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Grupo" for="acta-grupo" required :error="$errors->first('grupo')"
                    hint="Solo grupos activos del periodo seleccionado.">
                    <x-ui.select id="acta-grupo" wire:change="seleccionarGrupo($event.target.value)">
                        <option value="">Seleccionar grupo</option>
                        @foreach ($grupos as $g)
                            <option value="{{ $g->id }}" @selected($grupo?->getKey() === $g->id)>
                                {{ $g->clave }} - {{ $g->materia->clave }} ({{ $g->materia->nombre }})
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.alert tone="info">
                    El acta se crea en estado <strong>Abierta</strong> sin folio.
                    El folio <strong>ACT-YYYY-########</strong> se genera automáticamente
                    al cerrar el acta (cuatro ojos: DOCENTE captura, CONTROL_ESCOLAR cierra).
                </x-ui.alert>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="$set('modalAbierto', false)">Cancelar</x-ui.button>
                <x-ui.button type="submit" form="form-crear-acta" icon="plus"
                    wire:loading.attr="disabled" wire:target="crear">
                    <span wire:loading.remove wire:target="crear">Crear acta</span>
                    <span wire:loading wire:target="crear"><x-ui.loading label="Creando…" /></span>
                </x-ui.button>
            </x-slot:footer>
        </form>
    </x-ui.modal>
</div>
