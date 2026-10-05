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
        <div class="doc-type">Carga/Tira de Materias</div>
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

@if($tira && $tira->count())
<div class="section">
    <div class="section-title">Tira de Materias</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Clave</th>
                    <th>Materia</th>
                    <th>Créditos</th>
                    <th>Estatus</th>
                    <th>Calificación</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tira as $t)
                    <tr class="{{ $t['es_aprobada'] ? 'table-success' : ($t['es_reprobada'] ? 'table-danger' : ($t['estatus'] === 'CURSANDO' ? 'table-warning' : '')) }}">
                        <td>{{ $t['materia']['clave'] }}</td>
                        <td>{{ $t['materia']['nombre'] }}</td>
                        <td>{{ $t['materia']['creditos'] }}</td>
                        <td><span class="badge {{ $t['es_aprobada'] ? 'bg-success' : ($t['es_reprobada'] ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $t['estatus'] }}</span></td>
                        <td>{{ $t['calificacion_final'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-center">No hay materias registradas.</p>
@endif

@if($resumen)
<div class="section mt-20">
    <div class="section-title">Resumen</div>
    <div class="data-grid">
        <div class="data-item">
            <span class="data-label">Total materias</span>
            <span class="data-value">{{ $resumen['total_materias'] ?? 0 }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Créditos aprobados</span>
            <span class="data-value">{{ $resumen['creditos_aprobados'] ?? 0 }} / {{ $resumen['creditos_totales'] ?? 0 }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Avance</span>
            <span class="data-value">{{ $resumen['porcentaje_avance'] ?? 0 }}%</span>
        </div>
    </div>
</div>
@endif
@endsection