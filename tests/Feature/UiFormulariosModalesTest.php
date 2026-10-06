<?php

declare(strict_types=1);

use App\Livewire\Academico\BecaAdmin;
use App\Models\User;
use App\Enums\RolUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;

uses(RefreshDatabase::class)->group('ui', 'modales');

/*
|--------------------------------------------------------------------------
| Regresión WAVE UI/UX 2.0-12 — botón "Guardar" de los modales
|--------------------------------------------------------------------------
|
| `x-ui.modal` renderiza `<x-slot:footer>` fuera del hueco por defecto, así
| que cuando el formulario envolvía el pie (lo habitual en los modales), el
| botón `type="submit"` terminaba en el DOM FUERA del `<form>`:
|
|     submit dentro del form: 0 | submit fuera del form: 1
|
| El clic no disparaba ningún evento `submit`, la petición Livewire nunca salía
| y el modal se quedaba abierto sin guardar ni devolver errores. No se veía en
| la revisión visual (sólo se mira el render) ni en los tests (llaman a la
| acción con `->call()`, no al DOM).
|
| Solución: el atributo HTML5 `form=""`, que asocia un botón al formulario
| aunque esté fuera de él. `wire:submit` sigue siendo el que envía, y la tecla
| Intro dentro del formulario sigue funcionando.
|
*/

/**
 * @return list<array{0: string, 1: string, 2: string}> [archivo, id del formulario, acción]
 */
function ui_formularios_con_pie_de_submit(): array
{
    $encontrados = [];

    foreach ((new Finder())->in(resource_path('views'))->files()->name('*.blade.php') as $archivo) {
        $texto = $archivo->getContents();

        preg_match_all('/<form\b[^>]*wire:submit="([^"]+)"[^>]*>/', $texto, $etiquetas, PREG_OFFSET_CAPTURE);

        foreach ($etiquetas[1] as $i => [$accion, $inicio]) {
            $cierre = strpos($texto, '</form>', $inicio);

            if ($cierre === false) {
                continue;
            }

            $dentro = substr($texto, $inicio, $cierre - $inicio);
            $pie = strpos($dentro, '<x-slot:footer>');

            if ($pie === false || ! str_contains(substr($dentro, $pie), 'type="submit"')) {
                continue;
            }

            preg_match('/\bid="([^"]+)"/', $etiquetas[0][$i][0], $id);

            $encontrados[] = [
                str_replace(resource_path('views') . '/', '', $archivo->getPathname()),
                $id[1] ?? '',
                $accion,
            ];
        }
    }

    return $encontrados;
}

