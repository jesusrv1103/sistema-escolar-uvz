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
        <div class="doc-type">Lista de Grupo</div>
        @if($folio)
            <div class="doc-folio">Folio: {{ $folio }}</div>
        @endif
    </div>
</div>

@if($grupo)
<div class="section">
    <div class="section-title">Información del Grupo</div>
    <div class="data-grid">
        <div class="data-item">
            <span class="data-label">Grupo</span>
            <span class="data-value">{{ $grupo->clave }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Materia</span>
            <span class="data-value">{{ $grupo->materia->clave }} - {{ $grupo->materia->nombre }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Créditos</span>
            <span class="data-value">{{ $grupo->materia->creditos }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Periodo</span>
            <span class="data-value">{{ $grupo->periodo->clave }} - {{ $grupo->periodo->nombre }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Docente</span>
            <span class="data-value">{{ $grupo->docente?->nombreCompleto() ?? 'Sin asignar' }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Aula</span>
            <span class="data-value">{{ $grupo->aula?->nombre . ' (' . $grupo->aula->edificio . ')' ?? 'Sin asignar' }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Cupo</span>
            <span class="data-value">{{ $grupo->cupo }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Turno</span>
            <span class="data-value">{{ $grupo->turno?->label() }}</span>
        </div>
        <div class="data-item">
            <span class="data-label">Estatus</span>
            <span class="data-value"><span class="badge badge-{{ strtolower(str_replace('_', '-', $grupo->estatus)) }}">{{ $grupo->estatus }}</span></span>
        </div>
    </div>
</div>

@if($grupo->horarios->count())
<div class="section">
    <div class="section-title">Horarios</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Día</th>
                    <th>Hora Inicio</th>
                    <th>Hora Fin</th>
                    <th>Aula</th>
                </tr>
            </thead>
            <tbody>
                @foreach($grupo->horarios->sortBy('dia.orden')->sortBy('hora_inicio') as $h)
                    <tr>
                        <td>{{ $h->dia->label() }}</td>
                        <td>{{ $h->hora_inicio->format('H:i') }}</td>
                        <td>{{ $h->hora_fin->format('H:i') }}</td>
                        <td>{{ $grupo->aula?->nombre . ' (' . $grupo->aula->edificio . ')' ?? 'Sin asignar' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endif

@if($grupo && $grupo->inscripcionesMaterias()->where('estatus', 'CURSANDO')->count())
<div class="section">
    <div class="section-title">Alumnos Inscritos ({{ $grupo->inscripcionesMaterias()->where('estatus', 'CURSANDO')->count() }}/{{ $grupo->cupo }})</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Número de Control</th>
                    <th>Nombre Completo</th>
                    <th>CURP</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $alumnos = $grupo->inscripcionesMaterias()
                        ->where('estatus', 'CURSANDO')
                        ->with('matriculaPeriodo.alumno')
                        ->get()
                        ->sortBy(fn($i) => $i->matriculaPeriodo->alumno->apellidos . ' ' . $i->matriculaPeriodo->alumno->nombre);
                @endphp
                @foreach($alumnos as $index => $inscripcion)
                    @php $alumno = $inscripcion->matriculaPeriodo->alumno; @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $alumno->numero_control ?? 'Por asignar' }}</td>
                        <td>{{ $alumno->nombre_completo }}</td>
                        <td>{{ $alumno->curp }}</td>
                        <td>{{ $alumno->correo }}</td>
                        <td>{{ $alumno->telefono }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-center">No hay alumnos inscritos en este grupo.</p>
@endif

<div class="section mt-20">
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