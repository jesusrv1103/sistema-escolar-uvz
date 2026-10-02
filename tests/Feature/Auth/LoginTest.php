<?php

use App\Enums\RolUsuario;
use App\Models\User;

function usuarioPrueba(array $atributos = []): User
{
    return User::factory()->create($atributos);
}

test('muestra el formulario de login', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Correo electrónico')
        ->assertSee('Contraseña');
});

test('el visitante no autenticado es redirigido al login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/')->assertRedirect('/dashboard');
});

test('un usuario inicia sesion con credenciales validas', function () {
    $usuario = usuarioPrueba(['password' => 'Password-forte-2026']);

    $respuesta = $this->post('/login', [
        'email' => $usuario->email,
        'password' => 'Password-forte-2026',
    ]);

    $respuesta->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($usuario);
});

test('no inicia sesion con password incorrecta', function () {
    $usuario = usuarioPrueba(['password' => 'Password-forte-2026']);

    $respuesta = $this->from('/login')->post('/login', [
        'email' => $usuario->email,
        'password' => 'password-equivocada',
    ]);

    $respuesta->assertRedirect('/login');
    $this->assertGuest();
    $respuesta->assertSessionHasErrors('email');
});

test('no inicia sesion con correo inexistente', function () {
    $this->post('/login', [
        'email' => 'no-existe@ejemplo.com',
        'password' => 'cualquier-cosa',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('el logout cierra la sesion y limpia el token', function () {
    $usuario = usuarioPrueba(['password' => 'Password-forte-2026']);

    $this->actingAs($usuario)
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});

test('el login aplica rate limiting tras intentos fallidos', function () {
    $usuario = usuarioPrueba(['password' => 'Password-forte-2026']);

    foreach (range(1, 5) as $intento) {
        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'password-mala-'.$intento,
        ])->assertSessionHasErrors('email');
    }

    $this->post('/login', [
        'email' => $usuario->email,
        'password' => 'Password-forte-2026',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('la sesion se regenera tras iniciar sesion', function () {
    $usuario = usuarioPrueba(['password' => 'Password-forte-2026']);

    $this->get('/login');
    $claveAntes = $this->app->make('session')->getId();

    $this->post('/login', [
        'email' => $usuario->email,
        'password' => 'Password-forte-2026',
    ])->assertRedirect('/dashboard');

    expect($claveAntes)->not->toBeEmpty()
        ->and($this->app->make('session')->getId())->not->toBe($claveAntes);
});

test('un usuario inactivo no puede iniciar sesion', function () {
    $usuario = usuarioPrueba(['password' => 'Password-forte-2026', 'activo' => false]);

    $this->post('/login', [
        'email' => $usuario->email,
        'password' => 'Password-forte-2026',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('un login exitoso actualiza last_login_at', function () {
    $usuario = usuarioPrueba(['password' => 'Password-forte-2026']);

    expect($usuario->last_login_at)->toBeNull();

    $this->post('/login', [
        'email' => $usuario->email,
        'password' => 'Password-forte-2026',
    ])->assertRedirect('/dashboard');

    expect($usuario->refresh()->last_login_at)->not->toBeNull();
});

test('el rol se castea al enum RolUsuario', function () {
    $usuario = usuarioPrueba(['rol' => RolUsuario::CONTROL_ESCOLAR]);

    expect($usuario->rol)->toBeInstanceOf(RolUsuario::class)
        ->and($usuario->rol)->toBe(RolUsuario::CONTROL_ESCOLAR)
        ->and($usuario->fresh()->rol)->toBe(RolUsuario::CONTROL_ESCOLAR)
        ->and($usuario->esAdmin())->toBeFalse();

    $admin = usuarioPrueba(['rol' => RolUsuario::ADMIN]);
    expect($admin->esAdmin())->toBeTrue();
});
