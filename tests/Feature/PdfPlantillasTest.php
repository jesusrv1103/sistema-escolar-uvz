<?php

use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| §16/§17 — Salud estructural de las plantillas PDF
|--------------------------------------------------------------------------
|
| Auditoría pre-2.0-17 (BUG-04): 8 de 12 plantillas abrían un `<div class=
| "section">` que nunca se cerraba y 8 usaban clases de Bootstrap que el
| layout de PDF no define (`.table-success`, `.bg-warning`…), con lo que los
| estados de fila y las insignias salían sin color en el documento impreso.
|
| Estas pruebas impiden que la deuda vuelva: balance de etiquetas y clases
| usadas ⇒ definidas en el layout.
|
*/

function plantillasPdfPre2017(): array
{
    return File::glob(resource_path('views/pdf/formatos/*.blade.php'));
}

test('§16: toda plantilla PDF equilibra <div> y <table>', function () {
    $archivos = plantillasPdfPre2017();

    expect($archivos)->not->toBeEmpty();

    foreach ($archivos as $ruta) {
        $nombre = basename($ruta);
        $html = File::get($ruta);

        $divs = substr_count($html, '<div');
        $cierreDivs = substr_count($html, '</div>');
        $tablas = preg_match_all('/<table\b/', $html);
        $cierreTablas = preg_match_all('/<\/table>/', $html);

        $this->assertSame(
            $divs,
            $cierreDivs,
            "Desbalance de <div> en {$nombre}: {$divs} abiertos / {$cierreDivs} cerrados"
        );
        $this->assertSame(
            (int) $tablas,
            (int) $cierreTablas,
            "Desbalance de <table> en {$nombre}: {$tablas} abiertas / {$cierreTablas} cerradas"
        );
    }
});

test('§16: toda plantilla PDF cierra los bloques de Directivas de Blade', function () {
    foreach (plantillasPdfPre2017() as $ruta) {
        $nombre = basename($ruta);
        $html = File::get($ruta);

        $abren = preg_match_all('/@(if|foreach|forelse|php|section)\b/', $html);
        $cierran = preg_match_all('/@(endif|endforeach|endforelse|endphp|endsection)\b/', $html);

        $this->assertSame(
            (int) $abren,
            (int) $cierran,
            "Directivas sin cerrar en {$nombre}: {$abren} aperturas / {$cierran} cierres"
        );
    }
});

test('§16: las clases de estado que usan las plantillas están definidas en el layout', function () {
    $layout = File::get(resource_path('views/pdf/layouts/pdf.blade.php'));

    $clasesUsadas = [];

    foreach (plantillasPdfPre2017() as $ruta) {
        preg_match_all(
            '/class="[^"]*\b(bg-success|bg-danger|bg-warning|text-dark|table-success|table-danger|table-warning|badge-[a-z]+)\b/',
            File::get($ruta),
            $coincidencias
        );

        foreach ($coincidencias[1] as $clase) {
            $clasesUsadas[$clase][] = basename($ruta);
        }
    }

    expect($clasesUsadas)->not->toBeEmpty();

    foreach ($clasesUsadas as $clase => $archivos) {
        $this->assertMatchesRegularExpression(
            '/\.' . preg_quote($clase, '/') . '\b[^{}]*\{/',
            $layout,
            "La clase .{$clase} se usa en " . implode(', ', $archivos) . ' pero no está definida en pdf.layouts.pdf'
        );
    }
});

test('§16: las 12 plantillas extienden el layout y declaran su sección', function () {
    $archivos = plantillasPdfPre2017();

    expect($archivos)->toHaveCount(12);

    foreach ($archivos as $ruta) {
        $html = File::get($ruta);
        $nombre = basename($ruta);

        $this->assertStringContainsString("@extends('pdf.layouts.pdf')", $html, "{$nombre} no usa el layout de PDF");
        $this->assertStringContainsString("@section('content')", $html, "{$nombre} no declara content");
        $this->assertStringContainsString('@endsection', $html, "{$nombre} no cierra content");
        // El layout ya imprime encabezado, pie y fecha: no se repiten aquí.
        $this->assertDoesNotMatchRegularExpression('/<footer|class="footer"/', $html, "{$nombre} redefine el pie");
    }
});
