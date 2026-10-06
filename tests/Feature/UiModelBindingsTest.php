<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Guardia de bindings Livewire (WAVE UI/UX 2.0-12 — GATE FINAL)
|--------------------------------------------------------------------------
|
| Dos formas distintas de escribir mal un binding rompían los formularios en
| vivo, y ninguna aparecía en la revisión visual (que sólo mira el render
| inicial) ni en los tests (que usan `->set()` / `->call()` del lado PHP):
|
| 1. `wire:model` sobre una propiedad tipada con un modelo Eloquent: el
|    navegador manda el id como texto y Livewire lo asigna sin convertir,
|    con lo que la petición muere con
|
|        TypeError: Cannot assign string to property ...::$grupo of type ?Grupo
|
|    → 500 al elegir en el combo (asistencia, actas, formatos II, calificaciones)
|    o al enviar el formulario (bajas, equivalencias, becas).
|
| 2. `$propiedad` escrita a secas dentro de `wire:click` / `wire:submit`: eso
|    se evalúa en JavaScript, donde es una variable indefinida, así que la
|    acción recibía null
|
|        TypeError: generarFormato(): Argument #1 ($tipo) must be of type string, null given
|
|    → 500 al pulsar el botón (formatos II, estado de cuenta, carta de no adeudo).
|
| Sí funcionan y por eso se admiten:
|  * `wire:model="estatus"` con un enum (EnumSynth::hydrateFromType convierte).
|  * `wire:model="evaluacion.id"` (camino anidado: se escribe sobre el modelo
|    ya cargado, no se asigna a la propiedad tipada).
|  * `{{ $propiedad }}` dentro de la expresión: Blade lo interpola antes y
|    Livewire resuelve el modelo a partir del id (ImplicitlyBoundMethod).
|
| El patrón correcto para un combo es
| `wire:change="accion($event.target.value)"` + `@selected` en la opción.
|
*/

/**
 * Vistas (texto) de cada componente Livewire, siguiendo el `render()` real e
 * incluyendo los parciales que la vista trae con `@include`.
 *
 * @return array<string, array{0: string, 1: list<string>}> ruta del componente => [nombre, vistas]
 */
function ui_vistas_de_componentes(): array
{
    $resultado = [];

    foreach ((new Finder())->in(app_path('Livewire'))->files()->name('*.php') as $archivo) {
        $codigo = $archivo->getContents();

        preg_match_all("/view\('([^']+)'/", $codigo, $coincidencias);
        $rutas = array_unique($coincidencias[1] ?? []);
        if ($rutas === []) {
            continue;
        }

        $vistas = [];
        foreach ($rutas as $ruta) {
            $archivoVista = resource_path('views/' . str_replace('.', '/', $ruta) . '.blade.php');
            if (! is_file($archivoVista)) {
                continue;
            }

            $texto = (string) file_get_contents($archivoVista);
            $vistas[] = $texto;

            preg_match_all("/@include\('livewire\.([^']+)'\)/", $texto, $incluidos, PREG_SET_ORDER);
            foreach ($incluidos as $incluido) {
                $parcial = resource_path('views/livewire/' . str_replace('.', '/', $incluido[1]) . '.blade.php');
                if (is_file($parcial)) {
                    $vistas[] = (string) file_get_contents($parcial);
                }
            }
        }

        if ($vistas !== []) {
            $resultado[$archivo->getRelativePathname()] = [$codigo, $vistas];
        }
    }

    return $resultado;
}

test('ninguna vista liga wire:model a una propiedad tipada con un modelo eloquent', function (): void {
    $violaciones = [];
    $bindings = 0;
    $vistasRevisadas = 0;

    foreach (ui_vistas_de_componentes() as $ruta => [$codigo, $vistas]) {
        // nombre corto => tipo declarado de cada propiedad pública
        $tipos = [];
        preg_match_all('/^\s*public\s+\??([A-Za-z\\\\][\w\\\\]*)\s+\$(\w+)/m', $codigo, $propiedades, PREG_SET_ORDER);
        foreach ($propiedades as $propiedad) {
            $tipos[$propiedad[2]] = ltrim($propiedad[1], '?');
        }

        // nombre corto => FQCN resuelto a partir de los `use`
        $usos = [];
        preg_match_all('/^use\s+([A-Za-z\\\\][\w\\\\]*);/m', $codigo, $imports, PREG_SET_ORDER);
        foreach ($imports as $import) {
            $fqcn = $import[1];
            $usos[substr($fqcn, strrpos($fqcn, '\\') + 1)] = $fqcn;
        }

        foreach ($vistas as $texto) {
            $vistasRevisadas++;

            preg_match_all('/wire:model(?:\.[\w.]+)?="([^"]+)"/', $texto, $enlaces, PREG_SET_ORDER);
            foreach ($enlaces as $enlace) {
                $bindings++;
                $expresion = trim($enlace[1]);

                // caminos anidados y dinámicos no se resuelven aquí
                if (str_contains($expresion, '.') || str_contains($expresion, '[') || str_contains($expresion, '{')) {
                    continue;
                }

                if (! isset($tipos[$expresion])) {
                    continue;
                }

                $tipo = $tipos[$expresion];
                $fqcn = $usos[$tipo] ?? $tipo;

                if (enum_exists($fqcn)) {
                    continue; // EnumSynth sí convierte el texto a enum
                }

                if (class_exists($fqcn) && is_a($fqcn, Model::class, true)) {
                    $violaciones[] = sprintf(
                        '%s → wire:model="%s" (%s)',
                        $ruta,
                        $expresion,
                        $tipo
                    );
                }
            }
        }
    }

    // la revisión tiene que estar mirando vistas de verdad
    expect($vistasRevisadas)->toBeGreaterThan(10);
    expect($bindings)->toBeGreaterThan(10);

    expect($violaciones)->toBe([]);
});

test('ninguna vista usa una propiedad del componente como si fuera una variable de javascript', function (): void {
    $violaciones = [];
    $expresiones = 0;

    foreach (ui_vistas_de_componentes() as $ruta => [$codigo, $vistas]) {
        $propiedades = [];
        preg_match_all('/^\s*public\s+\??[\w\\\\]+\s+\$(\w+)/m', $codigo, $encontradas);
        foreach ($encontradas[1] as $nombre) {
            $propiedades[] = $nombre;
        }

        foreach ($vistas as $texto) {
            preg_match_all('/wire:(click|submit|keydown|keyup|change|input)="([^"]*)"/s', $texto, $enlaces, PREG_SET_ORDER);
            foreach ($enlaces as $enlace) {
                $expresiones++;

                // lo que Blade ya interpoló no llega a JavaScript
                $sinBlade = preg_replace('/\{\{.*?\}\}/s', '', $enlace[2]);

                preg_match_all('/(?<![\w.$])(\$[A-Za-z_]\w*)/', $sinBlade, $identificadores);
                foreach ($identificadores[1] as $identificador) {
                    $nombre = substr($identificador, 1);

                    if (in_array($nombre, ['wire', 'event', 'refresh', 'set', 'toggle', 'dispatch', 'js'], true)) {
                        continue;
                    }

                    if (in_array($nombre, $propiedades, true)) {
                        $violaciones[] = sprintf(
                            '%s → wire:%s="%s" usa %s (indefinido en JS)',
                            $ruta,
                            $enlace[1],
                            trim($enlace[2]),
                            $identificador
                        );
                    }
                }
            }
        }
    }

    expect($expresiones)->toBeGreaterThan(10);

    expect($violaciones)->toBe([]);
});
