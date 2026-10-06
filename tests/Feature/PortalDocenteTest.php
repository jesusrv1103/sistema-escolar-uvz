<?php

use App\Enums\RolUsuario;
use App\Models\Docente;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Periodo;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class)->group('portal-docente');

/**
 * Crea un usuario DOCENTE con perfil de docente y dos grupos: uno propio y
 * otro asignado a un docente distinto.
 *
 * @return array{docente: User, perfil: Docente, propio: Grupo, ajeno: Grupo, ajenoUser: User}
 */
function portal_docente_escenario(): array
{
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $plan = PlanEstudio::factory()->paraPrograma($programa)->create();
    $version = PlanVersion::factory()->forPlanEstudio($plan)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($version)->create(['activo' => true]);

    $docenteUser = User::factory()->deRol(RolUsuario::DOCENTE)->create();
    $perfil = $docenteUser->docenteOrCreate();

    $otroUser = User::factory()->deRol(RolUsuario::DOCENTE)->create();
    $otroPerfil = $otroUser->docenteOrCreate();

    $propio = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create([
        'docente_id' => $perfil->id,
        'clave' => 'PROPIO-1',
        'activo' => true,
    ]);

    $ajeno = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create([
        'docente_id' => $otroPerfil->id,
        'clave' => 'AJENO-1',
        'activo' => true,
    ]);

    return [
        'docente' => $docenteUser,
        'perfil' => $perfil,
        'propio' => $propio,
        'ajeno' => $ajeno,
        'ajenoUser' => $otroUser,
    ];
}

