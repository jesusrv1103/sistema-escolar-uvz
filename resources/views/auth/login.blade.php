@extends('layouts.app')

@section('title', 'Iniciar sesión — '.config('branding.short_name'))

@section('content')
    <div class="mx-auto flex max-w-md flex-col items-center py-8 sm:py-14">
        <div class="w-full rounded-2xl border border-brand-line bg-white p-8 shadow-sm">
            <div class="flex flex-col items-center text-center">
                <img src="{{ asset(config('branding.logo_vertical')) }}" alt="{{ config('branding.name') }}"
                    class="h-24 w-auto" width="643" height="524">
                <h1 class="mt-4 text-base font-semibold text-brand-text">{{ config('branding.name') }}</h1>
                <p class="mt-0.5 text-sm font-medium tracking-widest text-brand-primary uppercase">
                    {{ config('branding.system_name') }}
                </p>
            </div>

            @if ($errors->any())
                <div class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-brand-text">Correo electrónico</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                        class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm shadow-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-brand-text">Contraseña</label>
                    <input id="password" name="password" type="password" required
                        class="mt-1 w-full rounded-md border border-brand-line px-3 py-2 text-sm shadow-sm focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                </div>

                <label class="flex items-center gap-2 text-sm text-brand-muted">
                    <input type="checkbox" name="remember" value="1" class="rounded border-brand-line">
                    Recordarme
                </label>

                <button type="submit"
                    class="w-full rounded-md bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-secondary focus:outline-none focus:ring-2 focus:ring-brand-accent">
                    Iniciar sesión
                </button>
            </form>
        </div>

        <p class="mt-5 text-center font-display text-sm italic text-brand-muted">
            {{ config('branding.slogan') }}
        </p>
        <p class="mt-1 text-center text-xs text-brand-muted">
            {{ config('branding.short_name') }} · {{ config('branding.hashtag') }}
        </p>
    </div>
@endsection
