<div>
    <x-ui.modal id="modal-asistencia-form" size="md"
        :title="$asistenciaExistente ? 'Editar Asistencia' : 'Registrar Asistencia'"
        :open="$modalAbierto" close="$set('modalAbierto', false)">
        @if ($inscripcion !== null)
            <form wire:submit="guardar">
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

                    <x-ui.field label="Alumno">
                        <p class="text-sm font-medium text-brand-text">
                            {{ $inscripcion->matriculaPeriodo->alumno->nombre_completo }}
                        </p>
                        <p class="text-xs text-brand-muted">
                            {{ $inscripcion->matriculaPeriodo->alumno->numero_control ?? 'Sin número de control' }}
                        </p>
                    </x-ui.field>

                    <x-ui.field label="Fecha" for="asist-fecha" required :error="$errors->first('fecha')">
                        <x-ui.input id="asist-fecha" type="date" wire:model="fecha" />
                    </x-ui.field>

                    <x-ui.field label="Asistencia">
                        <label class="inline-flex cursor-pointer items-center gap-2.5">
                            <input type="checkbox" id="presente" wire:model="presente"
                                class="h-4 w-4 rounded border-brand-line accent-brand-primary" />
                            <x-ui.badge :tone="$presente ? 'success' : 'neutral'">
                                {{ $presente ? 'Presente' : 'Ausente' }}
                            </x-ui.badge>
                        </label>
                    </x-ui.field>

                    @if ($asistenciaExistente)
                        <x-ui.alert tone="info">
                            Editando registro existente (ID: {{ $asistenciaExistente->id }})
                        </x-ui.alert>
                    @endif
                </div>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="$set('modalAbierto', false)">Cancelar</x-ui.button>
                    <x-ui.button type="submit" icon="check"
                        wire:loading.attr="disabled" wire:target="guardar">
                        <span wire:loading.remove wire:target="guardar">Guardar</span>
                        <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                    </x-ui.button>
                </x-slot:footer>
            </form>
        @endif
    </x-ui.modal>
</div>
