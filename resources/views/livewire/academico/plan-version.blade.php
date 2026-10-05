<div>
    <x-ui.page-header
        :title="$plan ? 'Versiones del plan' : 'Versiones de plan'"
        :description="$plan ? $plan->nombre . ' (' . $plan->clave . ') · control de vigencia y aprobación de versiones.'
            : 'Selecciona un plan de estudio desde el listado principal para gestionar sus versiones.'">
        <x-slot:actions>
            <x-ui.button wire:click="abrirModalCrear" icon="plus" :disabled="!$plan">Nueva versión</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (! $plan)
        <div class="mt-4">
            <x-ui.alert tone="warning">
                Selecciona un plan de estudio desde el listado principal para gestionar sus versiones.
            </x-ui.alert>
        </div>
    @endif

    @if (session('success'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('success') }}</x-ui.alert></div>
    @endif

    @if ($errors->any())
        <div class="mt-4">
            <x-ui.alert tone="danger">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        </div>
    @endif

    <div class="mt-5">
        <x-ui.card :flush="$versiones->isNotEmpty()">
            @if ($versiones->isEmpty())
                <x-ui.empty-state icon="folder"
                    :title="$plan ? 'No hay versiones para este plan' : 'No hay versiones registradas'"
                    message="Crea la primera versión para empezar a documentar la vigencia del plan.">
                    <x-slot:action>
                        <x-ui.button wire:click="abrirModalCrear" icon="plus" :disabled="!$plan">Nueva versión
                        </x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Versiones del plan de estudio</caption>
                        <thead>
                            <tr>
                                <th scope="col">Versión</th>
                                <th scope="col">Fecha de vigencia</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Aprobado por</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($versiones as $v)
                                @php
                                    $tono = match ($v->estatus->value) {
                                        'VIGENTE' => 'success',
                                        'PROPUESTA' => 'warning',
                                        default => 'neutral',
                                    };
                                @endphp
                                <tr wire:key="version-{{ $v->id }}">
                                    <td class="font-medium text-brand-primary">{{ $v->version }}</td>
                                    <td class="text-brand-muted">{{ $v->fecha_vigencia?->format('d/m/Y') ?? '—' }}</td>
                                    <td><x-ui.badge :tone="$tono">{{ $v->estatus->label() }}</x-ui.badge></td>
                                    <td class="text-brand-muted">
                                        @if ($v->aprobadoPor)
                                            <span class="block">{{ $v->aprobadoPor->name }}</span>
                                            <span class="block text-xs">{{ $v->aprobado_at?->format('d/m/Y H:i') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <div class="ui-row-actions">
                                            @if ($v->esPropuesta())
                                                <x-ui.icon-button label="Editar versión {{ $v->version }}" icon="pencil"
                                                    wire:click="abrirModalEditar({{ $v->id }})" />
                                                <x-ui.icon-button label="Aprobar versión {{ $v->version }}" icon="check-circle"
                                                    variant="primary" wire:click="abrirModalAprobar({{ $v->id }})" />
                                                <x-ui.icon-button label="Eliminar versión {{ $v->version }}" icon="x-mark"
                                                    variant="danger" wire:click="eliminar({{ $v->id }})"
                                                    wire:confirm="¿Eliminar la versión {{ $v->version }}? (solo versiones en Propuesta)" />
                                            @else
                                                <span class="text-sm text-brand-muted" aria-label="Sin acciones disponibles">—</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-brand-line px-5 py-3">
                    {{ $versiones->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>

    {{-- ===================== MODAL CREAR / EDITAR ===================== --}}
    <x-ui.modal id="modal-version"
        :title="$versionEnEdicion ? 'Editar versión' : 'Nueva versión'"
        :open="$modalAbierto" close="cerrarModal">
        <form wire:submit="guardar" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Versión" for="version-numero" required :error="$errors->first('version')">
                    <x-ui.input id="version-numero" wire:model="version" maxlength="20" placeholder="Ej. 1.0 o 2024-1" />
                </x-ui.field>

                <x-ui.field label="Fecha de vigencia" for="version-fecha"
                    hint="Opcional para Propuesta; obligatoria al aprobar."
                    :error="$errors->first('fechaVigencia')">
                    <x-ui.input id="version-fecha" type="date" wire:model="fechaVigencia" />
                </x-ui.field>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-brand-line pt-4">
                <x-ui.button variant="secondary" wire:click="cerrarModal">Cancelar</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">
                        {{ $versionEnEdicion ? 'Guardar cambios' : 'Crear versión' }}
                    </span>
                    <span wire:loading wire:target="guardar"><x-ui.loading label="Guardando…" /></span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- ======================= MODAL APROBAR ======================= --}}
    <x-ui.modal id="modal-aprobar-version" size="sm" title="Confirmar aprobación"
        :open="$modalAprobarAbierto" close="cerrarModalAprobar">
        <div class="space-y-3">
            <p class="text-sm text-brand-text">
                ¿Aprobar la versión <strong>{{ $versionAAprobar->version ?? '' }}</strong>?
            </p>
            <p class="text-sm text-brand-muted">
                Esto cambia su estado de
                <x-ui.badge tone="warning">Propuesta</x-ui.badge>
                a
                <x-ui.badge tone="success">Vigente</x-ui.badge>.
            </p>

            @if ($versionAAprobar?->fecha_vigencia)
                <p class="text-sm text-brand-muted">
                    Fecha de vigencia:
                    <strong>{{ $versionAAprobar->fecha_vigencia->format('d/m/Y') }}</strong>
                </p>
            @endif

            <x-ui.alert tone="warning">
                Si ya existe una versión Vigente para este plan, pasará automáticamente a
                <x-ui.badge tone="neutral">Superada</x-ui.badge>.
            </x-ui.alert>
        </div>

        <x-slot:footer>
            <x-ui.button variant="secondary" wire:click="cerrarModalAprobar">Cancelar</x-ui.button>
            <x-ui.button wire:click="confirmarAprobar" icon="check" wire:loading.attr="disabled"
                wire:target="confirmarAprobar">
                <span wire:loading.remove wire:target="confirmarAprobar">Aprobar</span>
                <span wire:loading wire:target="confirmarAprobar"><x-ui.loading label="Aprobando…" /></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
