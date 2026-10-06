<div>
    <x-ui.page-header title="Formatos Académicos II"
        description="Generación de PDFs institucionales: kardex, boleta, historial, acta, tira de materias, avance y constancia de terminación." />

    @if (session('success'))
        <div class="mt-4">
            <x-ui.alert tone="success">
                {{ session('success') }}
                <x-slot:action>
                    @if ($ultimoDocumentoId)
                        <x-ui.button size="sm" variant="secondary" icon="document-text"
                            href="{{ route('documentos.generados.descargar', $ultimoDocumentoId) }}">
                            Descargar PDF
                        </x-ui.button>
                    @endif
                </x-slot:action>
            </x-ui.alert>
        </div>
    @endif
    @error('generar')
        <div class="mt-4"><x-ui.alert tone="danger">{{ $message }}</x-ui.alert></div>
    @enderror

    {{-- Misma regla que el backend: si la policy `DocumentoGenerado::create` niega
         la emisión a este rol (p. ej. ADMINISTRACION), el botón no se muestra en
         lugar de ofrecer una acción que terminaría siempre en 403. --}}
    @can('create', \App\Models\DocumentoGenerado::class)
    <div class="mt-5">
        <x-ui.card>
            <x-slot:header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-brand-text">Generar formato académico</p>
                    <x-ui.badge tone="info">{{ $tipos[$tipo] ?? $tipo }}</x-ui.badge>
                </div>
            </x-slot:header>

            {{-- Los argumentos van como valores de Blade: `$tipo` a secas sería una
                 variable de JavaScript (undefined) y llegaría null a la acción. --}}
            <form novalidate wire:submit="generarFormato('{{ $tipo }}', {{ $alumno?->getKey() ?? 'null' }}, {{ $acta?->getKey() ?? 'null' }})">
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <x-ui.field label="Tipo de formato" for="fmt-tipo" required :error="$errors->first('tipo')">
                            <x-ui.select id="fmt-tipo" wire:model.live="tipo">
                                @foreach ($tipos as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    </div>

                    <div>
                        <x-ui.field label="Alumno" for="fmt-alumno" required :error="$errors->first('alumno')">
                            <x-ui.select id="fmt-alumno" wire:change="seleccionarAlumno($event.target.value)">
                                <option value="">Seleccionar alumno</option>
                                @foreach (\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                                    <option value="{{ $a->id }}" @selected($alumno?->getKey() === $a->id)>
                                        {{ $a->nombre_completo }} ({{ $a->curp }})
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    </div>

                    <div>
                        <x-ui.field label="Acta" for="fmt-acta" :error="$errors->first('acta')"
                            hint="Solo necesario para el acta de calificaciones.">
                            <x-ui.select id="fmt-acta" wire:change="seleccionarActa($event.target.value)">
                                <option value="">Seleccionar acta (solo para Acta de Calificaciones)</option>
                                @foreach (\App\Models\Acta::with('grupo.materia')->orderByDesc('created_at')->get() as $a)
                                    <option value="{{ $a->id }}" @selected($acta?->getKey() === $a->id)>
                                        {{ $a->grupo->clave }} - {{ $a->grupo->materia->nombre }} ({{ $a->periodo->clave }})
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap justify-end gap-2 border-t border-brand-line pt-4">
                    <x-ui.button variant="secondary" icon="x-mark"
                        wire:click="$reset('tipo', 'alumno', 'acta')">
                        Limpiar
                    </x-ui.button>
                    <x-ui.button type="submit" icon="document-text"
                        wire:loading.attr="disabled" wire:target="generarFormato">
                        <span wire:loading.remove wire:target="generarFormato">Generar PDF</span>
                        <span wire:loading wire:target="generarFormato"><x-ui.loading label="Generando…" /></span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
    @else
    <div class="mt-5">
        <x-ui.alert tone="info">
            Este rol consulta los formatos académicos, pero no emite PDFs institucionales.
            La emisión queda en Control Escolar y Administrador.
        </x-ui.alert>
    </div>
    @endcan
</div>
