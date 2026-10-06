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
        <div class="doc-type">Acta de Calificaciones</div>
        @if($folio)
            <div class="doc-folio">Folio: {{ $folio }}</div>
        @endif
    </div>
</div>

<div class="section">
    <div class="section-title">Datos del Acta</div>
    <div class="data-grid">
        <div class="data-item">
            <span class="data-label">Grupo</span>
            <span class="data-value">{{ $acta->grupo->clave ?? '—' }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Materia</span>
            <span class="data-value">{{ $acta->grupo->materia->clave ?? '—' }} - {{ $acta->grupo->materia->nombre ?? '—' }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Periodo</span>
            <span class="data-value">{{ $acta->periodo->clave ?? '—' }} - {{ $acta->periodo->nombre ?? '—' }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Docente</span>
            <span class="data-value">{{ $acta->grupo->docente->nombreCompleto() ?? 'Sin asignar' }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Folio</span>
            <span class="data-value">{{ $acta->folio ?? '—' }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Estatus</span>
            <span class="data-value"><span class="badge bg-{{ $acta->esCerrada() ? 'success' : 'warning text-dark' }}">{{ $acta->estatus }}</span></span>
        </div>
        <div class="data-item">
            <span class="data-label">Cerrada por</span>
            <span class="data-value">{{ $acta->cerradaPor->name ?? '—' }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Fecha de cierre</span>
            <span class="data-value">{{ $acta->cerrada_at?->format('d/m/Y H:i') ?? '—' }}</span>
        </div>
    </div>
</div>

@if($acta->grupo && $acta->grupo->inscripcionesMaterias && $acta->grupo->inscripcionesMaterias->count())
<div class="section">
    <div class="section-title">Calificaciones del Grupo</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Número de Control</th>
                    <th>Calificación Final</th>
                    <th>Estatus</th>
                </tr>
            </thead>
            <tbody>
                @foreach($acta->grupo->inscripcionesMaterias->where('estatus', '!=', \App\Enums\EstatusInscripcionMateria::BAJA) as $inscripcion)
                    <tr class="{{ $inscripcion->isAprobada() ? 'table-success' : ($inscripcion->isReprobada() ? 'table-danger' : '') }}">
                        <td>{{ $inscripcion->matriculaPeriodo->alumno->nombre_completo }}</td>
                        <td>{{ $inscripcion->matriculaPeriodo->alumno->numero_control ?? '—' }}</td>
                        <td>{{ $inscripcion->calificacion_final ?? '—' }}</td>
                        <td><span class="badge {{ $inscripcion->isAprobada() ? 'bg-success' : ($inscripcion->isReprobada() ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $inscripcion->estatus->label() }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="section mt-20">
    <div class="section-title">Firmas</div>
    <div style="display: flex; justify-content: space-between;">
        <div style="text-align: center; width: 30%;">
            <br><br>
            <hr style="border-top: 1px solid #72132e; width: 80%; margin: 0 auto;">
            <small>Docente</small>
        </div>
        <div style="text-align: center; width: 30%;">
            <br><br>
            <hr style="border-top: 1px solid #72132e; width: 80%; margin: 0 auto;">
            <small>Jefe de Departamento</small>
        </div>
        <div style="text-align: center; width: 30%;">
            <br><br>
            <hr style="border-top: 1px solid #72132e; width: 80%; margin: 0 auto;">
            <small>Control Escolar</small>
        </div>
    </div>
</div>
@endsection