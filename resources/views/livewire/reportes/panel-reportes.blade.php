{{--
  Módulo global de Reportes operativos (2.0-17) — pantalla única `/reportes`.

  Categoría (según capacidad) → reporte → filtros → resumen → tabla → CSV.
  Sólo consulta: sin gráficas, sin KPIs inventados y sin datos sensibles.
  Los valores de estado SIEMPRE llegan etiquetados en español desde
  `CatalogoReportes::paraTabla()` (§18); aquí no se formatean reglas de negocio.

  La autorización real vive en el componente (Gates) y en el controlador del
  CSV; los `@can` de esta vista sólo ocultan atajos, igual que en el app shell.
--}}
<div>
    <x-ui.page-header title="Reportes"
        description="{{ $definicion['nombre'] }} — {{ $definicion['descripcion'] }}">
        <x-slot:actions>
            @if ($puedeExportar && $urlCsv)
                <x-ui.button variant="secondary" icon="document-text" :href="$urlCsv"
                    aria-label="Exportar el reporte actual a CSV">
                    Exportar CSV
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ------------------------- Categorías ------------------------- --}}
    <div class="mt-5 flex flex-wrap gap-2" role="tablist" aria-label="Categorías de reportes">
        @foreach ($categorias as $clave => $defCategoria)
            <x-ui.button size="sm" :variant="$clave === $categoria ? 'primary' : 'secondary'"
                wire:click="seleccionarCategoria('{{ $clave }}')" role="tab"
                :aria-selected="$clave === $categoria ? 'true' : 'false'">
                {{ $defCategoria['label'] }}
            </x-ui.button>
        @endforeach
    </div>

    {{-- --------------------- Reportes de la categoría --------------------- --}}
    <div class="mt-3 flex flex-wrap gap-2" role="tablist" aria-label="Reportes disponibles">
        @foreach ($reportes as $defReporte)
            <x-ui.button size="sm" :variant="$defReporte['slug'] === $reporte ? 'primary' : 'ghost'"
                wire:click="seleccionarReporte('{{ $defReporte['slug'] }}')" role="tab"
                :aria-selected="$defReporte['slug'] === $reporte ? 'true' : 'false'">
                {{ $defReporte['nombre'] }}
            </x-ui.button>
        @endforeach
    </div>

    {{-- ------------- Subtipo de movimientos (R5: subreportes) ------------- --}}
    @if ($reporte === 'movimientos')
        <div class="mt-3 flex flex-wrap gap-2" role="tablist" aria-label="Tipo de movimiento">
            @foreach ($tiposMovimiento as $claveTipo => $etiquetaTipo)
                <x-ui.button size="sm" :variant="$claveTipo === $tipo ? 'primary' : 'secondary'"
                    wire:click="seleccionarTipo('{{ $claveTipo }}')" role="tab"
                    :aria-selected="$claveTipo === $tipo ? 'true' : 'false'">
                    {{ $etiquetaTipo }}
                </x-ui.button>
            @endforeach
        </div>
    @endif

    {{-- ---------------------------- Filtros ---------------------------- --}}
    <div class="mt-5">
        <x-ui.filters title="Filtros del reporte">
            <x-slot:actions>
                <x-ui.button size="sm" variant="ghost" icon="x-mark" wire:click="limpiarFiltros">
                    Limpiar filtros
                </x-ui.button>
            </x-slot:actions>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($definicion['filtros'] as $nombreFiltro => $etiquetaFiltro)
                    <x-ui.field :label="$etiquetaFiltro" :error="$errors->first($nombreFiltro)">
                        @if ($nombreFiltro === 'busqueda')
                            <x-ui.input type="text" wire:model.live.debounce.400ms="{{ $nombreFiltro }}"
                                placeholder="Nombre, apellidos o número de control"
                                aria-label="{{ $etiquetaFiltro }}" />
                        @elseif (in_array($nombreFiltro, ['desde', 'hasta'], true))
                            <x-ui.input type="date" wire:model.live="{{ $nombreFiltro }}"
                                aria-label="{{ $etiquetaFiltro }}" />
                        @else
                            <x-ui.select wire:model.live="{{ $nombreFiltro }}"
                                aria-label="{{ $etiquetaFiltro }}">
                                <option value="">Todos</option>
                                @foreach ($opciones[$nombreFiltro] ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}">{{ $opcion['label'] }}</option>
                                @endforeach
                            </x-ui.select>
                        @endif
                    </x-ui.field>
                @endforeach

                @if ($definicion['filtros'] === [])
                    <p class="text-sm text-brand-muted">Este reporte no requiere filtros.</p>
                @endif
            </div>
        </x-ui.filters>
    </div>

    {{-- ----------------------- Resumen del conjunto ----------------------- --}}
    @if (count($resumen) > 0)
        <div class="mt-4 flex flex-wrap gap-2" role="status" aria-label="Resumen del reporte">
            @foreach ($resumen as $chip)
                <div class="rounded-lg border border-brand-line bg-white px-3 py-2 text-sm">
                    <span class="text-brand-muted">{{ $chip['label'] }}</span>
                    <span class="ml-2 font-semibold text-brand-text">{{ $chip['valor'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ------------------------------ Tabla ------------------------------ --}}
    <div class="mt-4" wire:loading.class="opacity-60">
        <x-ui.card :flush="count($filas) > 0">
            @if (count($filas) === 0)
                <x-ui.empty-state icon="inbox-stack" title="Sin resultados"
                    message="Ningún registro coincide con los filtros seleccionados. Ajusta o limpia los filtros para ver el conjunto completo." />
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                @foreach ($columnas as $columna)
                                    <th scope="col">{{ $columna['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filas as $indice => $celdas)
                                <tr wire:key="{{ $reporte }}-{{ $tipo }}-{{ $pagina->firstItem() ?? 0 }}-{{ $indice }}">
                                    @foreach ($celdas as $celda)
                                        <td @class([
                                            'whitespace-nowrap' => in_array(
                                                $celda['tipo'],
                                                ['fecha', 'fecha_hora', 'entero', 'moneda', 'porcentaje', 'calificacion'],
                                                true,
                                            ),
                                        ])>
                                            @if ($celda['tono'] !== null)
                                                <x-ui.badge :tone="$celda['tono']">{{ $celda['texto'] }}</x-ui.badge>
                                            @else
                                                {{ $celda['texto'] }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-brand-line px-5 py-3">{{ $pagina->links() }}</div>
            @endif
        </x-ui.card>
    </div>
</div>
