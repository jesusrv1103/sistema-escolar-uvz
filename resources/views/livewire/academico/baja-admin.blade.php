<div>
    <x-ui.page-header title="Bajas"
        description="Baja temporal, definitiva o por materia; el reingreso es exclusivo de la baja temporal.">
        <x-slot:actions>
            @foreach (\App\Enums\TipoBaja::cases() as $tipo)
                @can('baja.crear')
                    <x-ui.button variant="secondary" icon="no-symbol"
                        wire:click="abrirModalBaja('{{ $tipo->value }}')">
                        {{ $tipo->label() }}
                    </x-ui.button>
                @endcan
            @endforeach
        </x-slot:actions>
    </x-ui.page-header>

    @error('reingreso')
        <div class="mt-4"><x-ui.alert tone="danger">{{ $message }}</x-ui.alert></div>
    @enderror

    {{-- ------------------------- Filtros compactos ------------------------- --}}
    <div class="mt-5">
        <x-ui.filters>
            <div class="grid gap-3 md:grid-cols-3">
                <x-ui.field label="Buscar">
                    <x-ui.input type="text" wire:model.live.debounce.300ms="busqueda"
                        placeholder="Alumno, motivo…" aria-label="Buscar bajas" />
                </x-ui.field>

                <x-ui.field label="Tipo">
                    <x-ui.select wire:model.live="filtroTipo" aria-label="Filtrar por tipo">
                        <option value="">Todos</option>
                        @foreach ($tipos as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            </div>

            <div class="mt-3 flex justify-end border-t border-brand-line pt-3">
                <x-ui.button size="sm" variant="ghost" icon="x-mark" wire:click="limpiarFiltros">
                    Limpiar filtros
                </x-ui.button>
            </div>
        </x-ui.filters>
    </div>

    {{-- ------------------------------ Listado ------------------------------ --}}
    <div class="mt-4">
        <x-ui.card :flush="count($bajas) > 0">
            @if (session('success'))
                <div class="px-5 pt-4"><x-ui.alert tone="success">{{ session('success') }}</x-ui.alert></div>
            @endif

            @if (count($bajas) === 0)
                <x-ui.empty-state icon="no-symbol" title="Sin bajas registradas"
                    message="Ajusta los filtros o registra una nueva baja con los botones del encabezado." />
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th scope="col">Fecha</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Alumno</th>
                                <th scope="col">Materia</th>
                                <th scope="col">Motivo</th>
                                <th scope="col">Registrado por</th>
                                <th scope="col">Estatus</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bajas as $baja)
                                <tr wire:key="baja-{{ $baja->id }}">
                                    <td class="whitespace-nowrap">{{ $baja->fecha->format('d/m/Y') }}</td>
                                    <td>
                                        <x-ui.badge :tone="match ($baja->tipo->value) {
                                            'DEFINITIVA' => 'danger',
                                            'TEMPORAL' => 'warning',
                                            'MATERIA' => 'info',
                                            default => 'neutral',
                                        }">
                                            {{ $baja->tipo->label() }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="font-medium text-brand-text">{{ $baja->alumno->nombre_completo }}</td>
                                    <td class="whitespace-nowrap text-brand-muted">
                                        {{ $baja->inscripcionMateria?->materia->clave ?? '—' }}
                                    </td>
                                    <td class="max-w-[280px] text-brand-muted">{{ \Illuminate\Support\Str::limit($baja->motivo, 50) }}</td>
                                    <td class="text-brand-muted">{{ $baja->registradoPor?->name ?? '—' }}</td>
                                    <td>
                                        @if ($baja->esTemporal())
                                            @if ($baja->tieneReingreso())
                                                <x-ui.badge tone="success">Reingresado</x-ui.badge>
                                            @else
                                                <x-ui.badge tone="warning">Vigente</x-ui.badge>
                                            @endif
                                        @elseif ($baja->esDefinitiva())
                                            <x-ui.badge tone="danger">Definitiva</x-ui.badge>
                                        @else
                                            <x-ui.badge tone="info">Materia</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($baja->esTemporal() && ! $baja->tieneReingreso())
                                            <div class="ui-row-actions" role="group"
                                                aria-label="Acciones de la baja de {{ $baja->alumno->nombre_completo }}">
                                                <x-ui.button size="sm" variant="success" icon="arrow-right"
                                                    wire:click="abrirModalReingreso({{ $baja->id }})">
                                                    Reingreso
                                                </x-ui.button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-brand-line px-5 py-3">{{ $bajas->links() }}</div>
            @endif
        </x-ui.card>
    </div>

    {{-- -------------------------- Modal: registrar baja -------------------------- --}}
    @if ($modalBajaAbierto)
        <x-ui.modal id="modal-registrar-baja" size="lg" :title="'Registrar Baja ' . $tipoBaja"
            :open="true" close="$set('modalBajaAbierto', false)">
            <form wire:submit="registrarBaja">
                <div class="grid gap-3 md:grid-cols-2">
                    @if ($errors->any())
                        <div class="md:col-span-2">
                            <x-ui.alert tone="danger">
                                <ul class="list-inside list-disc space-y-0.5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </x-ui.alert>
                        </div>
                    @endif

                    <x-ui.field label="Alumno" for="baja-alumno" required :error="$errors->first('alumno')">
                        <x-ui.select id="baja-alumno" wire:model="alumno">
                            <option value="">Seleccionar alumno</option>
                            @foreach (\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                                <option value="{{ $a->id }}">{{ $a->nombre_completo }} ({{ $a->curp }})</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    @if ($tipoBaja === 'MATERIA')
                        <x-ui.field label="Materia (Inscripción)" for="baja-inscripcion" required
                            :error="$errors->first('inscripcion')">
                            <x-ui.select id="baja-inscripcion" wire:model="inscripcion">
                                <option value="">Seleccionar inscripción</option>
                                @foreach (\App\Models\InscripcionMateria::with('grupo.materia', 'matriculaPeriodo.alumno')
                                    ->where('estatus', '!=', 'BAJA')->get() as $i)
                                    <option value="{{ $i->id }}">
                                        {{ $i->matriculaPeriodo->alumno->nombre_completo }} - {{ $i->grupo->materia->clave }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    @endif

                    <x-ui.field label="Fecha" for="baja-fecha" required :error="$errors->first('fecha')">
                        <x-ui.input id="baja-fecha" type="date" wire:model="fecha"
                            :max="now()->format('Y-m-d')" />
                    </x-ui.field>

                    <div class="md:col-span-2">
                        <x-ui.field label="Motivo" for="baja-motivo" required :error="$errors->first('motivo')"
                            hint="Mínimo 5 y máximo 2000 caracteres.">
                            <textarea id="baja-motivo" wire:model="motivo" rows="3" minlength="5" maxlength="2000"
                                class="ui-textarea"
                                placeholder="Motivo de la baja (mínimo 5 caracteres, máximo 2000)"></textarea>
                        </x-ui.field>
                    </div>
                </div>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="$set('modalBajaAbierto', false)">Cancelar</x-ui.button>
                    <x-ui.button type="submit" icon="check"
                        wire:loading.attr="disabled" wire:target="registrarBaja">
                        <span wire:loading.remove wire:target="registrarBaja">Registrar Baja</span>
                        <span wire:loading wire:target="registrarBaja"><x-ui.loading label="Registrando…" /></span>
                    </x-ui.button>
                </x-slot:footer>
            </form>
        </x-ui.modal>
    @endif

    {{-- -------------------------- Modal: reingreso -------------------------- --}}
    @if ($modalReingresoAbierto)
        <x-ui.modal id="modal-reingreso" title="Autorizar Reingreso"
            :open="true" close="$set('modalReingresoAbierto', false)">
            <form wire:submit="autorizarReingreso">
                <div class="space-y-3">
                    <x-ui.alert tone="info">
                        Autorizando reingreso para
                        <strong>{{ $baja?->alumno->nombre_completo }}</strong>
                        (Baja {{ $baja?->tipo->label() }} del {{ $baja?->fecha->format('d/m/Y') }}).
                    </x-ui.alert>

                    <x-ui.field label="Fecha y hora de reingreso" for="reingreso-at" required
                        :error="$errors->first('reingresoAt')">
                        <x-ui.input id="reingreso-at" type="datetime-local" wire:model="reingresoAt"
                            :max="now()->format('Y-m-d\TH:i')" />
                    </x-ui.field>

                    @error('reingreso')
                        <p class="ui-error">{{ $message }}</p>
                    @enderror
                </div>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="$set('modalReingresoAbierto', false)">Cancelar</x-ui.button>
                    <x-ui.button variant="success" icon="check"
                        wire:loading.attr="disabled" wire:target="autorizarReingreso">
                        <span wire:loading.remove wire:target="autorizarReingreso">Autorizar Reingreso</span>
                        <span wire:loading wire:target="autorizarReingreso"><x-ui.loading label="Autorizando…" /></span>
                    </x-ui.button>
                </x-slot:footer>
            </form>
        </x-ui.modal>
    @endif
</div>
