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
        <div class="doc-type">Comprobante de Inscripción</div>
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

@if(!empty($matricula))
<div class="section">
    <div class="section-title">Información de la Inscripción</div>
    <div class="data-grid">
        @if($matricula['origen'])
            <div class="data-item">
                <span class="data-label">Origen</span>
                <span class="data-value">{{ $matricula['origen'] }}</span>
            </div>
        @endif
        <div class="data-item">
            <span class="data-label">Fecha de inscripción</span>
            <span class="data-value">{{ $matricula['fecha_matricula'] }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Estatus</span>
            <span class="data-value"><span class="badge badge-{{ strtolower(str_replace('_', '-', $matricula['estatus'])) }}">{{ $matricula['estatus'] }}</span></span>
        </div>
        <div class="data-item">
            <span class="data-label">Fecha de matrícula</span>
            <span class="data-value">{{ $matricula['fecha_matricula'] }}</span>
        </div>
    </div>
</div>
@endif

@if(!empty($matricula) && $matricula['periodo'])
<div class="section">
    <div class="section-title">Periodo Académico</div>
    <div class="data-grid">
        <div class="data-item">
            <span class="data-label">Periodo</span>
            <span class="data-value">{{ $matricula['periodo']['clave'] }} - {{ $matricula['periodo']['nombre'] }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Fecha inicio</span>
            <span class="data-value">{{ $matricula['periodo']['fecha_inicio'] }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Fecha fin</span>
            <span class="data-value">{{ $matricula['periodo']['fecha_fin'] }}</span>
        </div>
    </div>
</div>
@endif

@if(!empty($matricula) && $matricula['programa'])
<div class="section">
    <div class="section-title">Programa Académico</div>
    <div class="data-grid">
        <div class="data-item">
            <span class="data-label">Programa</span>
            <span class="data-value">{{ $matricula['programa']['clave'] }} - {{ $matricula['programa']['nombre'] }}</span>
        </div>
        @if($matricula['plan_version'])
            <div class="data-item">
                <span class="data-label">Plan de estudios</span>
                <span class="data-value">Versión {{ $matricula['plan_version']['version'] }} ({{ $matricula['plan_version']['fecha_vigencia'] }})</span>
            </div>
        @endif
        <div class="data-item">
            <span class="data-label">Origen de la matrícula</span>
            <span class="data-value">{{ $matricula['origen'] }}</span>
        </div>
    </div>
</div>
@endif

@if(!empty($matricula) && $matricula['inscripcionesCursando'] && $matricula['inscripcionesCursando']->count())
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

<div class="section mt-20">
    <p>Este comprobante acredita la inscripción del alumno en el periodo académico indicado.</p>
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