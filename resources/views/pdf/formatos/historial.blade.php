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
        <div class="doc-type">Historial Académico</div>
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
        <div class="data-item">
            <span class="data-label">Teléfono</span>
            <span class="data-value">{{ $alumno['telefono'] }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Domicilio</span>
            <span class="data-value">{{ $alumno['domicilio'] }}</span>
        </div>
    </div>
</div>

@if($historial && $historial->count())
<div class="section">
    <div class="section-title">Historial Académico Completo</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Periodo</th>
                    <th>Materia</th>
                    <th>Créditos</th>
                    <th>Calificación</th>
                    <th>Estatus</th>
                </tr>
            </thead>
            <tbody>
                @foreach($historial as $h)
                    <tr class="{{ $h['es_aprobada'] ? 'table-success' : ($h['es_reprobada'] ? 'table-danger' : '') }}">
                        <td>{{ $h['periodo']['clave'] ?? '—' }}</td>
                        <td>{{ $h['materia']['clave'] }} - {{ $h['materia']['nombre'] }}</td>
                        <td>{{ $h['materia']['creditos'] }}</td>
                        <td>{{ $h['calificacion_final'] ?? '—' }}</td>
                        <td><span class="badge {{ $h['es_aprobada'] ? 'bg-success' : ($h['es_reprobada'] ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $h['estatus'] }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
    <p class="text-center">No hay historial académico registrado.</p>
@endif

@if($resumen)
<div class="section mt-20">
    <div class="section-title">Resumen Académico</div>
    <div class="data-grid">
        <div class="data-item">
            <span class="data-label">Promedio</span>
            <span class="data-value">{{ $resumen['promedio'] ?? '—' }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Créditos aprobados</span>
            <span class="data-value">{{ $resumen['creditos_aprobados'] }} / {{ $resumen['creditos_totales'] }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Avance</span>
            <span class="data-value">{{ $resumen['porcentaje_avance'] }}%</span>
        </div>
    </div>
</div>
@endif
@endsection