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
        <div class="doc-type">Boleta de Calificaciones</div>
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
    <div class="section-title">Calificaciones del Periodo</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Materia</th>
                    <th>Evaluaciones</th>
                    <th>Calificación Final</th>
                    <th>Estatus</th>
                </tr>
            </thead>
            <tbody>
                @foreach($historial as $h)
                    <tr class="{{ $h['es_aprobada'] ? 'table-success' : ($h['es_reprobada'] ? 'table-danger' : '') }}">
                        <td>{{ $h['materia']['clave'] }} - {{ $h['materia']['nombre'] }}</td>
                        <td>
                            @foreach($h['calificaciones'] ?? [] as $cal)
                                {{ $cal['evaluacion']['nombre'] }}: {{ $cal['valor'] }}<br>
                            @endforeach
                        </td>
                        <td>{{ $h['calificacion_final'] ?? '—' }}</td>
                        <td><span class="badge {{ $h['es_aprobada'] ? 'bg-success' : ($h['es_reprobada'] ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $h['estatus'] }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-center">No hay calificaciones registradas para este periodo.</p>
@endif
@endsection