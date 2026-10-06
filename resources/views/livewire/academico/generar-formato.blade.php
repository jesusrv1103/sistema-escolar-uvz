<div>
    <x-ui.page-header title="Generar formatos académicos"
        description="Flujo guiado: elige el formato, el alumno, el grupo si aplica y genera el PDF con folio institucional." />

    {{-- ======================= 1. TIPO DE FORMATO ======================= --}}
    <section class="mt-5" aria-labelledby="paso-formato">
        <p id="paso-formato" class="ui-section-title mb-2">1 · Seleccionar tipo de formato</p>
        <x-ui.card>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($tiposFormato as $tipo)
                    @php $seleccionado = $tipoFormato === $tipo->value; @endphp
                    <label for="tipo_{{ $tipo->value }}" @class([
                        'group flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors',
                        'border-brand-primary bg-brand-surface' => $seleccionado,
                        'border-brand-line bg-white hover:border-brand-muted/60' => !$seleccionado,
                    ])>
                        <input type="radio" name="tipoFormato" id="tipo_{{ $tipo->value }}"
                            value="{{ $tipo->value }}" wire:model.live="tipoFormato"
                            class="mt-1 h-4 w-4 accent-brand-primary" />
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-brand-text">{{ $tipo->label() }}</span>
                            <span class="block text-xs text-brand-muted">
                                {{ $tipo->requiereFolio() ? 'Genera folio (CI-YYYY-########)' : 'Sin folio' }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>

            @error('tipoFormato')
                <p class="ui-error mt-3" role="alert">{{ $message }}</p>
            @enderror
        </x-ui.card>
    </section>

    {{-- ========================= 2. ALUMNO ========================= --}}
    <section class="mt-6" aria-labelledby="paso-alumno">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <p id="paso-alumno" class="ui-section-title">2 · Seleccionar alumno</p>
            <x-ui.button size="sm" variant="secondary" icon="magnifying-glass" wire:click="abrirModalAlumno"
                :disabled="! $tipoFormato">
                Buscar alumno
            </x-ui.button>
        </div>

        <x-ui.card>
            @if ($alumnoSeleccionado)
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-brand-text">
                            {{ $alumnoSeleccionado->nombre_completo }}
                        </p>
                        <p class="mt-1 flex flex-wrap gap-1.5">
                            <x-ui.badge tone="neutral">CURP {{ $alumnoSeleccionado->curp }}</x-ui.badge>
                            <x-ui.badge tone="info">
                                {{ $alumnoSeleccionado->numero_control ?? 'Sin número de control' }}
                            </x-ui.badge>
                        </p>
                    </div>
                    <x-ui.button size="sm" variant="secondary" icon="x-mark" wire:click="limpiarSeleccion">
                        Cambiar
                    </x-ui.button>
                </div>

                <div class="mt-4 border-t border-brand-line pt-4">
                    @if ($matriculaSeleccionada)
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 text-sm text-brand-muted">
                                <span class="font-medium text-brand-text">Matrícula activa:</span>
                                {{ $matriculaSeleccionada->periodo->clave }} — {{ $matriculaSeleccionada->periodo->nombre }}
                                <span class="mt-1 flex flex-wrap gap-1.5">
                                    <x-ui.badge :tone="match ($matriculaSeleccionada->estatus->value) {
                                        'ACTIVA' => 'success',
                                        'BAJA_TEMPORAL' => 'warning',
                                        'BAJA_DEFINITIVA' => 'danger',
                                        'EGRESADO' => 'primary',
                                        default => 'neutral',
                                    }">
                                        {{ $matriculaSeleccionada->estatus->label() }}
                                    </x-ui.badge>
                                    <x-ui.badge tone="neutral">
                                        {{ $matriculaSeleccionada->programa->clave }} — {{ $matriculaSeleccionada->programa->nombre }}
                                    </x-ui.badge>
                                </span>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-brand-muted">El alumno no tiene matrícula activa.</p>
                    @endif
                </div>
            @else
                <x-ui.empty-state icon="user" title="Selecciona un alumno para continuar"
                    message="Usa el buscador para elegir el alumno sobre el cual se generará el formato.">
                    <x-slot:action>
                        <x-ui.button size="sm" variant="secondary" icon="magnifying-glass"
                            wire:click="abrirModalAlumno" :disabled="! $tipoFormato">
                            Buscar alumno
                        </x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @endif
        </x-ui.card>
    </section>

    {{-- ========================= 3. GRUPO ========================= --}}
    @if (in_array($tipoFormato, ['HORARIO', 'LISTA_GRUPO']))
        <section class="mt-6" aria-labelledby="paso-grupo">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <p id="paso-grupo" class="ui-section-title">3 · Seleccionar grupo</p>
                <x-ui.button size="sm" variant="secondary" icon="magnifying-glass" wire:click="abrirModalGrupo">
                    Buscar grupo
                </x-ui.button>
            </div>

            <x-ui.card>
                @if ($grupoSeleccionado)
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-brand-text">
                                {{ $grupoSeleccionado->clave }} — {{ $grupoSeleccionado->materia->nombre }}
                            </p>
                            <p class="mt-1 flex flex-wrap gap-1.5">
                                <x-ui.badge tone="neutral">{{ $grupoSeleccionado->periodo->clave }}</x-ui.badge>
                                <x-ui.badge tone="info">
                                    {{ $grupoSeleccionado->docente?->nombreCompleto() ?? 'Sin docente' }}
                                </x-ui.badge>
                                <x-ui.badge tone="neutral">
                                    {{ $grupoSeleccionado->aula?->nombre ?? 'Sin aula' }}
                                </x-ui.badge>
                            </p>
                        </div>
                        <x-ui.button size="sm" variant="secondary" icon="x-mark"
                            wire:click="$set('grupoSeleccionado', null)">
                            Cambiar
                        </x-ui.button>
                    </div>
                @else
                    <x-ui.empty-state icon="users" title="Selecciona un grupo para continuar"
                        message="Este formato necesita un grupo concreto (horario o lista de asistencia).">
                        <x-slot:action>
                            <x-ui.button size="sm" variant="secondary" icon="magnifying-glass"
                                wire:click="abrirModalGrupo">
                                Buscar grupo
                            </x-ui.button>
                        </x-slot:action>
                    </x-ui.empty-state>
                @endif
            </x-ui.card>
        </section>
    @endif

    {{-- ========================= 4. GENERAR ========================= --}}
    <section class="mt-6" aria-labelledby="paso-generar">
        <p id="paso-generar" class="ui-section-title mb-2">4 · Generar</p>
        <x-ui.card>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-brand-text">
                        {{ $tipoFormato !== '' ? \App\Enums\TipoFormato::tryFrom($tipoFormato)?->label() ?? $tipoFormato : 'Formato sin seleccionar' }}
                    </p>
                    <p class="text-sm text-brand-muted">
                        @if (! $alumnoSeleccionado)
                            Falta seleccionar el alumno.
                        @elseif (in_array($tipoFormato, ['HORARIO', 'LISTA_GRUPO']) && ! $grupoSeleccionado)
                            Falta seleccionar el grupo.
                        @else
                            Listo para generar el documento.
                        @endif
                    </p>
                </div>

                <x-ui.button size="lg" icon="document-text"
                    wire:click="generar"
                    wire:loading.attr="disabled" wire:target="generar"
                    :disabled="! $tipoFormato || ! $alumnoSeleccionado
                        || (in_array($tipoFormato, ['HORARIO', 'LISTA_GRUPO']) && ! $grupoSeleccionado)">
                    <span wire:loading.remove wire:target="generar">Generar PDF</span>
                    <span wire:loading wire:target="generar"><x-ui.loading label="Generando…" /></span>
                </x-ui.button>
            </div>

            @error('generar')
                <div class="mt-4"><x-ui.alert tone="danger">{{ $message }}</x-ui.alert></div>
            @enderror

            <div id="aviso-formato-generado" class="mt-4" hidden role="status" aria-live="polite">
                <x-ui.alert tone="success">
                    Formato generado correctamente. Queda registrado en la trazabilidad de
                    documentos generados.
                    <x-slot:action>
                        @if ($ultimoDocumentoId)
                            <x-ui.button size="sm" variant="secondary" icon="document-text"
                                href="{{ route('documentos.generados.descargar', $ultimoDocumentoId) }}">
                                Descargar PDF
                            </x-ui.button>
                        @endif
                    </x-slot:action>
                </x-ui.alert>
            </div>
        </x-ui.card>
    </section>

    {{-- ==================== MODAL BUSCAR ALUMNO ==================== --}}
    <x-ui.modal id="modal-buscar-alumno" size="lg" title="Buscar alumno"
        :open="$modalAlumnoAbierto" close="$set('modalAlumnoAbierto', false)">
        <div class="space-y-4">
            <x-ui.input type="search" wire:model.debounce.300ms="busquedaAlumno"
                placeholder="Nombre, apellidos, CURP o número de control…" aria-label="Buscar alumno" />

            @if ($alumnos->isEmpty())
                <x-ui.empty-state icon="users" title="Sin resultados"
                    message="Prueba con otro nombre, CURP o número de control." />
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Resultados de búsqueda de alumnos</caption>
                        <thead>
                            <tr>
                                <th scope="col">Nombre</th>
                                <th scope="col">CURP</th>
                                <th scope="col">N.º control</th>
                                <th scope="col">Correo</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($alumnos as $alumno)
                                <tr wire:key="res-alumno-{{ $alumno->id }}"
                                    class="cursor-pointer"
                                    wire:click="seleccionarAlumno({{ $alumno->id }})">
                                    <td class="font-medium text-brand-text">{{ $alumno->nombre_completo }}</td>
                                    <td class="text-brand-muted">{{ $alumno->curp }}</td>
                                    <td class="text-brand-muted">{{ $alumno->numero_control ?? '—' }}</td>
                                    <td class="text-brand-muted">{{ $alumno->correo ?? '—' }}</td>
                                    <td class="text-right">
                                        <x-ui.button size="sm" wire:click.stop="seleccionarAlumno({{ $alumno->id }})">
                                            Seleccionar
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-ui.modal>

    {{-- ==================== MODAL BUSCAR GRUPO ==================== --}}
    <x-ui.modal id="modal-buscar-grupo" size="lg" title="Buscar grupo"
        :open="$modalGrupoAbierto" close="$set('modalGrupoAbierto', false)">
        <div class="space-y-4">
            <x-ui.input type="search" wire:model.debounce.300ms="busquedaGrupo"
                placeholder="Clave, materia o docente…" aria-label="Buscar grupo" />

            @if ($grupos->isEmpty())
                <x-ui.empty-state icon="users" title="Sin resultados" message="Prueba con otra clave o materia." />
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <caption class="sr-only">Resultados de búsqueda de grupos</caption>
                        <thead>
                            <tr>
                                <th scope="col">Clave</th>
                                <th scope="col">Materia</th>
                                <th scope="col">Periodo</th>
                                <th scope="col">Docente</th>
                                <th scope="col">Aula</th>
                                <th scope="col">Cupo</th>
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grupos as $grupo)
                                <tr wire:key="res-grupo-{{ $grupo->id }}" class="cursor-pointer"
                                    wire:click="seleccionarGrupo({{ $grupo->id }})">
                                    <td class="font-medium text-brand-primary">{{ $grupo->clave }}</td>
                                    <td class="font-medium text-brand-text">{{ $grupo->materia->nombre }}</td>
                                    <td class="text-brand-muted">{{ $grupo->periodo->clave }}</td>
                                    <td class="text-brand-muted">{{ $grupo->docente?->nombreCompleto() ?? '—' }}</td>
                                    <td class="text-brand-muted">{{ $grupo->aula?->nombre ?? '—' }}</td>
                                    <td class="text-brand-muted">{{ $grupo->cupo }}</td>
                                    <td class="text-right">
                                        <x-ui.button size="sm" wire:click.stop="seleccionarGrupo({{ $grupo->id }})">
                                            Seleccionar
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-ui.modal>

    @script
        <script>
            $wire.on('pdf-generado', function () {
                const aviso = document.getElementById('aviso-formato-generado');
                if (aviso) {
                    aviso.hidden = false;
                    aviso.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        </script>
    @endscript
</div>
