@extends('pdf.layouts.pdf')

@section('content')
<div class="header">
    <div class="logo-container">
        @if(config('branding.logo'))
            <img src="{{ public_path(config('branding.logo')) }}" alt="{{ config('branding.name') }}" class="logo">
        @endif
        <div class="brand-info">
            <div class="brand-name">{{ config('branding.name') }}</div>
            <div class="brand-short">{{ config('branding.short_name') }}</div>
            <div class="brand-slogan">{{ config('branding.slogan') }}</div>
        </div>
    </div>
    <div class="doc-title">
        <div class="doc-type">Constancia de Terminación</div>
        @if($folio)
            <div class="doc-folio">Folio: {{ $folio }}</div>
        @endif
    </div>
</div>

<div class="section">
    <div class="section-title">Datos del Alumno</div>
    <div class="data-grid">
        <div class="data-item">
            <span class="data-label">Nombre completo</span>
            <span class="data-value">{{ $alumno['nombre_completo'] }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">CURP</span>
            <span class="data-value">{{ $alumno['curp'] }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Número de control</span>
            <span class="data-value">{{ $alumno['numero_control'] ?? 'Por asignar' }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Correo</span>
            <span class="data-value">{{ $alumno['correo'] }}</span>
        </div>
    </div>
</div>

@if($egreso)
<div class="section">
    <div class="section-title">Egreso</div>
    <div class="data-grid">
        <div class="data-item">
            <span class="data-label">Fecha de registro del egreso</span>
            <span class="data-value">{{ $egreso->registrado_at?->format('d/m/Y') ?? '—' }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Plan de estudios</span>
            <span class="data-value">
                @if($egreso->planVersion)
                    {{ $egreso->planVersion->plan?->clave ?? '' }} versión {{ $egreso->planVersion->version }}
                @else
                    —
                @endif
            </span>
        </div>
        <div class="data-item">
            <span class="data-label">Créditos aprobados</span>
            <span class="data-value">{{ $egreso->creditos_acumulados }} / {{ $egreso->creditos_requeridos }}</span>
        </div>
        @if($resumen)
            <div class="data-item">
                <span class="data-label">Avance académico</span>
                <span class="data-value">{{ $resumen['porcentaje_avance'] }}%</span>
            </div>
        @endif
    </div>
</div>
@endif

<div class="section">
    <div class="section-title">Servicio Social y Prácticas Profesionales</div>
    <div class="data-grid">
        <div class="data-item">
            <span class="data-label">Servicio Social</span>
            <span class="data-value">
                @if($servicio_social)
                    {{ $servicio_social->estatus->label() }}
                    ({{ $servicio_social->dependencia }})
                @else
                    Sin registro
                @endif
            </span>
        </div>
        <div class="data-item">
            <span class="data-label">Actividad de Servicio Social</span>
            <span class="data-value">{{ $servicio_social?->actividad ?? '—' }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Prácticas Profesionales</span>
            <span class="data-value">
                @if($practicas)
                    {{ $practicas->estatus->label() }}
                    ({{ $practicas->empresa }})
                @else
                    Sin registro
                @endif
            </span>
        </div>
        <div class="data-item">
            <span class="data-label">Periodo de prácticas</span>
            <span class="data-value">
                {{ $practicas?->fecha_inicio?->format('d/m/Y') ?? '—' }}
                -
                {{ $practicas?->fecha_fin?->format('d/m/Y') ?? '—' }}
            </span>
        </div>
    </div>
</div>

<div class="section mt-20">
    <p>La presente constancia se expide a solicitud del interesado para los fines legales que correspondan.</p>
</div>

<div class="section mt-20">
    <div style="display: flex; justify-content: space-between;">
        <div style="text-align: center; width: 45%;">
            <br><br>
            <hr style="border-top: 1px solid #72132e; width: 80%; margin: 0 auto;">
            <small>Vo. Bo. Control Escolar</small>
        </div>
        <div style="text-align: center; width: 45%;">
            <br><br>
            <hr style="border-top: 1px solid #72132e; width: 80%; margin: 0 auto;">
            <small>Director/a de Control Escolar</small>
        </div>
    </div>
</div>
@endsection
