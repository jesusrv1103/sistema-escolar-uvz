<div>
    <x-ui.page-header :title="'Contactos — ' . $alumno->apellidos . ', ' . $alumno->nombre"
        description="Tutor y contacto de emergencia pueden ser personas distintas.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-left" href="{{ route('alumnos.ver', $alumno) }}">
                Volver al expediente
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('expediente'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('expediente') }}</x-ui.alert></div>
    @endif

    @unless ($puedeEditar)
        <div class="mt-4">
            <x-ui.alert tone="neutral">Solo lectura: tu rol no puede modificar los contactos.</x-ui.alert>
        </div>
    @endunless

    <div class="mt-5 grid gap-6 lg:grid-cols-2">
        @foreach (['tutor' => 'Contacto de tutor', 'emergencia' => 'Contacto de emergencia'] as $propiedad => $titulo)
            <x-ui.card>
                <x-slot:header>
                    <p class="text-sm font-semibold text-brand-text">{{ $titulo }}</p>
                </x-slot:header>

                <form wire:submit="guardar{{ $propiedad === 'tutor' ? 'Tutor' : 'Emergencia' }}" class="space-y-4">
                    <x-ui.field :label="'Nombre completo'" :error="$errors->first($propiedad . '.nombre')">
                        <x-ui.input wire:model="{{ $propiedad }}.nombre" maxlength="150"
                            :disabled="! $puedeEditar" />
                    </x-ui.field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Parentesco" :error="$errors->first($propiedad . '.parentesco')">
                            <x-ui.input wire:model="{{ $propiedad }}.parentesco" maxlength="60"
                                placeholder="Madre, Padre…" :disabled="! $puedeEditar" />
                        </x-ui.field>

                        <x-ui.field label="Teléfono" :error="$errors->first($propiedad . '.telefono')">
                            <x-ui.input wire:model="{{ $propiedad }}.telefono" maxlength="20"
                                placeholder="5512345678" :disabled="! $puedeEditar" />
                        </x-ui.field>

                        <x-ui.field label="Teléfono alterno" :error="$errors->first($propiedad . '.telefono_alt')">
                            <x-ui.input wire:model="{{ $propiedad }}.telefono_alt" maxlength="20"
                                :disabled="! $puedeEditar" />
                        </x-ui.field>

                        <x-ui.field label="Correo" :error="$errors->first($propiedad . '.correo')">
                            <x-ui.input type="email" wire:model.blur="{{ $propiedad }}.correo" maxlength="150"
                                :disabled="! $puedeEditar" />
                        </x-ui.field>

                        <div class="sm:col-span-2">
                            <x-ui.field label="Notas" :error="$errors->first($propiedad . '.notas')">
                                <x-ui.textarea wire:model="{{ $propiedad }}.notas" :rows="2"
                                    :disabled="! $puedeEditar" />
                            </x-ui.field>
                        </div>
                    </div>

                    @if ($puedeEditar)
                        <div class="flex flex-wrap items-center gap-2 border-t border-brand-line pt-4">
                            <x-ui.button type="submit" wire:loading.attr="disabled">
                                <span wire:loading.remove>Guardar</span>
                                <span wire:loading><x-ui.loading label="Guardando…" /></span>
                            </x-ui.button>
                            @if ($ids[$propiedad] ?? null)
                                <x-ui.button variant="danger" icon="x-mark"
                                    wire:click="eliminar({{ $ids[$propiedad] }})"
                                    wire:confirm="¿Eliminar este contacto?">
                                    Eliminar
                                </x-ui.button>
                            @endif
                        </div>
                    @endif
                </form>
            </x-ui.card>
        @endforeach
    </div>
</div>
