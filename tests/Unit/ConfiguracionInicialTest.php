<?php

test('la aplicacion esta configurada en es_MX y America/Mexico_City', function () {
    expect(config('app.locale'))->toBe('es_MX')
        ->and(config('app.faker_locale'))->toBe('es_MX')
        ->and(config('app.timezone'))->toBe('America/Mexico_City');
});

test('el disco por defecto es el privado y no sirve archivos por URL', function () {
    expect(config('filesystems.default'))->toBe('private')
        ->and(config('filesystems.disks.private.serve'))->toBeFalse()
        ->and(config('filesystems.disks.private.root'))->toContain('storage')
        ->and(config('filesystems.disks.private.root'))->not->toContain('public');
});

test('las pruebas usan la base de datos exclusiva de testing', function () {
    expect(config('database.default'))->toBe('mysql')
        ->and(config('database.connections.mysql.database'))->toBe('sistema_escolar_testing');
});
