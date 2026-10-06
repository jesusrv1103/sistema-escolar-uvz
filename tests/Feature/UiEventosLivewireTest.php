<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use App\Livewire\Academico\BecaAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;

uses(RefreshDatabase::class)->group('ui', 'eventos');

/*
|--------------------------------------------------------------------------
| Regresión WAVE UI/UX 2.0-12 — listeners de Livewire
|--------------------------------------------------------------------------
|
| Varios componentes declaraban en `$listeners` eventos que ellos mismos
| despachan (o que nadie despacha), sin definir el método manejador:
|
|     protected $listeners = ['becaRegistrada'];
|     $this->dispatch('becaRegistrada');   // ← no hay becaRegistrada()
|
| Livewire devuelve el evento al propio componente, que no encuentra el
| manejador y revienta la petición justo después de guardar:
|
|     EventHandlerDoesNotExist: Handler for event becaRegistrada does not exist
|
| Se veía sólo al usar el formulario en el navegador: los tests llaman a la
| acción con `->call()` y el `dispatch()` final nunca se evaluaba en el DOM.
|
| El `dispatch()` se mantiene (lo reciben otros componentes de la página);
| lo que se retira es la escucha propia, que no tenía manejador.
|
*/

/**
 * @return list<array{0: string, 1: string, 2: string}> [componente, evento, método]
 */
function ui_listeners_sin_manejador(): array
{
    $sinManejador = [];

    foreach ((new Finder())->in(app_path('Livewire'))->files()->name('*.php') as $archivo) {
        $ruta = 'App\\Livewire\\' . str_replace(
            ['/', '\\'],
            '\\',
            str_replace('.php', '', $archivo->getRelativePathname())
        );

        if (! class_exists($ruta)) {
            continue;
        }

        $reflexion = new ReflectionClass($ruta);

        if (! $reflexion->hasProperty('listeners')) {
            continue;
        }

        $propiedad = $reflexion->getProperty('listeners');
        $propiedad->setAccessible(true);

        foreach ($propiedad->getDefaultValue() as $evento => $destino) {
            $metodo = is_int($evento) ? $destino : $destino;

            if (! $reflexion->hasMethod($metodo) || ! $reflexion->getMethod($metodo)->isPublic()) {
                $sinManejador[] = sprintf(
                    '%s → evento "%s" sin método %s()',
                    str_replace('App\\Livewire\\', '', $ruta),
                    is_int($evento) ? $evento : $evento,
                    $metodo
                );
            }
        }
    }

    return $sinManejador;
}

test('todo listener declarado por un componente tiene su método manejador', function (): void {
    $violaciones = ui_listeners_sin_manejador();

    expect($violaciones)->toBe([]);
});

test('la vuelta de un evento que el propio componente escucha no rompe la peticion', function (): void {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $reflexion = new ReflectionClass(BecaAdmin::class);
    $propiedad = $reflexion->getProperty('listeners');
    $propiedad->setAccessible(true);
    $escucha = $propiedad->getDefaultValue();

    $testable = Livewire::actingAs($admin)->test(BecaAdmin::class);

    // El navegador devuelve al propio componente los eventos que éste despacha
    // y a la vez declara como escuchados: ése es el camino que daba 500.
    foreach (['becaRegistrada', 'asignacionRegistrada', 'aprobacionRealizada'] as $evento) {
        if (in_array($evento, $escucha, true)) {
            $testable->dispatch($evento);
        }
    }

    expect($testable->html())->not->toBe('');
});
