<?php

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fase 2 — Seguridad y autorización base (F10 y matriz §8.2)
|--------------------------------------------------------------------------
| Todas las pruebas corren sobre `sistema_escolar_testing` (RefreshDatabase).
*/

test('F10: ADMIN pasa todas las capacidades globales', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $capacidades = [
        'usuario.manage', 'alumno.create', 'alumno.update', 'alumno.delete',
        'informacionMedica.view', 'informacionMedica.edit',
        'inscripcion.create', 'inscripcion.update', 'inscripcion.generarCargos',
        'inscripcion.finalizar', 'inscripcion.cancelar',
        'convenio.aplicar', 'convenio.aprobar',
        'pago.registrar', 'pago.validar',
        'documento.cargar', 'documento.validar',
        'observacion.crear', 'observacion.resolver', 'auditoria.view',
    ];

    foreach ($capacidades as $capacidad) {
        expect(Gate::forUser($admin)->allows($capacidad))->toBeTrue("fallo: {$capacidad}");
    }
});

test('las capacidades siguen la matriz de roles de la arquitectura', function () {
    $esperado = [
        RolUsuario::ADMISIONES->value => ['alumno.create', 'informacionMedica.view', 'inscripcion.create', 'convenio.aplicar', 'pago.registrar', 'documento.cargar'],
        RolUsuario::ADMINISTRACION->value => ['convenio.aprobar', 'pago.validar', 'pago.registrar', 'documento.cargar'],
        RolUsuario::CONTROL_ESCOLAR->value => ['informacionMedica.view', 'inscripcion.finalizar', 'documento.validar', 'documento.cargar'],
    ];
    $prohibido = [
        RolUsuario::ADMISIONES->value => ['usuario.manage', 'pago.validar', 'convenio.aprobar', 'inscripcion.finalizar'],
        RolUsuario::ADMINISTRACION->value => ['informacionMedica.view', 'informacionMedica.edit', 'alumno.create'],
        RolUsuario::CONTROL_ESCOLAR->value => ['pago.validar', 'pago.registrar', 'inscripcion.create'],
    ];

    foreach ($esperado as $rol => $capacidades) {
        $usuario = User::factory()->deRol(RolUsuario::from($rol))->create();
        foreach ($capacidades as $capacidad) {
            expect(Gate::forUser($usuario)->allows($capacidad))->toBeTrue("{$rol} debería: {$capacidad}");
        }
        foreach ($prohibido[$rol] as $capacidad) {
            expect(Gate::forUser($usuario)->allows($capacidad))->toBeFalse("{$rol} no debería: {$capacidad}");
        }
    }
});

test('la regla de informacion medica queda registrada desde la Fase 2', function () {
    expect(Gate::forUser(User::factory()->deRol(RolUsuario::ADMISIONES)->create())->allows('informacionMedica.view'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create())->allows('informacionMedica.view'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->deRol(RolUsuario::ADMINISTRACION)->create())->allows('informacionMedica.view'))->toBeFalse()
        ->and(Gate::forUser(User::factory()->deRol(RolUsuario::ADMINISTRACION)->create())->allows('informacionMedica.edit'))->toBeFalse();
});

test('un usuario inactivo no pasa ninguna capacidad', function () {
    $inactivo = User::factory()->deRol(RolUsuario::ADMIN)->inactivo()->create();

    expect(Gate::forUser($inactivo)->allows('usuario.manage'))->toBeFalse()
        ->and(Gate::forUser($inactivo)->allows('pago.validar'))->toBeFalse();
});

test('el middleware role permite al rol requerido y rechaza a los demas', function () {
    Route::middleware('role:ADMISIONES')->get('/prueba-role', fn () => 'ok')->name('prueba.role');

    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create())
        ->get('/prueba-role')->assertOk()->assertSee('ok');

    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create())
        ->get('/prueba-role')->assertForbidden();

    $this->actingAs(User::factory()->deRol(RolUsuario::ADMINISTRACION)->create())
        ->get('/prueba-role')->assertForbidden();
});

test('el middleware role deja pasar a ADMIN y rechaza a invitados e inactivos', function () {
    Route::middleware('role:ADMISIONES')->get('/prueba-role-admin', fn () => 'ok');

    $this->get('/prueba-role-admin')->assertRedirect('/login');

    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->inactivo()->create())
        ->get('/prueba-role-admin')->assertForbidden();

    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create())
        ->get('/prueba-role-admin')->assertOk();
});

test('el alias role esta registrado en bootstrap', function () {
    $aliases = app('router')->getMiddleware();

    expect($aliases)->toHaveKey('role')
        ->and($aliases['role'])->toBe(App\Http\Middleware\EnsureUserHasRole::class);
});

test('AdminSeeder crea el ADMIN de desarrollo de forma idempotente', function () {
    $email = env('ADMIN_EMAIL', 'admin@uvz.local');

    $this->seed(Database\Seeders\AdminSeeder::class);
    $this->seed(Database\Seeders\AdminSeeder::class);

    expect(User::withTrashed()->where('email', $email)->count())->toBe(1);

    $admin = User::where('email', $email)->first();
    expect($admin)->not->toBeNull()
        ->and($admin->rol)->toBe(RolUsuario::ADMIN)
        ->and($admin->activo)->toBeTrue()
        ->and($admin->esAdmin())->toBeTrue();
});
