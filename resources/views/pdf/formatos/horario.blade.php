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
        <div class="doc-type">Horario del Alumno</div>
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
            <span class="data-value">{{ $alumno.nombre_completo }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Número de control</span>
            <span class="data-value">{{ $alumno.numero_control ?? 'Por asignar' }}</span>
        </div>
        @if($matricula && $matricula.periodo)
            <div class="data-item">
                <span class="data-label">Periodo</span>
                <span class="data-value">{{ $matricula.periodo.clave }} - {{ $matricula.periodo.nombre }}</span>
            </div>
        @endif
    </div>
</div>

@if($matricula && $matricula.inscripcionesCursando && $matricula->inscripcionesCursando->count())
<div class="section">
    <div class="section-title">Horario Semanal</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Día</th>
                    <th>Hora Inicio</th>
                    <th>Hora Fin</th>
                    <th>Materia</th>
                    <th>Grupo</th>
                    <th>Docente</th>
                    <th>Aula</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $horarios = collect();
                    foreach($matricula->inscripcionesCursando as $inscripcion) {
                        if($inscripcion->grupo) {
                            foreach($inscripcion->grupo->horarios as $h) {
                                $horarios->push([
                                    'dia' => $h->dia->orden(),
                                    'dia_label' => $h->dia->label(),
                                    'hora_inicio' => $h->hora_inicio,
                                    'hora_fin' => $h->hora_fin,
                                    'materia' => $inscripcion->materia->clave . ' - ' . $inscripcion->materia->nombre,
                                    'grupo' => $inscripcion->grupo->clave,
                                    'docente' => $inscripcion->grupo->docente?->nombreCompleto() ?? 'Sin asignar',
                                    'aula' => $inscripcion->grupo->aula?->nombre ?? 'Sin asignar',
                                ]);
                            }
                        }
                    }
                    $horarios = $horarios->sortBy('dia')->sortBy('hora_inicio');
                @endphp
                @foreach($horarios as $h)
                    <tr>
                        <td>{{ $h['dia_label'] }}</td>
                        <td>{{ $h['hora_inicio']->format('H:i') }}</td>
                        <td>{{ $h['hora_fin']->format('H:i') }}</td>
                        <td>{{ $h['materia'] }}</td>
                        <td>{{ $h['grupo'] }}</td>
                        <td>{{ $h['docente'] }}</td>
                        <td>{{ $h['aula'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-center">No hay materias inscritas con horario en este periodo.</p>
@endif

@if($matricula && $matricula.inscripcionesCursando && $matricula->inscripcionesCursando->count())
<div class="section mt-20">
    <div class="section-title">Resumen de Carga</div>
    <div class="data-grid">
        <div class="data-item">
            <span class="data-label">Total materias</span>
            <span class="data-value">{{ $matricula->inscripcionesCursando->count() }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Total créditos</span>
            <span class="data-value">{{ $matricula->inscripcionesCursando->sum('materia.creditos') }}</span>
        </div>
    </div>
</div>
@endif
@endsection