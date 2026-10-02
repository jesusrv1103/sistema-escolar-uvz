@extends('layouts.app')

@section('title', config('branding.system_name').' — '.config('branding.short_name'))

@section('content')
    <div class="rounded-2xl border border-brand-line bg-white p-8 shadow-sm">
        <p class="text-sm font-semibold tracking-wider text-brand-primary uppercase">
            {{ config('branding.name') }}
        </p>
        <h1 class="mt-1 text-xl font-semibold text-brand-text">{{ config('branding.system_name') }}</h1>

        <p class="mt-4 text-brand-muted">
            Bienvenido(a), <span class="font-medium text-brand-text">{{ auth()->user()->name }}</span>.
            Este es el panel administrativo de admisiones e inscripción.
        </p>
        <p class="mt-1 text-sm text-brand-muted">
            Los contadores operativos (inscripciones pendientes, pagos por validar, documentos pendientes e
            inscripciones completadas) se incorporan en la Fase 14.
        </p>

        <p class="mt-6 font-display text-sm italic text-brand-muted">
            {{ config('branding.slogan') }} · {{ config('branding.hashtag') }}
        </p>
    </div>
@endsection
