<?php

/*
|--------------------------------------------------------------------------
| Identidad institucional — Universidad Villa de Zacatecas (UVZ)
|--------------------------------------------------------------------------
|
| Única fuente de verdad del branding del sistema. Las vistas consumen
| config('branding.*'); los colores se inyectan como variables CSS
| (--brand-*) desde resources/views/layouts/app.blade.php y de ahí los
| tokens de Tailwind 4 en resources/css/app.css.
|
| Origen de los valores:
| - Textos, teléfono, correo y domicilio: sitio oficial UVZ (sección Contacto).
| - Colores: variables :root de styles.css del sitio oficial
|   (https://universidadvilladezacatecas.edu.mx/styles.css):
|   --wine #72132e, --wine-dark #4f0a1e, --wine-bright #9b2244,
|   --paper #fbfaf7, --cream #f4f0e9, --ink #171517, --muted #696369,
|   --line #ddd6ce; acento dorado #e6ad34 (outline de foco del sitio).
| - Logos y favicon: descargados del sitio oficial y almacenados en
|   public/images (sin hotlinking).
|
*/

return [

    'name' => 'Universidad Villa de Zacatecas',
    'short_name' => 'UVZ',
    'system_name' => 'Sistema Escolar',
    'slogan' => 'Aumentamos la excelencia',
    'hashtag' => '#AumentamosLaExcelencia',
    'website' => 'https://universidadvilladezacatecas.edu.mx/',

    // Assets locales (descargados del sitio institucional)
    'logo' => 'images/logo-uvz-horizontal.png',
    'logo_vertical' => 'images/logo-uvz-vertical.png',
    // PENDIENTE: el sitio institucional no publica una variante del logo
    // para fondos oscuros; se reutiliza el logo horizontal mientras tanto.
    'logo_dark' => 'images/logo-uvz-horizontal.png',
    'favicon' => 'images/favicon.png',

    // Colores institucionales (ver origen arriba; no inventados)
    'primary_color' => '#72132e',
    'secondary_color' => '#4f0a1e',
    'highlight_color' => '#9b2244',
    'accent_color' => '#e6ad34',
    'background_color' => '#fbfaf7',
    'surface_color' => '#f4f0e9',
    'text_color' => '#171517',
    'muted_color' => '#696369',
    'border_color' => '#ddd6ce',

    'phone' => '492 161 0444',
    'email' => 'admisiones@universidadvilladezacatecas.edu.mx',
    'address' => 'Av. Pedro Coronel #38, Cañada de la Bufa',

];
