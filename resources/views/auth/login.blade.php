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
                <div class="mt-6">
                    <x-ui.alert tone="danger">{{ $errors->first() }}</x-ui.alert>
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-5">
                @csrf

                <x-ui.field label="Correo electrónico" for="email">
                    <x-ui.input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus />
                </x-ui.field>

                <x-ui.field label="Contraseña" for="password">
                    <x-ui.input id="password" name="password" type="password" required />
                </x-ui.field>

                <label class="flex items-center gap-2 text-sm text-brand-muted">
                    <input type="checkbox" name="remember" value="1"
                        class="h-4 w-4 rounded border-brand-line accent-brand-primary">
                    Recordarme
                </label>

                <x-ui.button type="submit" block>Iniciar sesión</x-ui.button>
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