dataset('capacidades_portal_docente', [
    ['portal_docente.acceder', RolUsuario::DOCENTE->value, true],
    ['portal_docente.acceder', RolUsuario::CONTROL_ESCOLAR->value, false],
    ['portal_docente.acceder', RolUsuario::ADMISIONES->value, false],
    ['portal_docente.acceder', RolUsuario::ADMINISTRACION->value, false],
    ['portal_docente.acceder', RolUsuario::ADMIN->value, true],

    ['docente.grupo.ver', RolUsuario::DOCENTE->value, true],
    ['docente.grupo.ver', RolUsuario::CONTROL_ESCOLAR->value, false],
    ['docente.grupo.ver', RolUsuario::ADMINISTRACION->value, false],

    ['alumno.ver', RolUsuario::ADMISIONES->value, true],
    ['alumno.ver', RolUsuario::ADMINISTRACION->value, true],
    ['alumno.ver', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['alumno.ver', RolUsuario::DOCENTE->value, false],
    ['alumno.ver', RolUsuario::ADMIN->value, true],

    ['inscripcion.ver', RolUsuario::ADMISIONES->value, true],
    ['inscripcion.ver', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['inscripcion.ver', RolUsuario::DOCENTE->value, false],

    ['pago.ver', RolUsuario::ADMINISTRACION->value, true],
    ['pago.ver', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['pago.ver', RolUsuario::DOCENTE->value, false],
    ['pago.ver', RolUsuario::ADMISIONES->value, true],

    ['documento.ver', RolUsuario::ADMISIONES->value, true],
    ['documento.ver', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['documento.ver', RolUsuario::DOCENTE->value, false],
]);

test('capacidad 2.0-15: {capacidad} para {rol} → {esperado}', function (string $capacidad, string $rol, bool $esperado) {
    $usuario = User::factory()->deRol(RolUsuario::from($rol))->create();

    expect(Gate::forUser($usuario)->allows($capacidad))->toBe($esperado);
})->with('capacidades_portal_docente');

test('el portal del docente abre para DOCENTE y cierra para el resto', function (string $rol, int $codigo) {
    $this->actingAs(User::factory()->deRol(RolUsuario::from($rol))->create());

    $this->get('/docente')->assertStatus($codigo);
})->with([
    [RolUsuario::DOCENTE->value, 200],
    [RolUsuario::ADMIN->value, 200],
    [RolUsuario::CONTROL_ESCOLAR->value, 403],
    [RolUsuario::ADMISIONES->value, 403],
    [RolUsuario::ADMINISTRACION->value, 403],
]);

test('tras iniciar sesión el DOCENTE entra a su portal', function () {
    $docente = User::factory()->deRol(RolUsuario::DOCENTE)->create();

    $this->post('/login', ['email' => $docente->email, 'password' => 'password'])
        ->assertRedirect(route('docente.inicio'));

    $otro = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    $this->post('/logout');
    $this->post('/login', ['email' => $otro->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});

test('el dashboard y las pantallas administrativas devuelven 403 al DOCENTE', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::DOCENTE)->create());

    foreach ([
        '/dashboard',
        '/alumnos',
        '/inscripciones',
        '/historial',
        '/formatos-academicos',
        '/bajas-equivalencias/bajas',
        '/bajas-equivalencias/equivalencias',
        '/becas-descuentos/becas',
        '/servicio-social',
        '/practicas-profesionales',
        '/egreso',
        '/titulacion',
        '/admin/grupos',
        '/admin/calificaciones',
    ] as $ruta) {
        $this->get($ruta)->assertForbidden();
    }
});

test('el sidebar del DOCENTE sólo muestra su portal y oculta los menús admin', function () {
    $html = $this->actingAs(User::factory()->deRol(RolUsuario::DOCENTE)->create())
        ->get(route('docente.inicio'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('Mis grupos')
        ->not->toContain('Becas y descuentos')
        ->not->toContain('Titulación')
        ->not->toContain('Inscripciones')
        ->not->toContain('Dashboard');
});

test('los demás roles no ven el grupo Docente del sidebar', function () {
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();

    $html = $this->actingAs($administracion)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain('Mis grupos');
});

test('el docente ve su grupo y recibe 403 en el de otro docente', function () {
    $escenario = portal_docente_escenario();
    $this->actingAs($escenario['docente']);

    $this->get(route('docente.grupos.ver', $escenario['propio']))->assertOk();
    $this->get(route('docente.grupos.ver', $escenario['ajeno']))->assertForbidden();
});

test('las pantallas de captura abren sólo con un grupo propio', function () {
    $escenario = portal_docente_escenario();
    $this->actingAs($escenario['docente']);

    foreach (['evaluaciones', 'calificaciones', 'asistencia', 'actas'] as $pantalla) {
        $ruta = 'docente.grupos.' . $pantalla;

        $this->get(route($ruta, $escenario['propio']))->assertOk();
        $this->get(route($ruta, $escenario['ajeno']))->assertForbidden();
    }
});

test('la lista de grupos de un docente nunca muestra grupos ajenos', function () {
    $escenario = portal_docente_escenario();
    $this->actingAs($escenario['docente']);

    $html = $this->get(route('docente.grupos.calificaciones', $escenario['propio']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('PROPIO-1')
        ->not->toContain('AJENO-1');
});

test('el docente no puede cambiar la selección a un grupo ajeno', function () {
    $escenario = portal_docente_escenario();
    $this->actingAs($escenario['docente']);

    Livewire::actingAs($escenario['docente'])
        ->test(\App\Livewire\Academico\CalificacionAdmin::class, ['grupo' => $escenario['propio']])
        ->call('seleccionarGrupo', (string) $escenario['ajeno']->id)
        ->assertForbidden();

    Livewire::actingAs($escenario['docente'])
        ->test(\App\Livewire\Academico\AsistenciaAdmin::class, ['grupo' => $escenario['propio']])
        ->call('seleccionarGrupo', (string) $escenario['ajeno']->id)
        ->assertForbidden();
});

test('el servicio de actas rechaza a un docente que no es dueño del grupo', function () {
    $escenario = portal_docente_escenario();
    $this->actingAs($escenario['docente']);

    expect(fn () => app(\App\Services\ActaService::class)->crear(
        $escenario['ajeno'],
        $escenario['docente'],
    ))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('el portal muestra estado vacío sin perfil de docente y sin grupos', function () {
    $sinPerfil = User::factory()->deRol(RolUsuario::DOCENTE)->create();

    $this->actingAs($sinPerfil)
        ->get(route('docente.inicio'))
        ->assertOk()
        ->assertSee('Sin perfil de docente vinculado');

    $escenario = portal_docente_escenario();
    $escenario['propio']->update(['activo' => false]);

    $this->actingAs($escenario['docente'])
        ->get(route('docente.inicio'))
        ->assertOk()
        ->assertSee('Sin grupos asignados');
});
