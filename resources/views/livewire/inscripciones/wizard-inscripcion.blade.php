<div>
    <x-ui.page-header
        :title="$inscripcion === null ? 'Nueva inscripción' : 'Inscripción #' . $inscripcion->id"
        description="Asistente de 6 pasos · el avance queda guardado en la base de datos">
        <x-slot:meta>
            @if ($inscripcion !== null)
                <x-ui.badge tone="primary">{{ $inscripcion->estatus->label() }}</x-ui.badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-left" href="{{ route('inscripciones.lista') }}">
                Inscripciones
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('wizard'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('wizard') }}</x-ui.alert></div>
    @endif

    @error('paso')
        <div class="mt-4"><x-ui.alert tone="danger">{{ $message }}</x-ui.alert></div>
    @enderror

    @if (! $puedeEditar && $inscripcion !== null)
        <div class="mt-4">
            <x-ui.alert tone="neutral">
                Modo solo lectura: la inscripción no es borrador o el periodo está cerrado (RB-13).
            </x-ui.alert>
        </div>
    @endif

    {{-- Detalle operativo (Fase 14): estado general, convenio, pagos,
         documentos e historial de auditoría — solo lectura, sin rutas nuevas. --}}
    @if ($inscripcion !== null)
        <div class="mt-5">
            <livewire:inscripciones.detalle-inscripcion
                :inscripcion="$inscripcion"
                wire:key="detalle-{{ $inscripcion->id }}" />
        </div>
    @endif

    {{-- Indicador de pasos --}}
    @php
        $etiquetas = [
            1 => 'Selección y montos',
            2 => 'Datos personales',
            3 => 'Contacto (tutor)',
            4 => 'Contacto de emergencia',
            5 => 'Información médica',
            6 => 'Revisión final',
        ];
    @endphp
    <nav class="mt-6" aria-label="Pasos del asistente">
        <ol class="grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($etiquetas as $numero => $etiqueta)
                @php
                    $bloqueado = $numero > $pasoMaximo;
                    $actual = $numero === $paso;
                @endphp
                <li>
                    <button type="button" wire:click="irAPaso({{ $numero }})" @disabled($bloqueado)
                        @class([
                            'flex w-full items-center gap-2 rounded-lg border px-3 py-2 text-left text-xs font-medium transition',
                            'border-brand-primary bg-brand-primary text-white' => $actual,
                            'border-brand-line bg-white text-brand-primary hover:border-brand-muted/60' => ! $actual && ! $bloqueado,
                            'border-brand-line bg-brand-surface text-brand-muted opacity-60 cursor-not-allowed' => $bloqueado,
                        ])>
                        @if ($numero < $pasoMaximo && ! $actual)
                            <x-ui.icon name="check" :size="14" />
                        @endif
                        <span class="min-w-0 truncate">
                            <span class="font-semibold">{{ $numero }}.</span> {{ $etiqueta }}
                        </span>
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>

    {{-- Contenido por paso --}}
    <div class="mt-4">
        <x-ui.card>
            <x-slot:header>
                <p class="text-sm font-semibold text-brand-text">
                    @if ($paso === 1) Paso 1 — Selección y montos
                    @elseif ($paso === 2) Paso 2 — Datos personales
                    @elseif ($paso === 3) Paso 3 — Datos de contacto (tutor)
                    @elseif ($paso === 4) Paso 4 — Contacto de emergencia
                    @elseif ($paso === 5) Paso 5 — Información médica
                    @else Paso 6 — Revisión final
                    @endif
                </p>
            </x-slot:header>

            @if ($paso === 1)
                @if ($puedeEditar)
                    <p class="text-xs text-brand-muted">
                        Al continuar se crea el borrador de la inscripción. Los montos quedan guardados como
                        <em>snapshots</em> de la inscripción.
                    </p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <x-ui.field label="Buscar alumno" for="busqueda" :error="$errors->first('alumnoId')"
                                hint="No se duplican alumnos: la CURP sigue siendo única. Solo alumnos existentes y no eliminados.">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <x-ui.input id="busqueda" type="search"
                                        wire:model.live.debounce.300ms="busquedaAlumno"
                                        placeholder="Nombre, apellidos o CURP" aria-label="Buscar alumno" />
                                    <x-ui.select id="alumnoId" wire:model="alumnoId" aria-label="Alumno">
                                        <option value="">— Selecciona un alumno —</option>
                                        @foreach ($candidatos as $candidato)
                                            <option value="{{ $candidato->id }}">
                                                {{ $candidato->apellidos }}, {{ $candidato->nombre }} · CURP {{ $candidato->curp }}
                                            </option>
                                        @endforeach
                                    </x-ui.select>
                                </div>
                            </x-ui.field>
                            @can('alumno.create')
                                <p class="mt-1 text-xs text-brand-muted">
                                    <a href="{{ route('alumnos.nuevo') }}" class="font-medium text-brand-primary hover:underline">
                                        ¿No está? Regístralo en el expediente</a>.
                                </p>
                            @endcan
                        </div>

                        <x-ui.field label="Programa" for="programaId" required :error="$errors->first('programaId')"
                            hint="Solo programas activos y no eliminados.">
                            <x-ui.select id="programaId" wire:model="programaId">
                                <option value="">— Selecciona un programa —</option>
                                @foreach ($programas as $programa)
                                    <option value="{{ $programa->id }}">{{ $programa->clave }} — {{ $programa->nombre }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>

                        <x-ui.field label="Periodo" for="periodoId" required :error="$errors->first('periodoId')"
                            hint="Solo periodos activos y sin cerrar (RB-13).">
                            <x-ui.select id="periodoId" wire:model="periodoId">
                                <option value="">— Selecciona un periodo —</option>
                                @foreach ($periodos as $periodo)
                                    <option value="{{ $periodo->id }}">{{ $periodo->clave }} — {{ $periodo->nombre }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>

                        <x-ui.field label="Monto de inscripción (MXN)" for="monto_inscripcion"
                            :error="$errors->first('monto_inscripcion')">
                            <x-ui.input id="monto_inscripcion" type="number" step="0.01" min="0"
                                wire:model="monto_inscripcion" placeholder="1500.00" />
                        </x-ui.field>

                        <x-ui.field label="Monto de mensualidad (MXN)" for="monto_mensualidad"
                            :error="$errors->first('monto_mensualidad')">
                            <x-ui.input id="monto_mensualidad" type="number" step="0.01" min="0"
                                wire:model="monto_mensualidad" placeholder="1200.00" />
                        </x-ui.field>
                    </div>
                @else
                    <dl class="mt-2 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-brand-muted">Alumno</dt>
                            <dd class="font-medium text-brand-text">{{ $alumno ? $alumno->apellidos.', '.$alumno->nombre : '—' }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Periodo</dt>
                            <dd class="font-medium text-brand-text">{{ $inscripcion?->periodo?->nombre ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Programa</dt>
                            <dd class="font-medium text-brand-text">{{ $inscripcion?->programa?->nombre ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Montos</dt>
                            <dd class="font-medium text-brand-text">
                                ${{ number_format((float) ($inscripcion?->monto_inscripcion ?? 0), 2) }} /
                                ${{ number_format((float) ($inscripcion?->monto_mensualidad ?? 0), 2) }}
                            </dd></div>
                    </dl>
                @endif

            @elseif ($paso === 2)
                @if ($alumno !== null)
                    <dl class="mt-1 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-brand-muted">Nombre</dt>
                            <dd class="font-medium text-brand-text">{{ $alumno->apellidos }}, {{ $alumno->nombre }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">CURP</dt><dd class="font-mono">{{ $alumno->curp }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Fecha de nacimiento</dt>
                            <dd>{{ $alumno->fecha_nacimiento?->format('d/m/Y') }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Sexo</dt><dd>{{ $alumno->sexo?->label() }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Correo</dt><dd>{{ $alumno->correo ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Teléfono</dt><dd>{{ $alumno->telefono ?? '—' }}</dd></div>
                    </dl>
                    <div class="mt-4 flex flex-wrap items-center gap-3 text-xs">
                        @if ($pasoMaximo >= 3)
                            <x-ui.badge tone="success">Completo</x-ui.badge>
                        @else
                            <x-ui.badge tone="warning">Pendiente</x-ui.badge>
                        @endif
                        <a href="{{ route('alumnos.ver', $alumno) }}" class="font-medium text-brand-primary hover:underline">
                            @can('update', $alumno)
                                Editar en el expediente
                            @else
                                Ver en el expediente
                            @endcan
                        </a>
                    </div>
                @else
                    <x-ui.empty-state icon="user" title="Sin alumno seleccionado"
                        message="Completa el paso 1 para continuar." />
                @endif

            @elseif ($paso === 3)
                @if ($contactoTutor !== null)
                    <dl class="mt-1 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-brand-muted">Nombre</dt>
                            <dd class="font-medium text-brand-text">{{ $contactoTutor->nombre }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Parentesco</dt><dd>{{ $contactoTutor->parentesco ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Teléfono</dt><dd>{{ $contactoTutor->telefono ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Correo</dt><dd>{{ $contactoTutor->correo ?? '—' }}</dd></div>
                    </dl>
                    <p class="mt-3"><x-ui.badge tone="success">Contacto de tutor registrado</x-ui.badge></p>
                @else
                    <x-ui.empty-state icon="user" title="Falta el contacto de tutor"
                        message="Debe existir exactamente uno para poder avanzar." />
                @endif
                @if ($alumno !== null)
                    <div class="mt-3">
                        <x-ui.button size="sm" variant="secondary" icon="pencil" href="{{ route('alumnos.contactos', $alumno) }}">
                            {{ $contactoTutor !== null ? 'Editar contactos' : 'Registrar contacto de tutor' }}
                        </x-ui.button>
                    </div>
                @endif

            @elseif ($paso === 4)
                @if ($contactoEmergencia !== null)
                    <dl class="mt-1 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-brand-muted">Nombre</dt>
                            <dd class="font-medium text-brand-text">{{ $contactoEmergencia->nombre }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Parentesco</dt><dd>{{ $contactoEmergencia->parentesco ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Teléfono</dt><dd>{{ $contactoEmergencia->telefono ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Correo</dt><dd>{{ $contactoEmergencia->correo ?? '—' }}</dd></div>
                    </dl>
                    <p class="mt-3"><x-ui.badge tone="success">Contacto de emergencia registrado</x-ui.badge></p>
                @else
                    <x-ui.empty-state icon="user" title="Falta el contacto de emergencia"
                        message="Debe existir exactamente uno para poder avanzar." />
                @endif
                @if ($alumno !== null)
                    <div class="mt-3">
                        <x-ui.button size="sm" variant="secondary" icon="pencil" href="{{ route('alumnos.contactos', $alumno) }}">
                            {{ $contactoEmergencia !== null ? 'Editar contactos' : 'Registrar contacto de emergencia' }}
                        </x-ui.button>
                    </div>
                @endif

            @elseif ($paso === 5)
                <p class="mt-1 text-sm text-brand-text">
                    @if ($infoMedicaCompleta)
                        <x-ui.badge tone="success">Información médica registrada</x-ui.badge>
                    @else
                        <x-ui.badge tone="warning">Pendiente: falta la información médica básica del alumno</x-ui.badge>
                    @endif
                </p>
                <p class="mt-2 text-xs text-brand-muted">
                    El contenido médico no se muestra en este asistente ni en los listados (RB-18); solo se verifica que
                    exista el registro.
                </p>
                @can('informacionMedica.edit')
                    @if ($alumno !== null)
                        <div class="mt-3">
                            <x-ui.button size="sm" variant="secondary" icon="pencil"
                                href="{{ route('alumnos.info-medica', $alumno) }}">
                                {{ $infoMedicaCompleta ? 'Editar información médica' : 'Registrar información médica' }}
                            </x-ui.button>
                        </div>
                    @endif
                @endcan

            @else
                @if ($inscripcion !== null)
                    <dl class="mt-1 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        <div><dt class="text-xs text-brand-muted">Alumno</dt>
                            <dd class="font-medium text-brand-text">
                                {{ $alumno ? $alumno->apellidos.', '.$alumno->nombre : '—' }}
                                <span class="block font-mono text-xs text-brand-muted">{{ $alumno?->curp }}</span>
                            </dd></div>
                        <div><dt class="text-xs text-brand-muted">Estatus actual</dt>
                            <dd><x-ui.badge tone="primary">{{ $inscripcion->estatus->label() }}</x-ui.badge></dd></div>
                        <div><dt class="text-xs text-brand-muted">Programa</dt>
                            <dd>{{ $inscripcion->programa?->nombre ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Periodo</dt>
                            <dd>{{ $inscripcion->periodo?->nombre ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Monto de inscripción</dt>
                            <dd class="font-medium text-brand-text">${{ number_format((float) $inscripcion->monto_inscripcion, 2) }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Monto de mensualidad</dt>
                            <dd class="font-medium text-brand-text">${{ number_format((float) $inscripcion->monto_mensualidad, 2) }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Contacto de tutor</dt>
                            <dd>{{ $contactoTutor?->nombre ?? 'Falta' }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Contacto de emergencia</dt>
                            <dd>{{ $contactoEmergencia?->nombre ?? 'Falta' }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Información médica</dt>
                            <dd>{{ $infoMedicaCompleta ? 'Registrada' : 'Falta' }}</dd></div>
                        <div><dt class="text-xs text-brand-muted">Número de control</dt>
                            <dd>{{ $alumno?->numero_control ?? 'Se asigna en la revisión final' }}</dd></div>
                    </dl>

                    @if ($puedeEditar)
                        <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-brand-line pt-4">
                            <x-ui.button icon="check" wire:click="completar"
                                wire:loading.attr="disabled" wire:target="completar">
                                <span wire:loading.remove wire:target="completar">
                                    Completar datos (→ {{ \App\Enums\InscripcionEstatus::DATA_COMPLETE->label() }})
                                </span>
                                <span wire:loading wire:target="completar"><x-ui.loading label="Guardando…" /></span>
                            </x-ui.button>
                            <span class="text-xs text-brand-muted">
                                Registra la marca de tiempo <code>datos_completados_at</code> y auditoría;
                                los pagos llegan en la siguiente fase.
                            </span>
                        </div>
                    @elseif ($inscripcion->estatus !== \App\Enums\InscripcionEstatus::DRAFT)
                        <p class="mt-4">
                            <x-ui.badge tone="success">Datos completos ({{ $inscripcion->estatus->label() }})</x-ui.badge>
                        </p>
                    @endif
                @else
                    <x-ui.empty-state icon="clipboard" title="Completa el paso 1"
                        message="Al crear el borrador aparecerá aquí el resumen de la inscripción." />
                @endif
            @endif
        </x-ui.card>
    </div>

    @if ($inscripcion !== null)
        {{-- Accesos rápidos (Fases 7–13) --}}
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.card>
                <x-slot:header><p class="text-sm font-semibold text-brand-text">Documentos</p></x-slot:header>
                <p class="text-xs text-brand-muted">
                    Recepción y validación de los documentos obligatorios del expediente.
                </p>
                <div class="mt-3">
                    <x-ui.button size="sm" variant="secondary" icon="document-text"
                        href="{{ route('inscripciones.documentos', $inscripcion) }}">
                        Abrir panel
                    </x-ui.button>
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-slot:header><p class="text-sm font-semibold text-brand-text">Observaciones</p></x-slot:header>
                <p class="text-xs text-brand-muted">
                    Notas y faltantes del expediente: se registran y se resuelven con responsable y fecha.
                </p>
                <div class="mt-3">
                    <x-ui.button size="sm" variant="secondary" icon="clipboard"
                        href="{{ route('inscripciones.observaciones', $inscripcion) }}">
                        Abrir observaciones
                    </x-ui.button>
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-slot:header><p class="text-sm font-semibold text-brand-text">Revisión final</p></x-slot:header>
                <p class="text-xs text-brand-muted">
                    Verifica los requisitos de cierre y asigna el número de control del alumno.
                </p>
                <div class="mt-3">
                    <x-ui.button size="sm" icon="check-circle"
                        href="{{ route('inscripciones.revision-final', $inscripcion) }}">
                        Abrir revisión
                    </x-ui.button>
                </div>
            </x-ui.card>
        </div>

        {{-- Convenio, pagos y cancelación (Fases 7, 8 y 13) --}}
        <div class="mt-4">
            <livewire:inscripciones.modal-aplicar-convenio
                :inscripcion="$inscripcion"
                wire:key="convenio-{{ $inscripcion->id }}" />

            <livewire:pagos.modal-registrar-pago
                :inscripcion="$inscripcion"
                wire:key="pago-registrar-{{ $inscripcion->id }}" />
            <livewire:pagos.modal-validar-pago
                :inscripcion="$inscripcion"
                wire:key="pago-validar-{{ $inscripcion->id }}" />

            <livewire:inscripciones.modal-cancelar-inscripcion
                :inscripcion="$inscripcion"
                wire:key="cancelar-{{ $inscripcion->id }}" />
        </div>

        {{-- Cargos (Fase 7) — la lógica vive en ConvenioService --}}
        <div class="mt-4">
            <x-ui.card>
                <x-slot:header>
                    <p class="text-sm font-semibold text-brand-text">Cargos</p>
                </x-slot:header>

                @error('cargos')
                    <p class="ui-error">{{ $message }}</p>
                @enderror

                @if ($conceptosPago->isEmpty())
                    @if ($inscripcion->estatus === \App\Enums\InscripcionEstatus::DATA_COMPLETE)
                        @can('generarCargos', $inscripcion)
                            <p class="text-xs text-brand-muted">
                                Aún no hay cargos. Al generarlos se crearán 1 concepto de inscripción y 4 mensualidades
                                (cuatrimestre UVZ) y la inscripción pasará a
                                <strong>{{ \App\Enums\InscripcionEstatus::PENDING_PAYMENT->label() }}</strong>.
                            </p>
                            <div class="mt-3">
                                <x-ui.button icon="banknotes" wire:click="generarCargos"
                                    wire:loading.attr="disabled" wire:target="generarCargos">
                                    <span wire:loading.remove wire:target="generarCargos">
                                        Generar cargos (→ {{ \App\Enums\InscripcionEstatus::PENDING_PAYMENT->label() }})
                                    </span>
                                    <span wire:loading wire:target="generarCargos"><x-ui.loading label="Generando…" /></span>
                                </x-ui.button>
                            </div>
                        @endcan
                    @else
                        <p class="text-xs text-brand-muted">
                            Sin cargos todavía: se generan cuando los datos están completos
                            ({{ \App\Enums\InscripcionEstatus::DATA_COMPLETE->label() }}).
                        </p>
                    @endif
                @else
                    <ul class="divide-y divide-brand-line text-sm">
                        @foreach ($conceptosPago as $concepto)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                                <div class="min-w-0">
                                    <span class="font-medium text-brand-text">{{ $concepto->concepto }}</span>
                                    <span class="text-xs text-brand-muted">
                                        · {{ $concepto->tipo->label() }}{{ $concepto->numero !== null ? ' · #'.$concepto->numero : '' }}
                                        · vence {{ $concepto->fecha_vencimiento->format('d/m/Y') }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="text-brand-muted">
                                        ${{ number_format((float) $concepto->monto, 2) }}
                                        @if ((float) $concepto->descuento > 0)
                                            − descuento ${{ number_format((float) $concepto->descuento, 2) }}
                                        @endif
                                    </span>
                                    <span class="font-semibold text-brand-text">
                                        ${{ number_format((float) $concepto->monto_total, 2) }}
                                    </span>
                                    <x-ui.badge :tone="match ($concepto->estatus->value) {
                                        'PAGADO' => 'success',
                                        'RECHAZADO' => 'danger',
                                        'PENDIENTE' => 'warning',
                                        default => 'neutral',
                                    }">
                                        {{ $concepto->estatus->label() }}
                                    </x-ui.badge>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    @endif

    {{-- Navegación --}}
    <div class="mt-5 flex items-center justify-between border-t border-brand-line pt-4">
        <x-ui.button variant="secondary" icon="chevron-left" wire:click="atras" :disabled="$paso === 1">
            Atrás
        </x-ui.button>

        @if ($paso < 6)
            <x-ui.button icon="chevron-right" wire:click="avanzar"
                wire:loading.attr="disabled" wire:target="avanzar">
                <span wire:loading.remove wire:target="avanzar">Continuar</span>
                <span wire:loading wire:target="avanzar"><x-ui.loading label="Validando…" /></span>
            </x-ui.button>
        @endif
    </div>
</div>
