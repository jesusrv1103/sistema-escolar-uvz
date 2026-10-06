<div>
    <x-ui.modal id="modal-crear-evaluacion" size="lg" title="Nueva Evaluación"
        :open="$modalAbierto" close="cerrarModal">
        <form novalidate id="form-crear-evaluacion" wire:submit="guardar">
            @if ($errors->any())
                <x-ui.alert tone="danger">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            <div class="mt-4 space-y-3">
                <input type="hidden" wire:model="grupoId">

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <x-ui.field label="Nombre" for="eval-nombre" required :error="$errors->first('nombre')">
                            <x-ui.input id="eval-nombre" type="text" wire:model="nombre" maxlength="100"
                                placeholder="Primer parcial" />
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Tipo" for="eval-tipo" required :error="$errors->first('tipo')">
                        <x-ui.select id="eval-tipo" wire:model="tipo">
                            <option value="">— Selecciona —</option>
                            @foreach ($tipos as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <div>
                        <x-ui.field label="Ponderación (%)" for="eval-ponderacion" required
                            :error="$errors->first('ponderacion')"
                            hint="Entre 1 y 100. La suma total no puede exceder 100%.">
                            <x-ui.input id="eval-ponderacion" type="number" wire:model="ponderacion"
                                min="1" max="100" />
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Fecha" for="eval-fecha" required :error="$errors->first('fecha')">
                        <x-ui.input id="eval-fecha" type="date" wire:model="fecha" />
                    </x-ui.field>

                    <div class="sm:col-span-3">
                        <x-ui.field label="Descripción" for="eval-descripcion"
                            :error="$errors->first('descripcion')">
                            <textarea id="eval-descripcion" wire:model="descripcion" rows="3" maxlength="1000"
                                class="ui-textarea"
                                placeholder="Descripción opcional de la evaluación…"></textarea>
                        </x-ui.field>
                    </div>
                </div>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="cerrarModal">Cancelar</x-ui.button>
                <x-ui.button type="submit" form="form-crear-evaluacion" icon="plus" wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">Crear</span>
                    <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                </x-ui.button>
            </x-slot:footer>
        </form>
    </x-ui.modal>
</div>
