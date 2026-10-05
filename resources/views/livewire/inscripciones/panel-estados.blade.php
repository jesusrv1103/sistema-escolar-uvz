{{-- Panel de revisión final (Fase 12, FC-7): requisitos RB-8 y cierre a
     ENROLLED con número de control. Sin información médica (RB-18) y sin
     mensualidades como requisito (RB-8 corregida: no bloquean); la acción
     siempre se delega server-side al servicio (@can solo complementa UX). --}}
<div>
    <x-ui.page-header title="Revisión final"
        :description="'Inscripción #' . $inscripcion->id . ' · ' . ($alumno?->apellidos ?? '') . ', ' . ($alumno?->nombre ?? '')">
        <x-slot:meta>
            <x-ui.badge :tone="$inscripcion->estatus->value === 'ENROLLED' ? 'success' : 'primary'">
                Estado actual: {{ $inscripcion->estatus->label() }}
            </x-ui.badge>
        </x-slot:meta>
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-left" href="{{ route('inscripciones.ver', $inscripcion) }}">
                Volver a la inscripción
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <nav class="mt-2 flex flex-wrap items-center gap-2 text-xs text-brand-muted" aria-label="Ruta de navegación">
        <a href="{{ route('inscripciones.lista') }}" class="font-medium text-brand-primary hover:underline">Inscripciones</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('inscripciones.ver', $inscripcion) }}" class="font-medium text-brand-primary hover:underline">Inscripción</a>
        <span aria-hidden="true">/</span>
        <span>Revisión final</span>
    </nav>

    @if (session('revision'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('revision') }}</x-ui.alert></div>
    @endif

    <div class="mt-5 grid gap-4 lg:grid-cols-3">
        {{-- ---------------------- Concepto de pago ---------------------- --}}
        <x-ui.card>
            <x-slot:header>
                <p class="text-sm font-semibold text-brand-text">Pago del concepto de inscripción</p>
            </x-slot:header>

            @if ($concepto === null)
                <p class="text-sm text-brand-muted">Este periodo no tiene concepto de inscripción.</p>
            @else
                <p class="text-lg font-semibold text-brand-text">
                    {{ $concepto->descripcion }}
                </p>
                <p class="mt-2 flex flex-wrap gap-1.5">
                    <x-ui.badge :tone="$pagoOk ? 'success' : 'warning'">
                        {{ $concepto->estatus->label() }}
                    </x-ui.badge>
                    <x-ui.badge tone="neutral">Periodo {{ $periodo?->clave ?? '—' }}</x-ui.badge>
                </p>
            @endif
        </x-ui.card>

        {{-- ----------------------- Documentos ----------------------- --}}
        <x-ui.card>
            <x-slot:header>
                <p class="text-sm font-semibold text-brand-text">Documentos obligatorios</p>
            </x-slot:header>

            <p class="text-lg font-semibold text-brand-text">
                {{ $documentos['aceptados'] }} de {{ $documentos['total'] }} aceptados
            </p>

            <ul class="mt-3 flex flex-wrap gap-1.5">
                <li><x-ui.badge :tone="$documentos['pendientes'] > 0 ? 'warning' : 'neutral'">
                    {{ $documentos['pendientes'] }} pendientes
                </x-ui.badge></li>
                <li><x-ui.badge :tone="$documentos['rechazados'] > 0 ? 'danger' : 'neutral'">
                    {{ $documentos['rechazados'] }} rechazados
                </x-ui.badge></li>
                <li><x-ui.badge :tone="$documentos['ausentes'] > 0 ? 'danger' : 'neutral'">
                    {{ $documentos['ausentes'] }} ausentes
                </x-ui.badge></li>
            </ul>

            <div class="mt-4 border-t border-brand-line pt-3">
                <x-ui.button size="sm" variant="secondary" icon="document-text"
                    href="{{ route('inscripciones.documentos', $inscripcion) }}">
                    Gestionar documentos
                </x-ui.button>
            </div>
        </x-ui.card>

        {{-- ---------------------- Observaciones ---------------------- --}}
        <x-ui.card>
            <x-slot:header>
                <p class="text-sm font-semibold text-brand-text">Faltantes señalados</p>
            </x-slot:header>

            <p class="text-lg font-semibold text-brand-text">{{ $faltantesAbiertos }} abiertos</p>
            <p class="mt-1 text-sm text-brand-muted">
                Observaciones de tipo faltante que siguen sin cerrarse.
            </p>

            <div class="mt-4 border-t border-brand-line pt-3">
                <x-ui.button size="sm" variant="secondary" icon="clipboard"
                    href="{{ route('inscripciones.observaciones', $inscripcion) }}">
                    Ver observaciones
                </x-ui.button>
            </div>
        </x-ui.card>
    </div>

    {{-- ---------------------- Requisitos pendientes ---------------------- --}}
    <section class="mt-5" aria-labelledby="requisitos-titulo">
        <p id="requisitos-titulo" class="ui-section-title mb-2">Requisitos antes de cerrar</p>
        <x-ui.card>
            @if ($requisitos === [])
                <x-ui.alert tone="success">
                    Todos los requisitos obligatorios están satisfechos.
                </x-ui.alert>
            @else
                <x-ui.alert tone="warning">
                    <p class="mb-1 font-semibold">Falta lo siguiente:</p>
                    <ul class="list-inside list-disc space-y-0.5 text-sm">
                        @foreach ($requisitos as $requisito)
                            <li>{{ $requisito }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            @unless ($convenioOk)
                <div class="mt-3">
                    <x-ui.alert tone="warning">
                        El convenio vinculado aún no está aprobado.
                    </x-ui.alert>
                </div>
            @endunless
        </x-ui.card>
    </section>

    {{-- --------------------------- Cierre --------------------------- --}}
    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-brand-muted">
            Al finalizar se asigna el número de control y la inscripción queda cerrada.
            La validación definitiva ocurre en el servidor.
        </p>

        @if ($puedeFinalizar)
            <x-ui.button size="lg" icon="check" wire:click="finalizar"
                wire:loading.attr="disabled" wire:target="finalizar"
                wire:confirm="¿Cerrar la inscripción y asignar el número de control? Esta acción no se puede deshacer.">
                <span wire:loading.remove wire:target="finalizar">Finalizar inscripción</span>
                <span wire:loading wire:target="finalizar"><x-ui.loading label="Procesando…" /></span>
            </x-ui.button>
        @else
            <x-ui.button size="lg" :disabled="true">
                Requisitos pendientes
            </x-ui.button>
        @endif
    </div>

    @if ($errors->any())
        <div class="mt-4">
            <x-ui.alert tone="danger">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        </div>
    @endif
</div>
