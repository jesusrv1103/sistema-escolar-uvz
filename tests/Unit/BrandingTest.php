<?php

test('branding centraliza la identidad institucional UVZ', function () {
    expect(config('branding.name'))->toBe('Universidad Villa de Zacatecas')
        ->and(config('branding.short_name'))->toBe('UVZ')
        ->and(config('branding.slogan'))->toBe('Aumentamos la excelencia')
        ->and(config('branding.website'))->toBe('https://universidadvilladezacatecas.edu.mx/')
        ->and(config('branding.phone'))->not->toBeEmpty()
        ->and(config('branding.email'))->toContain('universidadvilladezacatecas.edu.mx')
        ->and(config('branding.address'))->not->toBeEmpty();
});

test('branding define logo, favicon y colores', function () {
    foreach (['logo', 'logo_dark', 'favicon'] as $clave) {
        expect(config("branding.$clave"))->not->toBeEmpty()
            ->and(public_path(config("branding.$clave")))->toBeFile();
    }

    foreach (['primary_color', 'secondary_color', 'accent_color'] as $clave) {
        expect(config("branding.$clave"))->toMatch('/^#[0-9a-fA-F]{6}$/');
    }
});

test('el login muestra la identidad institucional', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee(config('branding.name'))
        ->assertSee(config('branding.system_name'))
        ->assertSee(config('branding.slogan'));
});

test('el layout muestra la marca y el nombre corto UVZ', function () {
    $usuario = App\Models\User::factory()->create();

    $this->actingAs($usuario)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(config('branding.name'))
        ->assertSee(config('branding.short_name'));
});
