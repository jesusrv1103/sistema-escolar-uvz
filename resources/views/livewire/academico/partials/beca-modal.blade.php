@if ($modalBecaAbierto)
    <x-ui.modal id="modal-beca" size="lg" :title="($beca ? 'Editar' : 'Crear') . ' beca'"
        :open="true" close="$set('modalBecaAbierto', false)">
        <form wire:submit="guardarBeca">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @if ($errors->any())
                    <div class="sm:col-span-2 lg:col-span-4">
                        <x-ui.alert tone="danger">
                            <ul class="list-inside list-disc space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </x-ui.alert>
                    </div>
                @endif

                <div class="sm:col-span-1">
                    <x-ui.field label="Clave" for="beca-clave" required :error="$errors->first('clave')">
                        <x-ui.input id="beca-clave" type="text" wire:model="clave" maxlength="20" />
                    </x-ui.field>
                </div>

                <div class="sm:col-span-1">
                    <x-ui.field label="Nombre" for="beca-nombre" required :error="$errors->first('nombre')">
                        <x-ui.input id="beca-nombre" type="text" wire:model="nombre" maxlength="150" />
                    </x-ui.field>
                </div>

                <div>
                    <x-ui.field label="Tipo" for="beca-tipo" required :error="$errors->first('tipo')">
                        <x-ui.select id="beca-tipo" wire:model="tipo">
                            @foreach (\App\Enums\TipoBeca::cases() as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>

                <div>
                    <x-ui.field label="Valor" for="beca-valor" required :error="$errors->first('valor')">
                        <div class="flex items-stretch gap-2">
                            <x-ui.input id="beca-valor" class="ui-input flex-1" type="number"
                                wire:model="valor" step="0.01" min="0" />
                            <span class="flex min-w-11 items-center justify-center rounded-lg border border-brand-line bg-brand-surface px-3 text-sm font-semibold text-brand-muted">
                                {{ $tipo->value === 'PORCENTAJE' ? '%' : '$' }}
                            </span>
                        </div>
                    </x-ui.field>
                </div>

                <div>
                    <x-ui.field label="Alcance" for="beca-alcance" required :error="$errors->first('alcance')">
                        <x-ui.select id="beca-alcance" wire:model="alcance">
                            @foreach (\App\Enums\AlcanceBeca::cases() as $a)
                                <option value="{{ $a->value }}">{{ $a->label() }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>

                <div>
                    <x-ui.field label="Periodo" for="beca-periodo" required :error="$errors->first('periodo')">
                        <x-ui.select id="beca-periodo" wire:model="periodo">
                            <option value="">Seleccionar periodo</option>
                            @foreach (\App\Models\Periodo::orderBy('fecha_inicio', 'desc')->get() as $p)
                                <option value="{{ $p->id }}">{{ $p->clave }} - {{ $p->nombre }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>

                <div class="flex items-end pb-6">
                    <label class="inline-flex cursor-pointer items-center gap-2.5">
                        <input type="checkbox" id="activo" wire:model="activo"
                            class="h-4 w-4 rounded border-brand-line accent-brand-primary" />
                        <span class="text-sm font-medium text-brand-text">Activa</span>
                    </label>
                </div>

                <div>
                    <x-ui.field label="Fecha de inicio" for="beca-fi" required :error="$errors->first('fecha_inicio')">
                        <x-ui.input id="beca-fi" type="date" wire:model="fecha_inicio" />
                    </x-ui.field>
                </div>

                <div>
                    <x-ui.field label="Fecha de fin" for="beca-ff" required :error="$errors->first('fecha_fin')">
                        <x-ui.input id="beca-ff" type="date" wire:model="fecha_fin" />
                    </x-ui.field>
                </div>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="$set('modalBecaAbierto', false)">Cancelar</x-ui.button>
                <x-ui.button type="submit" icon="check" wire:loading.attr="disabled" wire:target="guardarBeca">
                    <span wire:loading.remove wire:target="guardarBeca">{{ $beca ? 'Actualizar' : 'Crear' }}</span>
                    <span wire:loading wire:target="guardarBeca"><x-ui.loading label="Guardando…" /></span>
                </x-ui.button>
            </x-slot:footer>
        </form>
    </x-ui.modal>
@endif
