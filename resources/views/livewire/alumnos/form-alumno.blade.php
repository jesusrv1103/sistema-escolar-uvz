<div>
    <x-ui.page-header
        :title="$alumno ? 'Expediente: ' . $alumno->apellidos . ', ' . $alumno->nombre : 'Nuevo alumno'"
        description="Datos personales y domicilio del expediente permanente.">
        <x-slot:meta>
            @if ($alumno && $alumno->numero_control)
                <x-ui.badge tone="primary">Núm. control {{ $alumno->numero_control }}</x-ui.badge>
            @elseif ($alumno)
                <x-ui.badge tone="neutral">Núm. control pendiente (se asigna al finalizar la primera inscripción)</x-ui.badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-left" href="{{ route('alumnos.lista') }}">
                Volver a alumnos
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('expediente'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('expediente') }}</x-ui.alert></div>
    @endif

    @if ($alumno)
        <nav class="mt-4 flex flex-wrap gap-2" aria-label="Secciones del expediente">
            <x-ui.button size="sm" variant="secondary" href="{{ route('alumnos.contactos', $alumno) }}">
                Contactos
            </x-ui.button>
            @can('informacionMedica.edit')
                <x-ui.button size="sm" variant="secondary" href="{{ route('alumnos.info-medica', $alumno) }}">
                    Información médica
                </x-ui.button>
            @endcan
        </nav>
    @endif

    <div class="mt-5">
        <x-ui.card>
            <x-slot:header>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-brand-text">Datos personales</p>
                    @unless ($puedeEditar)
                        <p class="mt-0.5 text-xs text-brand-muted">
                            Solo lectura: tu rol no puede modificar el expediente.
                        </p>
                    @endunless
                </div>
            </x-slot:header>

            <form novalidate wire:submit="guardar" class="space-y-6">
                {{-- Identificación --}}
                <fieldset class="grid gap-4 sm:grid-cols-2">
                    <legend class="ui-section-title mb-2 w-full">Identificación</legend>

                    <x-ui.field label="Nombre(s)" for="nombre" required :error="$errors->first('nombre')">
                        <x-ui.input id="nombre" wire:model="nombre" maxlength="100"
                            :disabled="! $puedeEditar" />
                    </x-ui.field>

                    <x-ui.field label="Apellidos" for="apellidos" required :error="$errors->first('apellidos')">
                        <x-ui.input id="apellidos" wire:model="apellidos" maxlength="100"
                            :disabled="! $puedeEditar" />
                    </x-ui.field>

                    <x-ui.field label="CURP" for="curp" required
                        hint="Validación sintáctica local (sin consulta RENAPO)."
                        :error="$errors->first('curp')">
                        <x-ui.input id="curp" wire:model.blur="curp" maxlength="18" placeholder="18 caracteres"
                            class="font-mono uppercase" :disabled="! $puedeEditar" />
                    </x-ui.field>

                    <x-ui.field label="Fecha de nacimiento" for="fecha_nacimiento" required
                        :error="$errors->first('fecha_nacimiento')">
                        <x-ui.input id="fecha_nacimiento" type="date" wire:model="fecha_nacimiento"
                            :disabled="! $puedeEditar" />
                    </x-ui.field>

                    <x-ui.field label="Sexo" for="sexo" required :error="$errors->first('sexo')">
                        <x-ui.select id="sexo" wire:model="sexo" :disabled="! $puedeEditar">
                            <option value="">Selecciona…</option>
                            @foreach ($sexos as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field label="Nacionalidad" for="nacionalidad" :error="$errors->first('nacionalidad')">
                        <x-ui.input id="nacionalidad" wire:model="nacionalidad" maxlength="60"
                            :disabled="! $puedeEditar" />
                    </x-ui.field>

                    <x-ui.field label="Correo electrónico" for="correo" :error="$errors->first('correo')">
                        <x-ui.input id="correo" type="email" wire:model.blur="correo" maxlength="150"
                            :disabled="! $puedeEditar" />
                    </x-ui.field>

                    <x-ui.field label="Teléfono" for="telefono" :error="$errors->first('telefono')">
                        <x-ui.input id="telefono" wire:model="telefono" maxlength="20" placeholder="5512345678"
                            :disabled="! $puedeEditar" />
                    </x-ui.field>
                </fieldset>

                {{-- Domicilio --}}
                <fieldset class="grid gap-4 border-t border-brand-line pt-5 sm:grid-cols-2">
                    <legend class="ui-section-title mb-2 w-full">Domicilio</legend>

                    <x-ui.field label="Calle" for="calle" class="sm:col-span-2" :error="$errors->first('calle')">
                        <x-ui.input id="calle" wire:model="calle" maxlength="150" :disabled="! $puedeEditar" />
                    </x-ui.field>

                    <x-ui.field label="Colonia" for="colonia" :error="$errors->first('colonia')">
                        <x-ui.input id="colonia" wire:model="colonia" maxlength="120" :disabled="! $puedeEditar" />
                    </x-ui.field>

                    <x-ui.field label="Código postal" for="cp" placeholder="98000"
                        :error="$errors->first('cp')">
                        <x-ui.input id="cp" wire:model="cp" maxlength="10" placeholder="98000"
                            :disabled="! $puedeEditar" />
                    </x-ui.field>

                    <x-ui.field label="Municipio" for="municipio" :error="$errors->first('municipio')">
                        <x-ui.input id="municipio" wire:model="municipio" maxlength="120"
                            :disabled="! $puedeEditar" />
                    </x-ui.field>

                    <x-ui.field label="Estado" for="estado" :error="$errors->first('estado')">
                        <x-ui.input id="estado" wire:model="estado" maxlength="120" :disabled="! $puedeEditar" />
                    </x-ui.field>

                    <x-ui.field label="País" for="pais" :error="$errors->first('pais')">
                        <x-ui.input id="pais" wire:model="pais" maxlength="80" :disabled="! $puedeEditar" />
                    </x-ui.field>
                </fieldset>

                @if ($puedeEditar)
                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-brand-line pt-4">
                        <x-ui.button variant="secondary" href="{{ route('alumnos.lista') }}">Cancelar</x-ui.button>
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="guardar">
                            <span wire:loading.remove wire:target="guardar">Guardar</span>
                            <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                        </x-ui.button>
                    </div>
                @endif
            </form>
        </x-ui.card>
    </div>
</div>
