<div>
    <x-ui.page-header :title="'Información médica — ' . $alumno->apellidos . ', ' . $alumno->nombre"
        description="Acceso restringido (RB-18): solo ADMIN y ADMISIONES.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-left" href="{{ route('alumnos.ver', $alumno) }}">
                Volver al expediente
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('expediente'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('expediente') }}</x-ui.alert></div>
    @endif

    <div class="mt-5">
        <x-ui.card>
            <x-slot:header>
                <div class="flex items-center gap-2">
                    <x-ui.icon name="exclamation-triangle" :size="16" class="text-brand-primary" />
                    <p class="text-sm font-semibold text-brand-text">Información sensible</p>
                </div>
            </x-slot:header>

            <p class="mb-4 text-sm text-brand-muted">
                No aparece en listados, búsquedas, dashboard ni logs. Se guarda con tu usuario como responsable
                de la actualización.
            </p>

            <form wire:submit="guardar" class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Tipo de sangre" for="tipo_sangre" :error="$errors->first('tipo_sangre')">
                    <x-ui.select id="tipo_sangre" wire:model="tipo_sangre">
                        <option value="">No capturado</option>
                        @foreach ($tiposSangre as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <div class="flex items-end pb-1">
                    <label class="inline-flex cursor-pointer items-center gap-2.5">
                        <input type="checkbox" wire:model.live="tiene_discapacidad" value="1"
                            class="h-4 w-4 rounded border-brand-line accent-brand-primary" />
                        <span class="text-sm font-medium text-brand-text">Presenta discapacidad</span>
                    </label>
                </div>
                @error('tiene_discapacidad')
                    <p class="ui-error sm:col-span-2" role="alert">{{ $message }}</p>
                @enderror

                <div class="sm:col-span-2">
                    <x-ui.field label="Alergias" for="alergias" :error="$errors->first('alergias')">
                        <x-ui.textarea id="alergias" wire:model="alergias" :rows="2" maxlength="2000"
                            placeholder="Ninguna / describir" />
                    </x-ui.field>
                </div>

                <div class="sm:col-span-2">
                    <x-ui.field label="Condiciones médicas previas" for="condiciones"
                        :error="$errors->first('condiciones')">
                        <x-ui.textarea id="condiciones" wire:model="condiciones" :rows="2" maxlength="2000" />
                    </x-ui.field>
                </div>

                <div class="sm:col-span-2">
                    <x-ui.field label="Medicamentos actuales" for="medicamentos"
                        :error="$errors->first('medicamentos')">
                        <x-ui.textarea id="medicamentos" wire:model="medicamentos" :rows="2" maxlength="2000" />
                    </x-ui.field>
                </div>

                @if ($tiene_discapacidad)
                    <div class="sm:col-span-2">
                        <x-ui.field label="Detalle de la discapacidad" for="detalle_discapacidad" required
                            :error="$errors->first('detalle_discapacidad')">
                            <x-ui.textarea id="detalle_discapacidad" wire:model="detalle_discapacidad" :rows="2"
                                maxlength="2000" />
                        </x-ui.field>
                    </div>
                @endif

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-brand-line pt-4 sm:col-span-2">
                    <x-ui.button variant="secondary" href="{{ route('alumnos.ver', $alumno) }}">Cancelar</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="guardar">
                        <span wire:loading.remove wire:target="guardar">Guardar</span>
                        <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
