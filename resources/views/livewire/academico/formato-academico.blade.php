<div>
    <x-ui.page-header title="Formatos Académicos II"
        description="Generación de PDFs institucionales: kardex, boleta, historial, acta, tira de materias y avance." />

    @if (session('success'))
        <div class="mt-4"><x-ui.alert tone="success">{{ session('success') }}</x-ui.alert></div>
    @endif
    @error('generar')
        <div class="mt-4"><x-ui.alert tone="danger">{{ $message }}</x-ui.alert></div>
    @enderror

    <div class="mt-5">
        <x-ui.card>
            <x-slot:header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-brand-text">Generar formato académico</p>
                    <x-ui.badge tone="info">{{ $tipos[$tipo] ?? $tipo }}</x-ui.badge>
                </div>
            </x-slot:header>

            <form wire:submit="generarFormato($tipo, {{ $alumno?->id ?? 'null' }}, {{ $acta?->id ?? 'null' }})">
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
                            <x-ui.select id="fmt-alumno" wire:model.live="alumno">
                                <option value="">Seleccionar alumno</option>
                                @foreach (\App\Models\Alumno::orderBy('apellidos')->orderBy('nombre')->get() as $a)
                                    <option value="{{ $a->id }}">{{ $a->nombre_completo }} ({{ $a->curp }})</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    </div>

                    <div>
                        <x-ui.field label="Acta" for="fmt-acta" :error="$errors->first('acta')"
                            hint="Solo necesario para el acta de calificaciones.">
                            <x-ui.select id="fmt-acta" wire:model.live="acta">
                                <option value="">Seleccionar acta (solo para Acta de Calificaciones)</option>
                                @foreach (\App\Models\Acta::with('grupo.materia')->orderByDesc('created_at')->get() as $a)
                                    <option value="{{ $a->id }}">
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
</div>
