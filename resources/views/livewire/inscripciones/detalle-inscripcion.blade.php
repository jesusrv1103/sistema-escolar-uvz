{{-- Detalle operativo de la inscripción (Fase 14) — SOLO LECTURA.
     Estado general, convenio, pagos, documentos e historial de auditoría
     (esta última solo con la capacidad auditoria.view = ADMIN, §8.2).
     Sin información médica (RB-18), sin rutas privadas y sin acciones:
     los flujos viven en los componentes existentes. --}}
<div>
    <x-ui.page-header :title="'Inscripción #' . $inscripcion->id"
        :description="'Lectura del expediente de inscripción · ' . ($inscripcion->alumno?->apellidos ?? '')
            . ', ' . ($inscripcion->alumno?->nombre ?? '')">
        <x-slot:meta>
            <x-ui.badge :tone="match ($inscripcion->estatus->value) {
                'ENROLLED' => 'success',
                'CANCELLED' => 'danger',
                default => 'primary',
            }">
                {{ $inscripcion->estatus->label() }}
            </x-ui.badge>
        </x-slot:meta>
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-left" href="{{ route('inscripciones.lista') }}">
                Inscripciones
            </x-ui.button>
            @if ($inscripcion->estatus->value !== 'CANCELLED')
                <x-ui.button icon="check-circle"
                    href="{{ route('inscripciones.revision-final', $inscripcion) }}">
                    Revisión final
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mt-5 grid gap-4">
        {{-- 1) Estado general (§14) --}}
        <x-ui.card>
            <x-slot:header>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-brand-text">Estado general</p>
                    <p class="mt-0.5 text-xs text-brand-muted">Datos de la inscripción y sus hitos.</p>
                </div>
            </x-slot:header>

            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs text-brand-muted">Alumno</dt>
                    <dd class="mt-0.5 text-sm font-medium text-brand-text">
                        {{ $inscripcion->alumno?->apellidos }}, {{ $inscripcion->alumno?->nombre }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-brand-muted">Número de control</dt>
                    <dd class="mt-0.5 text-sm font-medium text-brand-primary">
                        {{ $inscripcion->alumno?->numero_control ?? 'Se asigna al finalizar' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-brand-muted">Programa</dt>
                    <dd class="mt-0.5 text-sm font-medium text-brand-text">{{ $inscripcion->programa?->nombre ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-brand-muted">Periodo</dt>
                    <dd class="mt-0.5 text-sm font-medium text-brand-text">{{ $inscripcion->periodo?->nombre ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-brand-muted">Creada</dt>
                    <dd class="mt-0.5 text-sm font-medium text-brand-text">
                        {{ $inscripcion->created_at?->format('d/m/Y H:i') }}
                    </dd>
                </div>
            </dl>

            {{-- Hitos relevantes ya almacenados (no se inventan estados derivados) --}}
            <ul class="mt-4 flex flex-wrap gap-2 text-xs">
                @if ($inscripcion->datos_completados_at !== null)
                    <li><x-ui.badge tone="neutral">
                        Datos completos · {{ $inscripcion->datos_completados_at->format('d/m/Y H:i') }}
                    </x-ui.badge></li>
                @endif
                @if ($inscripcion->pagos_validados_at !== null)
                    <li><x-ui.badge tone="neutral">
                        Pagos validados · {{ $inscripcion->pagos_validados_at->format('d/m/Y H:i') }}
                    </x-ui.badge></li>
                @endif
                @if ($inscripcion->documentos_validados_at !== null)
                    <li><x-ui.badge tone="neutral">
                        Documentos validados · {{ $inscripcion->documentos_validados_at->format('d/m/Y H:i') }}
                    </x-ui.badge></li>
                @endif
                @if ($inscripcion->finalizada_at !== null)
                    <li><x-ui.badge tone="success">
                        Finalizada · {{ $inscripcion->finalizada_at->format('d/m/Y H:i') }}
                        @if ($inscripcion->validadaPor !== null) · por {{ $inscripcion->validadaPor->name }} @endif
                    </x-ui.badge></li>
                @endif
            </ul>

            {{-- Cancelación (§20): fecha, responsable y motivo — sin reapertura --}}
            @if ($inscripcion->cancelada_at !== null)
                <div class="mt-4">
                    <x-ui.alert tone="danger">
                        <p class="font-semibold">Inscripción cancelada · {{ $inscripcion->cancelada_at->format('d/m/Y H:i') }}</p>
                        <p class="mt-1">
                            @if ($inscripcion->canceladaPor !== null)
                                Por {{ $inscripcion->canceladaPor->name }} ·
                            @endif
                            Motivo: {{ $inscripcion->cancelacion_motivo }}
                        </p>
                        <p class="mt-1 opacity-80">
                            Los pagos, documentos y observaciones se conservan; no hay reapertura en el MVP.
                        </p>
                    </x-ui.alert>
                </div>
            @endif
        </x-ui.card>

        {{-- 2) Convenio (§15): solo lectura; las acciones viven en ModalAplicarConvenio --}}
        <x-ui.card>
            <x-slot:header>
                <p class="text-sm font-semibold text-brand-text">Convenio</p>
            </x-slot:header>

            @if ($inscripcion->convenio_id === null)
                <p class="text-sm text-brand-muted">No aplica (sin convenio ni promoción aplicada).</p>
            @else
                @php
                    $estatusConvenio = $inscripcion->convenio_estatus?->value;
                @endphp
                <p class="flex flex-wrap items-center gap-2 text-xs">
                    <x-ui.badge :tone="match ($estatusConvenio) {
                        'APROBADO' => 'success',
                        'RECHAZADO' => 'danger',
                        'PENDIENTE_APROBACION' => 'warning',
                        default => 'neutral',
                    }">
                        {{ $inscripcion->convenio_estatus?->label() ?? 'Sin estatus' }}
                    </x-ui.badge>
                    <span class="font-medium text-brand-text">{{ $inscripcion->convenio?->nombre }}</span>
                    <span class="text-brand-muted">
                        ({{ $inscripcion->convenio?->tipo?->label() }}
                        {{ $inscripcion->convenio?->tipo?->value === 'PORCENTAJE'
                            ? $inscripcion->convenio?->valor.'%'
                            : '$'.number_format((float) ($inscripcion->convenio?->valor ?? 0), 2) }})
                    </span>
                    <span class="text-brand-muted">
                        Descuento aplicado: ${{ number_format((float) $inscripcion->monto_descuento, 2) }}
                    </span>
                    @if ($estatusConvenio === 'RECHAZADO' && filled($inscripcion->convenio_motivo))
                        <span class="text-brand-muted">· Motivo: {{ \Illuminate\Support\Str::limit($inscripcion->convenio_motivo, 120) }}</span>
                    @endif
                </p>

                <p class="mt-2 text-xs text-brand-muted">
                    @if ($inscripcion->convenio_aplicado_at !== null)
                        Aplicado por {{ $inscripcion->convenioAplicadoPor?->name ?? '—' }}
                        el {{ $inscripcion->convenio_aplicado_at->format('d/m/Y H:i') }}.
                    @endif
                    @if ($inscripcion->convenio_aprobado_at !== null)
                        Aprobado por {{ $inscripcion->convenioAprobadoPor?->name ?? '—' }}
                        el {{ $inscripcion->convenio_aprobado_at->format('d/m/Y H:i') }}.
                    @endif
                </p>
            @endif
        </x-ui.card>

        {{-- 3) Pagos (§16): resumen por concepto; comprobante solo vía rutas con policy --}}
        <x-ui.card>
            <x-slot:header>
                <p class="text-sm font-semibold text-brand-text">Pagos</p>
            </x-slot:header>

            @php
                $pagosVisibles = $conceptos->flatMap->pagos;
            @endphp

            @if ($pagosVisibles->isEmpty())
                <x-ui.empty-state icon="receipt" title="Sin pagos registrados todavía"
                    message="Los pagos aparecen aquí en cuanto se registre el primero." />
            @else
                <div class="grid gap-4">
                    @foreach ($conceptos as $concepto)
                        @if ($concepto->pagos->isEmpty())
                            @continue
                        @endif
                        <div class="rounded-lg border border-brand-line bg-brand-surface p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-medium text-brand-text">
                                    {{ $concepto->concepto }}
                                    <span class="text-xs font-normal text-brand-muted">
                                        · {{ $concepto->tipo->label() }}{{ $concepto->numero !== null ? ' · #'.$concepto->numero : '' }}
                                    </span>
                                </p>
                                <x-ui.badge tone="neutral">
                                    Concepto {{ $concepto->estatus->label() }}
                                </x-ui.badge>
                            </div>

                            <ul class="mt-2 divide-y divide-brand-line">
                                @foreach ($concepto->pagos as $pago)
                                    <li class="py-2 text-xs">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="font-medium text-brand-text">
                                                ${{ number_format((float) $pago->monto, 2) }}
                                                <span class="ml-2 inline-block align-middle">
                                                    <x-ui.badge :tone="match ($pago->estatus->value) {
                                                        'VALIDADO' => 'success',
                                                        'RECHAZADO' => 'danger',
                                                        'PENDIENTE' => 'warning',
                                                        default => 'neutral',
                                                    }">
                                                        {{ $pago->estatus->label() }}
                                                    </x-ui.badge>
                                                </span>
                                            </p>
                                            <p class="text-brand-muted">
                                                Folio: <span class="font-mono font-semibold text-brand-text">{{ $pago->folio ?? '—' }}</span>
                                            </p>
                                        </div>
                                        <p class="mt-1 text-brand-muted">
                                            {{ $pago->metodo->label() }}
                                            @if (filled($pago->referencia)) · ref. {{ $pago->referencia }} @endif
                                            · pago {{ $pago->fecha_pago?->format('d/m/Y') }}
                                            · capturó {{ $pago->registradoPor?->name ?? '—' }}
                                            @if ($pago->validado_at !== null)
                                                · validó {{ $pago->validadoPor?->name ?? '—' }} el {{ $pago->validado_at->format('d/m/Y H:i') }}
                                            @endif
                                        </p>
                                        @if (filled($pago->motivo_rechazo))
                                            <p class="mt-1 ui-error">
                                                Rechazado: {{ \Illuminate\Support\Str::limit($pago->motivo_rechazo, 200) }}
                                            </p>
                                        @endif
                                        <div class="mt-1 flex flex-wrap gap-3">
                                            @if ($pago->comprobante_ruta !== null)
                                                {{-- Rutas con policy: jamás se muestra la ruta interna --}}
                                                <a href="{{ route('pagos.comprobante', $pago) }}"
                                                    class="font-semibold text-brand-primary hover:underline"
                                                    target="_blank" rel="noopener">
                                                    Ver comprobante
                                                </a>
                                            @endif
                                            @if ($pago->estatus->value === 'VALIDADO')
                                                <a href="{{ route('pagos.comprobante-pdf', $pago) }}"
                                                    class="font-semibold text-brand-primary hover:underline"
                                                    target="_blank" rel="noopener">
                                                    Comprobante PDF
                                                </a>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        {{-- 4) Documentos (§17): solo lectura; las acciones viven en PanelDocumentos --}}
        <x-ui.card>
            <x-slot:header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-brand-text">Documentos</p>
                        <p class="mt-0.5 text-xs text-brand-muted">Tipos cargados y su estatus de validación.</p>
                    </div>
                    <x-ui.button size="sm" variant="secondary" icon="document-text"
                        href="{{ route('inscripciones.documentos', $inscripcion) }}">
                        Abrir panel de documentos
                    </x-ui.button>
                </div>
            </x-slot:header>

            @if ($documentos->isEmpty())
                <x-ui.empty-state icon="document-text" title="Sin documentos cargados todavía"
                    message="Sube los archivos desde el panel de documentos." />
            @else
                <ul class="divide-y divide-brand-line text-xs">
                    @foreach ($documentos as $documento)
                        <li class="py-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-medium text-brand-text">
                                    {{ $documento->tipoDocumento?->nombre ?? 'Tipo no disponible' }}
                                    <span class="ml-2 inline-block align-middle">
                                        <x-ui.badge tone="neutral">
                                            {{ $documento->tipoDocumento?->obligatorio ? 'Obligatorio' : 'Opcional' }}
                                        </x-ui.badge>
                                    </span>
                                </p>
                                <x-ui.badge :tone="match ($documento->estatus->value) {
                                    'ACEPTADO' => 'success',
                                    'RECHAZADO' => 'danger',
                                    'PENDIENTE' => 'warning',
                                    default => 'neutral',
                                }">
                                    {{ $documento->estatus->label() }}
                                </x-ui.badge>
                            </div>
                            <p class="mt-1 text-brand-muted">
                                Subido {{ $documento->created_at?->format('d/m/Y H:i') }}
                                @if ($documento->validado_at !== null)
                                    · validó {{ $documento->validadoPor?->name ?? '—' }} el {{ $documento->validado_at->format('d/m/Y H:i') }}
                                @endif
                            </p>
                            @if (filled($documento->motivo_rechazo))
                                <p class="mt-1 ui-error">
                                    Rechazado: {{ \Illuminate\Support\Str::limit($documento->motivo_rechazo, 200) }}
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        {{-- 5) Historial de auditoría (§18): solo capacidad auditoria.view (ADMIN) --}}
        @if ($puedeVerHistorial)
            <x-ui.card>
                <x-slot:header>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-brand-text">Historial de auditoría</p>
                        <p class="mt-0.5 text-xs text-brand-muted">
                            Más reciente primero · solo lectura (append-only). No se muestra el payload técnico.
                        </p>
                    </div>
                </x-slot:header>

                @if ($historial->isEmpty())
                    <x-ui.empty-state icon="clock" title="Sin registros de auditoría"
                        message="Cada cambio relevante quedará registrado aquí." />
                @else
                    <ul class="divide-y divide-brand-line text-xs">
                        @foreach ($historial as $registro)
                            <li class="py-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-medium text-brand-text">{{ $registro->accionLabel() }}</p>
                                    <p class="font-mono text-brand-muted">{{ $registro->created_at?->format('d/m/Y H:i:s') }}</p>
                                </div>
                                <p class="mt-1 text-brand-muted">
                                    Usuario: {{ $registro->user?->name ?? 'Sistema' }}
                                    @if ($registro->estado_anterior !== null || $registro->estado_nuevo !== null)
                                        {{-- Presentación: etiquetas en español; en BD siguen los valores técnicos --}}
                                        · Estado:
                                            {{ \App\Enums\InscripcionEstatus::tryFrom($registro->estado_anterior)?->label() ?? ($registro->estado_anterior ?? '—') }}
                                            <span aria-hidden="true">→</span>
                                            {{ \App\Enums\InscripcionEstatus::tryFrom($registro->estado_nuevo)?->label() ?? ($registro->estado_nuevo ?? '—') }}
                                    @endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endif
    </div>
</div>
