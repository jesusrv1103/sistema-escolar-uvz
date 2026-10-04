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
        <div class="doc-type">Ficha del Alumno</div>
        @if($folio)
            <div class="doc-folio">Folio: {{ $folio }}</div>
        @endif
    </div>
</div>

<div class="section">
    <div class="section-title">Datos Personales</div>
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
            <span class="data-label">Fecha de nacimiento</span>
            <span class="data-value">{{ $alumno['fecha_nacimiento']?->format('d/m/Y') }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Sexo</span>
            <span class="data-value">{{ $alumno['sexo']?->label() }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Nacionalidad</span>
            <span class="data-value">{{ $alumno['nacionalidad'] }}</span>
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

@if($matricula)
<div class="section">
    <div class="section-title">Información Académica Actual</div>
    <div class="data-grid">
        @if($matricula['periodo'])
            <div class="data-item">
                <span class="data-label">Periodo actual</span>
                <span class="data-value">{{ $matricula['periodo']['clave'] }} - {{ $matricula['periodo']['nombre'] }}</span>
            </div>
        @endif
        <div class="data-item">
            <span class="data-label">Estatus de matrícula</span>
            <span class="data-value"><span class="badge badge-{{ strtolower(str_replace('_', '-', $matricula['estatus'])) }}">{{ $matricula['estatus'] }}</span></span>
        </div>
        @if($matricula['programa'])
            <div class="data-item">
                <span class="data-label">Programa</span>
                <span class="data-value">{{ $matricula['programa']['clave'] }} - {{ $matricula['programa']['nombre'] }}</span>
            </div>
        @endif
        @if($matricula['plan_version'])
            <div class="data-item">
                <span class="data-label">Plan de estudios</span>
                <span class="data-value">Versión {{ $matricula['plan_version']['version'] }}</span>
            </div>
        @endif
        <div class="data-item">
            <span class="data-label">Origen</span>
            <span class="data-value">{{ $matricula['origen'] }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Fecha matrícula</span>
            <span class="data-value">{{ $matricula['fecha_matricula'] }}</span>
        </div>
    </div>
</div>
@endif

@if($matricula && $matricula['inscripcionesCursando'] && $matricula['inscripcionesCursando']->count())
<div class="section">
    <div class="section-title">Materias Inscritas (Cursando)</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Clave</th>
                    <th>Materia</th>
                    <th>Créditos</th>
                    <th>Grupo</th>
                    <th>Docente</th>
                    <th>Horario</th>
                </tr>
            </thead>
            <tbody>
                @foreach($matricula['inscripcionesCursando'] as $inscripcion)
                    @if($inscripcion['grupo'])
                    <tr>
                        <td>{{ $inscripcion['materia']['clave'] }}</td>
                        <td>{{ $inscripcion['materia']['nombre'] }}</td>
                        <td>{{ $inscripcion['materia']['creditos'] }}</td>
                        <td>{{ $inscripcion['grupo']['clave'] }}</td>
                        <td>{{ $inscripcion['grupo']['docente']['nombreCompleto'] ?? 'Sin asignar' }}</td>
                        <td>
                            @foreach($inscripcion['grupo']['horarios'] as $h)
                                {{ $h['dia']->label() }} {{ $h['hora_inicio']->format('H:i') }}-{{ $h['hora_fin']->format('H:i') }}<br>
                            @endforeach
                        </td>
                    </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if($matricula && $matricula['inscripcionesAprobadas'] && $matricula['inscripcionesAprobadas']->count())
<div class="section">
    <div class="section-title">Materias Aprobadas</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Clave</th>
                    <th>Materia</th>
                    <th>Créditos</th>
                    <th>Calificación</th>
                    <th>Periodo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($matricula['inscripcionesAprobadas'] as $inscripcion)
                <tr>
                    <td>{{ $inscripcion['materia']['clave'] }}</td>
                    <td>{{ $inscripcion['materia']['nombre'] }}</td>
                    <td>{{ $inscripcion['materia']['creditos'] }}</td>
                    <td>{{ $inscripcion['calificacion_final'] }}</td>
                    <td>{{ $inscripcion['grupo']['periodo']['clave'] ?? 'N/A' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="section mt-20">
    <p>Ficha generada para fines académicos y administrativos.</p>
</div>
@endsection