<?php

use App\Enums\RolUsuario;
use App\Livewire\Catalogos\Convenios;
use App\Livewire\Catalogos\Periodos;
use App\Livewire\Catalogos\Programas;
use App\Livewire\Catalogos\TiposDocumento;
use App\Models\Convenio;
use App\Models\Periodo;
use App\Models\Programa;
use App\Models\TipoDocumento;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\TiposDocumentoSeeder;
use Livewire\Livewire;

test('ADMIN puede gestionar el catalogo de programas', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(Programas::class)
        ->call('crear')
        ->set('clave', 'DER')
        ->set('nombre', 'Licenciatura en Derecho')
        ->set('duracion_periodos', 8)
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Programa::where('clave', 'DER')->count())->toBe(1);

    $programa = Programa::where('clave', 'DER')->first();

    Livewire::test(Programas::class)
        ->call('editar', $programa->id)
        ->set('duracion_periodos', 9)
        ->call('guardar')
        ->assertHasNoErrors();

    expect($programa->refresh()->duracion_periodos)->toBe(9);

    Livewire::test(Programas::class)->call('alternarActivo', $programa->id);
    expect($programa->refresh()->activo)->toBeFalse();

    Livewire::test(Programas::class)->call('eliminar', $programa->id);
    $programa->refresh();
    expect($programa->trashed())->toBeTrue();
});

test('ADMIN puede gestionar periodos y cerrarlos', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(Periodos::class)
        ->call('crear')
        ->set('clave', '2026-1')
        ->set('nombre', 'Enero - Junio 2026')
        ->set('fecha_inicio', '2026-01-05')
        ->set('fecha_fin', '2026-06-26')
        ->call('guardar')
        ->assertHasNoErrors();

    $periodo = Periodo::where('clave', '2026-1')->firstOrFail();
    expect($periodo->cerrado_at)->toBeNull();

    Livewire::test(Periodos::class)->call('cerrar', $periodo->id);
    expect($periodo->refresh()->cerrado_at)->not->toBeNull();

    Livewire::test(Periodos::class)->call('reabrir', $periodo->id);
    expect($periodo->refresh()->cerrado_at)->toBeNull();

    Livewire::test(Periodos::class)->call('eliminar', $periodo->id);
    expect($periodo->refresh()->trashed())->toBeTrue();
});

test('ADMIN puede gestionar el catalogo de tipos de documento', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(TiposDocumento::class)
        ->call('crear')
        ->set('clave', 'CURP')
        ->set('nombre', 'CURP')
        ->set('obligatorio', true)
        ->call('guardar')
        ->assertHasNoErrors();

    $tipo = TipoDocumento::where('clave', 'CURP')->firstOrFail();

    Livewire::test(TiposDocumento::class)
        ->call('editar', $tipo->id)
        ->set('obligatorio', false)
        ->call('guardar')
        ->assertHasNoErrors();

    expect($tipo->refresh()->obligatorio)->toBeFalse();

    Livewire::test(TiposDocumento::class)->call('eliminar', $tipo->id);
    expect($tipo->refresh()->trashed())->toBeTrue();
});

test('ADMIN puede gestionar el catalogo de convenios', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(Convenios::class)
        ->call('crear')
        ->set('clave', 'BECA10')
        ->set('nombre', 'Beca 10%')
        ->set('tipo', 'PORCENTAJE')
        ->set('valor', '10')
        ->call('guardar')
        ->assertHasNoErrors();

    $convenio = Convenio::where('clave', 'BECA10')->firstOrFail();
    expect($convenio->valor)->toBe('10.00');

    Livewire::test(Convenios::class)->call('alternarActivo', $convenio->id);
    expect($convenio->refresh()->activo)->toBeFalse();

    Livewire::test(Convenios::class)->call('eliminar', $convenio->id);
    expect($convenio->refresh()->trashed())->toBeTrue();
});

test('los catalogos rechazan a roles que no son ADMIN', function () {
    $rutas = ['/admin/programas', '/admin/periodos', '/admin/tipos-documento', '/admin/convenios'];

    foreach ($rutas as $ruta) {
        $this->get($ruta)->assertRedirect('/login');
    }

    foreach ([RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR] as $rol) {
        foreach ($rutas as $ruta) {
            $this->actingAs(User::factory()->deRol($rol)->create())->get($ruta)->assertForbidden();
        }
    }
});