test('todo modal que envuelve su pie en el formulario asocia el botón con el atributo form', function (): void {
    $violaciones = [];

    foreach (ui_formularios_con_pie_de_submit() as [$archivo, $id, $accion]) {
        if ($id === '') {
            $violaciones[] = sprintf('%s: <form wire:submit="%s"> sin id', $archivo, $accion);

            continue;
        }

        $texto = (string) file_get_contents(resource_path('views/' . $archivo));

        if (! preg_match('/<x-ui\.button\s+type="submit"\s+form="' . preg_quote($id, '/') . '"/', $texto)) {
            $violaciones[] = sprintf('%s: botón de "%s" sin form="%s"', $archivo, $accion, $id);
        }
    }

    // la revisión tiene que estar mirando los modales de verdad
    expect(count(ui_formularios_con_pie_de_submit()))->toBeGreaterThan(5);

    expect($violaciones)->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Regresión — todo modal que envuelve su pie tiene botón de envío
|--------------------------------------------------------------------------
|
| El guard anterior sólo miraba los pies que YA traían `type="submit"`, así
| que un modal cuyo botón del pie se quedó como `type="button"` (el valor por
| defecto de `x-ui.button`) pasaba inadvertido: no enviaba nada, no llegaba a
| Livewire ni devolvía errores. Es el caso del modal "Autorizar Reingreso" de
| Bajas, donde la acción nunca se ejecutaba.
|
*/

/**
 * @return list<array{0: string, 1: string, 2: string}> [archivo, id del formulario, acción]
 */
function ui_modales_con_pie_en_formulario(): array
{
    $encontrados = [];

    foreach ((new Finder())->in(resource_path('views'))->files()->name('*.blade.php') as $archivo) {
        $texto = $archivo->getContents();

        preg_match_all('/<form\b[^>]*wire:submit="([^"]+)"[^>]*>/', $texto, $etiquetas, PREG_OFFSET_CAPTURE);

        foreach ($etiquetas[1] as $i => [$accion, $inicio]) {
            $cierre = strpos($texto, '</form>', $inicio);

            if ($cierre === false) {
                continue;
            }

            $dentro = substr($texto, $inicio, $cierre - $inicio);

            if (strpos($dentro, '<x-slot:footer>') === false) {
                continue;
            }

            preg_match('/\bid="([^"]+)"/', $etiquetas[0][$i][0], $id);

            $encontrados[] = [
                str_replace(resource_path('views') . '/', '', $archivo->getPathname()),
                $id[1] ?? '',
                $accion,
            ];
        }
    }

    return $encontrados;
}

test('todo modal que envuelve su pie en el formulario ofrece un botón de envío', function (): void {
    $violaciones = [];

    foreach (ui_modales_con_pie_en_formulario() as [$archivo, $id, $accion]) {
        if ($id === '') {
            $violaciones[] = sprintf('%s: <form wire:submit="%s"> sin id', $archivo, $accion);

            continue;
        }

        $texto = (string) file_get_contents(resource_path('views/' . $archivo));

        if (! preg_match('/<x-ui\.button\s+type="submit"\s+form="' . preg_quote($id, '/') . '"/', $texto)) {
            $violaciones[] = sprintf('%s: el pie de "%s" no tiene botón type="submit" form="%s"', $archivo, $accion, $id);
        }
    }

    // la revisión tiene que estar mirando los modales de verdad
    expect(count(ui_modales_con_pie_en_formulario()))->toBeGreaterThan(5);

    expect($violaciones)->toBe([]);
});

test('en el html renderizado todo botón de envío dispara un formulario', function (): void {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $html = Livewire::actingAs($admin)
        ->test(BecaAdmin::class)
        ->call('abrirModalBeca')
        ->html();

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $ids = [];
    foreach ($dom->getElementsByTagName('form') as $form) {
        if ($form->getAttribute('id') !== '') {
            $ids[] = $form->getAttribute('id');
        }
    }

    $sueltos = [];
    foreach ($dom->getElementsByTagName('button') as $boton) {
        if ($boton->getAttribute('type') !== 'submit') {
            continue;
        }

        $dentro = false;
        for ($nodo = $boton->parentNode; $nodo !== null; $nodo = $nodo->parentNode) {
            if ($nodo->nodeName === 'form') {
                $dentro = true;

                break;
            }
        }

        $asociado = $dentro || in_array($boton->getAttribute('form'), $ids, true);

        if (! $asociado) {
            $sueltos[] = trim(preg_replace('/\s+/', ' ', $boton->textContent));
        }
    }

    expect(count($ids))->toBeGreaterThan(0);

    expect($sueltos)->toBe([]);
});

/*
|--------------------------------------------------------------------------

| Regresión WAVE UI/UX 2.0-12 — validación nativa contra la de Livewire

|--------------------------------------------------------------------------

|

| Los campos numéricos llevan `min`/`max`/`step` (y hay `type="email"`), así

| que el navegador bloqueaba el `submit` con su propia burbuja antes de que

| Livewire validara:

|

|     <input type="number" max="100">  +  valor 150  →  0 peticiones, 0 errores

|

| No salía `p.ui-error` ni ninguna petición: el formulario no se enviaba nunca

| y la validación de la acción (`max:100`) era inalcanzable. Con `novalidate`

| manda la validación de Livewire y su mensaje con el estilo de la app.

|

*/

test('todo formulario Livewire deja que su validación sea la que manda', function (): void {
    $sinNovalidate = [];
    $escaneados = 0;

    foreach ((new Finder())->in(resource_path('views'))->files()->name('*.blade.php') as $archivo) {
        preg_match_all('/<form\b[^>]*wire:submit=[^>]*>/', $archivo->getContents(), $formularios);

        foreach ($formularios[0] as $etiqueta) {
            $escaneados++;

            if (! str_contains($etiqueta, 'novalidate')) {
                $sinNovalidate[] = str_replace(
                    resource_path('views') . '/',
                    '',
                    $archivo->getPathname()
                ) . ': ' . trim(preg_replace('/\\s+/', ' ', $etiqueta));
            }
        }
    }

    // la revisión tiene que cubrir todos los formularios, no uno de muestra
    expect($escaneados)->toBeGreaterThan(20);

    expect($sinNovalidate)->toBe([]);
});
