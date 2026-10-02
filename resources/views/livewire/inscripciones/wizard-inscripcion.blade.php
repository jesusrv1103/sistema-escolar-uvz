<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-brand-text">
                {{ $inscripcion === null ? 'Nueva inscripción' : 'Inscripción #'.$inscripcion->id }}
            </h1>
            <p class="text-sm text-brand-muted">Asistente de 6 pasos · el avance queda guardado en la base de datos</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            @if ($inscripcion !== null)
                <span class="rounded-full border border-brand-line bg-brand-surface px-3 py-1 text-xs font-semibold text-brand-primary">
                    {{ $inscripcion->estatus->value }}
                </span>
            @endif
            <a href="{{ route('inscripciones.lista') }}" class="font-medium text-brand-primary hover:text-brand-secondary">Inscripciones</a>
            <a href="{{ route('dashboard') }}" class="text-brand-muted hover:text-brand-secondary">Dashboard</a>
        </div>
    </div>

    @if (session('wizard'))
        <div class="mb-4 rounded-md border border-brand-line bg-brand-surface px-4 py-3 text-sm text-brand-text">
            {{ session('wizard') }}
        </div>
    @endif

    @error('paso')
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $message }}
        </div>
    @enderror

    @if (! $puedeEditar && $inscripcion !== null)
        <div class="mb-4 rounded-md border border-brand-line bg-brand-surface px-4 py-3 text-xs text-brand-muted">
            Modo solo lectura: la inscripción no es borrador o el periodo está cerrado (RB-13).
        </div>
    @endif

    {{-- Detalle operativo (Fase 14): estado general, convenio, pagos,
         documentos e historial de auditoría — solo lectura, sin rutas nuevas. --}}
    @if ($inscripcion !== null)
        <livewire:inscripciones.detalle-inscripcion
            :inscripcion="$inscripcion"
            wire:key="detalle-{{ $inscripcion->id }}" />
    @endif

    {{-- Indicador de pasos --}}
    <ol class="mb-6 grid gap-2 sm:grid-cols-6">
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
        @foreach ($etiquetas as $numero => $etiqueta)
            @php
                $bloqueado = $numero > $pasoMaximo;
                $actual = $numero === $paso;
            @endphp
            <li>
                <button type="button" wire:click="irAPaso({{ $numero }})" @disabled($bloqueado)
                    @class([
                        'w-full rounded-md border px-2 py-2 text-xs font-medium text-left transition',
                        'border-brand-primary bg-brand-primary text-white' => $actual,
                        'border-brand-line bg-white text-brand-primary hover:bg-brand-surface' => ! $actual && ! $bloqueado,
                        'border-brand-line bg-white text-brand-muted opacity-60 cursor-not-allowed' => $bloqueado,
                    ])>
                    @if ($numero < $pasoMaximo && ! $actual)
                        <span aria-hidden="true">✓</span>
                    @endif
                    {{ $numero }}. {{ $etiqueta }}
                </button>
            </li>
        @endforeach
    </ol>

    {{-- Contenido por paso --}}
    <div class="rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
        @if ($paso === 1)
            @if ($puedeEditar)
                <h2 class="text-sm font-semibold text-brand-text">Paso 1 — Selección y montos</h2>
                <p class="mt-1 text-xs text-brand-muted">
                    Al continuar se crea el borrador de la inscripción. Los montos quedan guardados como
                    <em>snapshots</em> de la inscripción.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="busqueda" class="block text-sm font-medium text-brand-text">Buscar alumno</label>
                        <input id="busqueda" type="search" wire:model.live.debounce.300ms="busquedaAlumno"
                            placeholder="Nombre, apellidos o CURP"
                            class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                        <select id="alumnoId" wire:model="alumnoId"
                            class="mt-2 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                            <option value="">— Selecciona un alumno —</option>
                            @foreach ($candidatos as $candidato)
                                <option value="{{ $candidato->id }}">
                                    {{ $candidato->apellidos }}, {{ $candidato->nombre }} · CURP {{ $candidato->curp }}
                                </option>
                            @endforeach
                        </select>
                        @error('alumnoId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-brand-muted">
                            No se duplican alumnos: la CURP sigue siendo única. Solo alumnos existentes y no eliminados.
                            @can('alumno.create')
                                <a href="{{ route('alumnos.nuevo') }}" class="font-medium text-brand-primary hover:underline">
                                    ¿No está? Regístralo en el expediente</a>.
                            @endcan
                        </p>
                    </div>

                    <div>
                        <label for="programaId" class="block text-sm font-medium text-brand-text">Programa</label>
                        <select id="programaId" wire:model="programaId"
                            class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                            <option value="">— Selecciona un programa —</option>
                            @foreach ($programas as $programa)
                                <option value="{{ $programa->id }}">{{ $programa->clave }} — {{ $programa->nombre }}</option>
                            @endforeach
                        </select>
                        @error('programaId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-brand-muted">Solo programas activos y no eliminados.</p>
                    </div>

                    <div>
                        <label for="periodoId" class="block text-sm font-medium text-brand-text">Periodo</label>
                        <select id="periodoId" wire:model="periodoId"
                            class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                            <option value="">— Selecciona un periodo —</option>
                            @foreach ($periodos as $periodo)
                                <option value="{{ $periodo->id }}">{{ $periodo->clave }} — {{ $periodo->nombre }}</option>
                            @endforeach
                        </select>
                        @error('periodoId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-brand-muted">Solo periodos activos y sin cerrar (RB-13).</p>
                    </div>

                    <div>
                        <label for="monto_inscripcion" class="block text-sm font-medium text-brand-text">Monto de inscripción (MXN)</label>
                        <input id="monto_inscripcion" type="number" step="0.01" min="0" wire:model="monto_inscripcion"
                            placeholder="1500.00"
                            class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                        @error('monto_inscripcion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="monto_mensualidad" class="block text-sm font-medium text-brand-text">Monto de mensualidad (MXN)</label>
                        <input id="monto_mensualidad" type="number" step="0.01" min="0" wire:model="monto_mensualidad"
                            placeholder="1200.00"
                            class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                        @error('monto_mensualidad') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @else
                <h2 class="text-sm font-semibold text-brand-text">Paso 1 — Selección y montos</h2>
                <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-brand-muted">Alumno</dt>
                        <dd>{{ $alumno ? $alumno->apellidos.', '.$alumno->nombre : '—' }}</dd></div>
                    <div><dt class="text-brand-muted">Periodo</dt>
                        <dd>{{ $inscripcion?->periodo?->nombre ?? '—' }}</dd></div>
                    <div><dt class="text-brand-muted">Programa</dt>
                        <dd>{{ $inscripcion?->programa?->nombre ?? '—' }}</dd></div>
                    <div><dt class="text-brand-muted">Montos</dt>
                        <dd>${{ number_format((float) ($inscripcion?->monto_inscripcion ?? 0), 2) }} / ${{ number_format((float) ($inscripcion?->monto_mensualidad ?? 0), 2) }}</dd></div>
                </dl>
            @endif

        @elseif ($paso === 2)
            <h2 class="text-sm font-semibold text-brand-text">Paso 2 — Datos personales</h2>
            @if ($alumno !== null)
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-brand-muted">Nombre</dt><dd>{{ $alumno->apellidos }}, {{ $alumno->nombre }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">CURP</dt><dd class="font-mono">{{ $alumno->curp }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">Fecha de nacimiento</dt><dd>{{ $alumno->fecha_nacimiento?->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">Sexo</dt><dd>{{ $alumno->sexo?->value }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">Correo</dt><dd>{{ $alumno->correo ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">Teléfono</dt><dd>{{ $alumno->telefono ?? '—' }}</dd></div>
                </dl>
                <div class="mt-4 flex items-center gap-3 text-xs">
                    @if ($pasoMaximo >= 3)
                        <span class="font-semibold text-brand-primary">✓ Completo</span>
                    @else
                        <span class="font-semibold text-brand-muted">Pendiente</span>
                    @endif
                    @can('update', $alumno)
                        <a href="{{ route('alumnos.ver', $alumno) }}" class="font-medium text-brand-primary hover:underline">Editar en el expediente</a>
                    @else
                        <a href="{{ route('alumnos.ver', $alumno) }}" class="font-medium text-brand-primary hover:underline">Ver en el expediente</a>
                    @endcan
                </div>
            @else
                <p class="mt-2 text-sm text-brand-muted">No hay alumno seleccionado. Completa el paso 1.</p>
            @endif

        @elseif ($paso === 3)
            <h2 class="text-sm font-semibold text-brand-text">Paso 3 — Datos de contacto (tutor)</h2>
            @if ($contactoTutor !== null)
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-brand-muted">Nombre</dt><dd>{{ $contactoTutor->nombre }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">Parentesco</dt><dd>{{ $contactoTutor->parentesco ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">Teléfono</dt><dd>{{ $contactoTutor->telefono ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">Correo</dt><dd>{{ $contactoTutor->correo ?? '—' }}</dd></div>
                </dl>
                <p class="mt-3 text-xs font-semibold text-brand-primary">✓ Contacto de tutor registrado</p>
            @else
                <p class="mt-2 text-sm text-brand-muted">Falta el contacto de tutor (debe existir exactamente uno).</p>
            @endif
            @if ($alumno !== null)
                <a href="{{ route('alumnos.contactos', $alumno) }}" class="mt-3 inline-block text-xs font-medium text-brand-primary hover:underline">
                    {{ $contactoTutor !== null ? 'Editar contactos' : 'Registrar contacto de tutor' }}</a>
            @endif

        @elseif ($paso === 4)
            <h2 class="text-sm font-semibold text-brand-text">Paso 4 — Contacto de emergencia</h2>
            @if ($contactoEmergencia !== null)
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-brand-muted">Nombre</dt><dd>{{ $contactoEmergencia->nombre }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">Parentesco</dt><dd>{{ $contactoEmergencia->parentesco ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">Teléfono</dt><dd>{{ $contactoEmergencia->telefono ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">Correo</dt><dd>{{ $contactoEmergencia->correo ?? '—' }}</dd></div>
                </dl>
                <p class="mt-3 text-xs font-semibold text-brand-primary">✓ Contacto de emergencia registrado</p>
            @else
                <p class="mt-2 text-sm text-brand-muted">Falta el contacto de emergencia (debe existir exactamente uno).</p>
            @endif
            @if ($alumno !== null)
                <a href="{{ route('alumnos.contactos', $alumno) }}" class="mt-3 inline-block text-xs font-medium text-brand-primary hover:underline">
                    {{ $contactoEmergencia !== null ? 'Editar contactos' : 'Registrar contacto de emergencia' }}</a>
            @endif

        @elseif ($paso === 5)
            <h2 class="text-sm font-semibold text-brand-text">Paso 5 — Información médica</h2>
            <p class="mt-2 text-sm text-brand-text">
                @if ($infoMedicaCompleta)
                    <span class="font-semibold text-brand-primary">✓ Información médica registrada</span>
                @else
                    <span class="font-semibold text-brand-muted">Pendiente: falta la información médica básica del alumno.</span>
                @endif
            </p>
            <p class="mt-1 text-xs text-brand-muted">
                El contenido médico no se muestra en este asistente ni en los listados (RB-18); solo se verifica que
                exista el registro.
            </p>
            @can('informacionMedica.edit')
                @if ($alumno !== null)
                    <a href="{{ route('alumnos.info-medica', $alumno) }}" class="mt-3 inline-block text-xs font-medium text-brand-primary hover:underline">
                        {{ $infoMedicaCompleta ? 'Editar información médica' : 'Registrar información médica' }}</a>
                @endif
            @endcan

        @else
            <h2 class="text-sm font-semibold text-brand-text">Paso 6 — Revisión final</h2>
            @if ($inscripcion !== null)
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-brand-muted">Alumno</dt>
                        <dd>{{ $alumno ? $alumno->apellidos.', '.$alumno->nombre : '—' }}
                            <span class="font-mono text-xs text-brand-muted">{{ $alumno?->curp }}</span></dd></div>
                    <div><dt class="text-xs text-brand-muted">Estatus actual</dt>
                        <dd class="font-semibold">{{ $inscripcion->estatus->value }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">Programa</dt>
                        <dd>{{ $inscripcion->programa?->nombre ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">Periodo</dt>
                        <dd>{{ $inscripcion->periodo?->nombre ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">Monto de inscripción</dt>
                        <dd>${{ number_format((float) $inscripcion->monto_inscripcion, 2) }}</dd></div>
                    <div><dt class="text-xs text-brand-muted">Monto de mensualidad</dt>
                        <dd>${{ number_format((float) $inscripcion->monto_mensualidad, 2) }}</dd></div>
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
                    <div class="mt-5 flex flex-wrap gap-3">
                        <button type="button" wire:click="completar" wire:loading.attr="disabled"
                            class="rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-secondary">
                            <span wire:loading.remove wire:target="completar">Completar datos (→ DATA_COMPLETE)</span>
                            <span wire:loading wire:target="completar">Guardando…</span>
                        </button>
                        <span class="self-center text-xs text-brand-muted">
                            Registra <code>datos_completados_at</code> y auditoría; los pagos llegan en la siguiente fase.
                        </span>
                    </div>
                @elseif ($inscripcion->estatus !== \App\Enums\InscripcionEstatus::DRAFT)
                    <p class="mt-4 text-xs font-semibold text-brand-primary">
                        Datos completos ({{ $inscripcion->estatus->value }}).
                    </p>
                @endif
            @else
                <p class="mt-2 text-sm text-brand-muted">Completa el paso 1 para crear el borrador.</p>
            @endif
        @endif
    </div>

    {{-- Convenio y cargos (Fase 7) — la lógica vive en ConvenioService --}}
    @if ($inscripcion !== null)
        <livewire:inscripciones.modal-aplicar-convenio
            :inscripcion="$inscripcion"
            wire:key="convenio-{{ $inscripcion->id }}" />

        {{-- Pagos (Fase 8): captura y revisión four-eyes sobre los cargos --}}
        <livewire:pagos.modal-registrar-pago
            :inscripcion="$inscripcion"
            wire:key="pago-registrar-{{ $inscripcion->id }}" />
        <livewire:pagos.modal-validar-pago
            :inscripcion="$inscripcion"
            wire:key="pago-validar-{{ $inscripcion->id }}" />

        {{-- Documentos (Fase 10): recepción, reemplazo y validación del expediente --}}
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
            <div>
                <h2 class="text-sm font-semibold text-brand-text">Documentos</h2>
                <p class="mt-1 text-xs text-brand-muted">Recepción y validación de los documentos obligatorios del expediente.</p>
            </div>
            <a href="{{ route('inscripciones.documentos', $inscripcion) }}"
                class="rounded-md border border-brand-line px-4 py-2 text-sm font-medium text-brand-primary hover:bg-brand-surface">
                Abrir panel de documentos
            </a>
        </div>

        {{-- Observaciones (Fase 11): registrar faltantes y resolverlos (append-only) --}}
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
            <div>
                <h2 class="text-sm font-semibold text-brand-text">Observaciones</h2>
                <p class="mt-1 text-xs text-brand-muted">Notas y faltantes del expediente: se registran y se resuelven con responsable y fecha.</p>
            </div>
            <a href="{{ route('inscripciones.observaciones', $inscripcion) }}"
                class="rounded-md border border-brand-line px-4 py-2 text-sm font-medium text-brand-primary hover:bg-brand-surface">
                Abrir observaciones
            </a>
        </div>

        {{-- Revisión final (Fase 12): guardas RB-8 y cierre con número de control --}}
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
            <div>
                <h2 class="text-sm font-semibold text-brand-text">Revisión final</h2>
                <p class="mt-1 text-xs text-brand-muted">Verifica los requisitos de cierre y asigna el número de control del alumno.</p>
            </div>
            <a href="{{ route('inscripciones.revision-final', $inscripcion) }}"
                class="rounded-md border border-brand-line px-4 py-2 text-sm font-medium text-brand-primary hover:bg-brand-surface">
                Abrir revisión final
            </a>
        </div>

        {{-- Cancelación (Fase 13): solo ADMIN, estados no terminales, motivo obligatorio --}}
        <livewire:inscripciones.modal-cancelar-inscripcion
            :inscripcion="$inscripcion"
            wire:key="cancelar-{{ $inscripcion->id }}" />

        <div class="mt-4 rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-brand-text">Cargos</h2>

            @error('cargos')
                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror

            @if ($conceptosPago->isEmpty())
                @if ($inscripcion->estatus === \App\Enums\InscripcionEstatus::DATA_COMPLETE)
                    @can('generarCargos', $inscripcion)
                        <p class="mt-1 text-xs text-brand-muted">
                            Aún no hay cargos. Al generarlos se crearán 1 concepto de inscripción y 4 mensualidades
                            (cuatrimestre UVZ) y la inscripción pasará a <strong>PENDING_PAYMENT</strong>.
                        </p>
                        <button type="button" wire:click="generarCargos" wire:loading.attr="disabled"
                            class="mt-3 rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-secondary">
                            <span wire:loading.remove wire:target="generarCargos">Generar cargos (→ PENDING_PAYMENT)</span>
                            <span wire:loading wire:target="generarCargos">Generando…</span>
                        </button>
                    @endcan
                @else
                    <p class="mt-2 text-xs text-brand-muted">Sin cargos todavía: se generan cuando los datos están completos (DATA_COMPLETE).</p>
                @endif
            @else
                <ul class="mt-3 divide-y divide-brand-line text-sm">
                    @foreach ($conceptosPago as $concepto)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                            <div>
                                <span class="font-medium text-brand-text">{{ $concepto->concepto }}</span>
                                <span class="text-xs text-brand-muted">
                                    · {{ $concepto->tipo->value }}{{ $concepto->numero !== null ? ' · #'.$concepto->numero : '' }}
                                    · vence {{ $concepto->fecha_vencimiento->format('d/m/Y') }}
                                </span>
                            </div>
                            <div class="text-xs">
                                <span class="text-brand-muted">
                                    ${{ number_format((float) $concepto->monto, 2) }}
                                    @if ((float) $concepto->descuento > 0)
                                        − descuento ${{ number_format((float) $concepto->descuento, 2) }}
                                    @endif
                                </span>
                                <span class="ml-2 font-semibold text-brand-text">${{ number_format((float) $concepto->monto_total, 2) }}</span>
                                <span class="ml-2 rounded-full border border-brand-line bg-brand-surface px-2 py-0.5 text-[11px] font-semibold">
                                    {{ $concepto->estatus->value }}
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{-- Navegación --}}
    <div class="mt-4 flex items-center justify-between">
        <button type="button" wire:click="atras" @disabled($paso === 1)
            class="rounded-md border border-brand-line px-4 py-2 text-sm font-medium text-brand-text hover:bg-brand-surface disabled:opacity-50">
            ← Atrás
        </button>

        @if ($paso < 6)
            <button type="button" wire:click="avanzar" wire:loading.attr="disabled"
                class="rounded-md bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-secondary disabled:opacity-50">
                <span wire:loading.remove wire:target="avanzar">Continuar →</span>
                <span wire:loading wire:target="avanzar">Validando…</span>
            </button>
        @endif
    </div>
</div>
