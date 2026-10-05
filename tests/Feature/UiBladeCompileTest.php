<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Guardia de compilación Blade
|--------------------------------------------------------------------------
|
| Falla si CUALQUIER vista compila a PHP con sintaxis inválida. Es la red de
| seguridad de todo el WAVE UI/UX: errores clásicos como un @if sin @endif,
| un @can sin @endcan o un @php(expr) engullido por un @endphp posterior
| solo estallan en tiempo de render, es decir, en producción.
|
| No toca base de datos ni lógica de negocio: solo compila y parsea.
|
*/

test('todas las vistas blade compilan a php con sintaxis valida', function (): void {
    $compiler = app('blade.compiler');

    $archivos = (new Finder())
        ->in(resource_path('views'))
        ->name('*.blade.php')
        ->sortByName();

    $fallos = [];

    foreach ($archivos as $archivo) {
        $php = $compiler->compileString($archivo->getContents(), $archivo->getPathname());

        try {
            token_get_all($php, TOKEN_PARSE);
        } catch (ParseError $e) {
            $fallos[] = sprintf('%s: %s (línea %d)', $archivo->getRelativePathname(), $e->getMessage(), $e->getLine());
        }
    }

    expect($fallos)->toBeEmpty();

    // Salvaguarda: la suite debe estar revisando un número razonable de vistas.
    expect(count($archivos))->toBeGreaterThan(50);
});