/**
 * Código HTTP de la última petición de Livewire: una denegación de Gate se
 * devuelve como respuesta 403 (no como excepción) dentro del ciclo de pruebas.
 */
function estadoDePeticion(object $testable): ?int
{
    $propiedad = new ReflectionProperty($testable, 'lastState');
    $propiedad->setAccessible(true);
    $estado = $propiedad->getValue($testable);

    return $estado->getResponse()?->getStatusCode();
}

test('el componente de catalogo exige ADMIN aunque se invoque directamente', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());

    $componente = Livewire::test(Programas::class)
        ->set('clave', 'HAC')
        ->set('nombre', 'Intento sin autorizacion')
        ->call('guardar');

    expect(estadoDePeticion($componente))->toBe(403)
        ->and(Programa::count())->toBe(0);
});

test('rechaza claves duplicadas en programas', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    Programa::create(['clave' => 'DER', 'nombre' => 'Licenciatura en Derecho']);

    Livewire::test(Programas::class)
        ->call('crear')
        ->set('clave', 'DER')
        ->set('nombre', 'Otra licenciatura')
        ->call('guardar')
        ->assertHasErrors(['clave']);

    expect(Programa::count())->toBe(1);
});

test('rechaza un periodo con fechas incoherentes', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(Periodos::class)
        ->call('crear')
        ->set('clave', '2026-2')
        ->set('nombre', 'Agosto - Diciembre 2026')
        ->set('fecha_inicio', '2026-08-03')
        ->set('fecha_fin', '2026-07-01')
        ->call('guardar')
        ->assertHasErrors(['fecha_fin']);

    Livewire::test(Periodos::class)
        ->call('crear')
        ->set('clave', '2026-2')
        ->set('nombre', 'Agosto - Diciembre 2026')
        ->set('fecha_inicio', '')
        ->set('fecha_fin', '2026-12-18')
        ->call('guardar')
        ->assertHasErrors(['fecha_inicio']);

    expect(Periodo::count())->toBe(0);
});

test('rechaza convenios con valores fuera de rango', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(Convenios::class)
        ->call('crear')
        ->set('clave', 'P150')
        ->set('nombre', 'Porcentaje imposible')
        ->set('tipo', 'PORCENTAJE')
        ->set('valor', '150')
        ->call('guardar')
        ->assertHasErrors(['valor']);

    Livewire::test(Convenios::class)
        ->call('crear')
        ->set('clave', 'M0')
        ->set('nombre', 'Monto cero')
        ->set('tipo', 'MONTO')
        ->set('valor', '0')
        ->call('guardar')
        ->assertHasErrors(['valor']);

    expect(Convenio::count())->toBe(0);
});

test('el soft delete oculta los registros de los listados', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $programa = Programa::create(['clave' => 'TMP', 'nombre' => 'Temporal']);

    Livewire::test(Programas::class)->call('eliminar', $programa->id)->assertDontSee('Temporal');

    expect(Programa::count())->toBe(0)
        ->and(Programa::withTrashed()->count())->toBe(1)
        ->and($programa->refresh()->trashed())->toBeTrue();
});

test('los seeders de catalogos son idempotentes', function () {
    $this->seed(CatalogosSeeder::class);
    $this->seed(CatalogosSeeder::class);
    $this->seed(TiposDocumentoSeeder::class);
    $this->seed(TiposDocumentoSeeder::class);

    expect(Programa::count())->toBe(3)
        ->and(TipoDocumento::count())->toBe(1);
});

test('el seed carga los tres programas UVZ con claves internas', function () {
    $this->seed(CatalogosSeeder::class);

    $esperados = [
        'DER' => 'Licenciatura en Derecho',
        'SAI' => 'Licenciatura en Seguridad y Administración Industrial',
        'CF' => 'Licenciatura en Ciencia Forense',
    ];

    foreach ($esperados as $clave => $nombre) {
        $programa = Programa::where('clave', $clave)->first();
        expect($programa)->not->toBeNull()
            ->and($programa->nombre)->toBe($nombre)
            ->and($programa->duracion_periodos)->toBeNull()  // sin datos inventados
            ->and($programa->activo)->toBeTrue();
    }

    expect(Programa::count())->toBe(3);
});
