{{-- Detalle operativo de la inscripción (Fase 14) — SOLO LECTURA.
     Estado general, convenio, pagos, documentos e historial de auditoría
     (esta última solo con la capacidad auditoria.view = ADMIN, §8.2).
     Sin información médica (RB-18), sin rutas privadas y sin acciones:
     los flujos viven en los componentes existentes. --}}
<div class="space-y-4">
    {{-- 1) Estado general (§14) --}}
    <div class="rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-brand-text">Estado general</h2>
                <p class="mt-1 text-xs text-brand-muted">Lectura del expediente de inscripción.</p>
            </div>
            <span @class([
                'rounded-full border px-3 py-1 text-xs font-semibold',
                'border-green-300 bg-green-50 text-green-800' => $inscripcion->estatus->value === 'ENROLLED',
                'border-red-300 bg-red-50 text-red-800' => $inscripcion->estatus->value === 'CANCELLED',
                'border-brand-line bg-brand-surface text-brand-primary' => ! in_array($inscripcion->estatus->value, ['ENROLLED', 'CANCELLED'], true),
            ])>
                {{ $inscripcion->estatus->value }}
            </span>
        </div>

        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs text-brand-muted">Alumno</dt>
                <dd class="text-sm font-medium text-brand-text">
                    {{ $inscripcion->alumno?->apellidos }}, {{ $inscripcion->alumno?->nombre }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-brand-muted">Número de control</dt>
                <dd class="text-sm font-medium text-brand-primary">
                    {{ $inscripcion->alumno?->numero_control ?? 'Se asigna al finalizar' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-brand-muted">Programa</dt>
                <dd class="text-sm font-medium text-brand-text">{{ $inscripcion->programa?->nombre ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-brand-muted">Periodo</dt>
                <dd class="text-sm font-medium text-brand-text">{{ $inscripcion->periodo?->nombre ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-brand-muted">Creada</dt>
                <dd class="text-sm font-medium text-brand-text">{{ $inscripcion->created_at?->format('d/m/Y H:i') }}</dd>
            </div>
        </dl>

        {{-- Hitos relevantes ya almacenados (no se inventan estados derivados) --}}
        <ul class="mt-4 flex flex-wrap gap-2 text-xs">
            @if ($inscripcion->datos_completados_at !== null)
                <li class="rounded-full border border-brand-line bg-brand-surface px-3 py-1 text-brand-muted">
                    Datos completos · {{ $inscripcion->datos_completados_at->format('d/m/Y H:i') }}
                </li>
            @endif
            @if ($inscripcion->pagos_validados_at !== null)
                <li class="rounded-full border border-brand-line bg-brand-surface px-3 py-1 text-brand-muted">
                    Pagos validados · {{ $inscripcion->pagos_validados_at->format('d/m/Y H:i') }}
                </li>
            @endif
            @if ($inscripcion->documentos_validados_at !== null)
                <li class="rounded-full border border-brand-line bg-brand-surface px-3 py-1 text-brand-muted">
                    Documentos validados · {{ $inscripcion->documentos_validados_at->format('d/m/Y H:i') }}
                </li>
            @endif
            @if ($inscripcion->finalizada_at !== null)
                <li class="rounded-full border border-green-300 bg-green-50 px-3 py-1 text-green-800">
                    Finalizada · {{ $inscripcion->finalizada_at->format('d/m/Y H:i') }}
                    @if ($inscripcion->validadaPor !== null) · por {{ $inscripcion->validadaPor->name }} @endif
                </li>
            @endif
        </ul>

        {{-- Cancelación (§20): fecha, responsable y motivo — sin reapertura --}}
        @if ($inscripcion->cancelada_at !== null)
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800">
                <p class="font-semibold">Inscripción cancelada · {{ $inscripcion->cancelada_at->format('d/m/Y H:i') }}</p>
                <p class="mt-1">
                    @if ($inscripcion->canceladaPor !== null)
                        Por {{ $inscripcion->canceladaPor->name }} ·
                    @endif
                    Motivo: {{ $inscripcion->cancelacion_motivo }}
                </p>
                <p class="mt-1 text-red-700">Los pagos, documentos y observaciones se conservan; no hay reapertura en el MVP.</p>
            </div>
        @endif
    </div>

    {{-- 2) Convenio (§15): solo lectura; las acciones viven en ModalAplicarConvenio --}}
    <div class="rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-brand-text">Convenio</h2>

        @if ($inscripcion->convenio_id === null)
            <p class="mt-2 text-sm text-brand-muted">No aplica (sin convenio ni promoción aplicada).</p>
        @else
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                @php
                    $estatusConvenio = $inscripcion->convenio_estatus?->value;
                @endphp
                <span @class([
                    'rounded-full border px-3 py-1 font-semibold',
                    'border-amber-300 bg-amber-50 text-amber-800' => $estatusConvenio === 'PENDIENTE_APROBACION',
                    'border-green-300 bg-green-50 text-green-800' => $estatusConvenio === 'APROBADO',
                    'border-red-300 bg-red-50 text-red-800' => $estatusConvenio === 'RECHAZADO',
                    'border-brand-line bg-brand-surface text-brand-muted' => $estatusConvenio === null,
                ])>{{ $estatusConvenio ?? 'SIN ESTATUS' }}</span>
                <span class="font-medium text-brand-text">{{ $inscripcion->convenio?->nombre }}</span>
                <span class="text-brand-muted">
                    ({{ $inscripcion->convenio?->tipo?->value }}
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
            </div>

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
    </div>

    {{-- 3) Pagos (§16): resumen por concepto; comprobante solo vía rutas con policy --}}
    <div class="rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-brand-text">Pagos</h2>

        @php
            $pagosVisibles = $conceptos->flatMap->pagos;
        @endphp

        @if ($pagosVisibles->isEmpty())
            <p class="mt-2 text-sm text-brand-muted">Sin pagos registrados todavía.</p>
        @else
            <div class="mt-3 space-y-4">
                @foreach ($conceptos as $concepto)
                    @if ($concepto->pagos->isEmpty())
                        @continue
                    @endif
                    <div class="rounded-xl border border-brand-line bg-brand-surface p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-medium text-brand-text">
                                {{ $concepto->concepto }}
                                <span class="text-xs font-normal text-brand-muted">
                                    · {{ $concepto->tipo->value }}{{ $concepto->numero !== null ? ' · #'.$concepto->numero : '' }}
                                </span>
                            </p>
                            <span class="rounded-full border border-brand-line bg-white px-2 py-0.5 text-[11px] font-semibold text-brand-muted">
                                Concepto {{ $concepto->estatus->value }}
                            </span>
                        </div>

                        <ul class="mt-2 divide-y divide-brand-line">
                            @foreach ($concepto->pagos as $pago)
                                <li class="py-2 text-xs">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="font-medium text-brand-text">
                                            ${{ number_format((float) $pago->monto, 2) }}
                                            <span @class([
                                                'ml-2 rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                                                'border-green-300 bg-green-50 text-green-800' => $pago->estatus->value === 'VALIDADO',
                                                'border-red-300 bg-red-50 text-red-800' => $pago->estatus->value === 'RECHAZADO',
                                                'border-amber-300 bg-amber-50 text-amber-800' => $pago->estatus->value === 'PENDIENTE',
                                            ])>{{ $pago->estatus->value }}</span>
                                        </p>
                                        <p class="text-brand-muted">
                                            Folio: <span class="font-mono font-semibold text-brand-text">{{ $pago->folio ?? '—' }}</span>
                                        </p>
                                    </div>
                                    <p class="mt-1 text-brand-muted">
                                        {{ $pago->metodo->value }}
                                        @if (filled($pago->referencia)) · ref. {{ $pago->referencia }} @endif
                                        · pago {{ $pago->fecha_pago?->format('d/m/Y') }}
                                        · capturó {{ $pago->registradoPor?->name ?? '—' }}
                                        @if ($pago->validado_at !== null)
                                            · validó {{ $pago->validadoPor?->name ?? '—' }} el {{ $pago->validado_at->format('d/m/Y H:i') }}
                                        @endif
                                    </p>
                                    @if (filled($pago->motivo_rechazo))
                                        <p class="mt-1 text-red-700">Rechazado: {{ \Illuminate\Support\Str::limit($pago->motivo_rechazo, 200) }}</p>
                                    @endif
                                    <div class="mt-1 flex flex-wrap gap-3">
                                        @if ($pago->comprobante_ruta !== null)
                                            {{-- Rutas con policy: jamás se muestra la ruta interna --}}
                                            <a href="{{ route('pagos.comprobante', $pago) }}"
                                                class="font-semibold text-brand-primary hover:underline" target="_blank" rel="noopener">
                                                Ver comprobante
                                            </a>
                                        @endif
                                        @if ($pago->estatus->value === 'VALIDADO')
                                            <a href="{{ route('pagos.comprobante-pdf', $pago) }}"
                                                class="font-semibold text-brand-primary hover:underline" target="_blank" rel="noopener">
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
    </div>

    {{-- 4) Documentos (§17): solo lectura; las acciones viven en PanelDocumentos --}}
    <div class="rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-brand-text">Documentos</h2>
                <p class="mt-1 text-xs text-brand-muted">Tipos cargados y su estatus de validación.</p>
            </div>
            <a href="{{ route('inscripciones.documentos', $inscripcion) }}"
                class="rounded-md border border-brand-line px-3 py-1.5 text-xs font-medium text-brand-primary hover:bg-brand-surface">
                Abrir panel de documentos
            </a>
        </div>

        @if ($documentos->isEmpty())
            <p class="mt-3 text-sm text-brand-muted">Sin documentos cargados todavía.</p>
        @else
            <ul class="mt-3 divide-y divide-brand-line text-xs">
                @foreach ($documentos as $documento)
                    <li class="py-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-medium text-brand-text">
                                {{ $documento->tipoDocumento?->nombre ?? 'Tipo no disponible' }}
                                <span class="ml-2 rounded-full border border-brand-line bg-white px-2 py-0.5 text-[10px] font-semibold text-brand-muted">
                                    {{ $documento->tipoDocumento?->obligatorio ? 'Obligatorio' : 'Opcional' }}
                                </span>
                            </p>
                            <span @class([
                                'rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                                'border-green-300 bg-green-50 text-green-800' => $documento->estatus->value === 'ACEPTADO',
                                'border-red-300 bg-red-50 text-red-800' => $documento->estatus->value === 'RECHAZADO',
                                'border-amber-300 bg-amber-50 text-amber-800' => $documento->estatus->value === 'PENDIENTE',
                            ])>{{ $documento->estatus->value }}</span>
                        </div>
                        <p class="mt-1 text-brand-muted">
                            Subido {{ $documento->created_at?->format('d/m/Y H:i') }}
                            @if ($documento->validado_at !== null)
                                · validó {{ $documento->validadoPor?->name ?? '—' }} el {{ $documento->validado_at->format('d/m/Y H:i') }}
                            @endif
                        </p>
                        @if (filled($documento->motivo_rechazo))
                            <p class="mt-1 text-red-700">Rechazado: {{ \Illuminate\Support\Str::limit($documento->motivo_rechazo, 200) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- 5) Historial de auditoría (§18): solo capacidad auditoria.view (ADMIN) --}}
    @if ($puedeVerHistorial)
        <div class="rounded-2xl border border-brand-line bg-white p-6 shadow-sm">
            <div>
                <h2 class="text-sm font-semibold text-brand-text">Historial de auditoría</h2>
                <p class="mt-1 text-xs text-brand-muted">
                    Más reciente primero · solo lectura (append-only). No se muestra el payload técnico.
                </p>
            </div>

            @if ($historial->isEmpty())
                <p class="mt-3 text-sm text-brand-muted">Sin registros de auditoría.</p>
            @else
                <ul class="mt-3 divide-y divide-brand-line text-xs">
                    @foreach ($historial as $registro)
                        <li class="py-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-medium text-brand-text">{{ $registro->accion }}</p>
                                <p class="font-mono text-brand-muted">{{ $registro->created_at?->format('d/m/Y H:i:s') }}</p>
                            </div>
                            <p class="mt-1 text-brand-muted">
                                Usuario: {{ $registro->user?->name ?? 'Sistema' }}
                                @if ($registro->estado_anterior !== null || $registro->estado_nuevo !== null)
                                    · Estado:
                                        {{ $registro->estado_anterior ?? '—' }}
                                        <span aria-hidden="true">→</span>
                                        {{ $registro->estado_nuevo ?? '—' }}
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
