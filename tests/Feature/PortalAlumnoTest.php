<?php

use App\Enums\RolUsuario;
use App\Enums\TipoFormato;
use App\Livewire\Academico\BecaAdmin;
use App\Models\Alumno;
use App\Models\ConceptoPago;
use App\Models\DocumentoGenerado;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\Inscripcion;
use App\Models\InscripcionMateria;
use App\Models\Materia;
use App\Models\MatriculaPeriodo;
use App\Models\Periodo;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\Programa;
use App\Models\User;
use App\Services\EstadoCuentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class)->group('portal-alumno');

/**
 * Escenario con DOS alumnos aislados (A y B): cada cuenta ALUMNO sólo puede
 * ver su propio expediente. Devuelve también un usuario CONTROL_ESCOLAR para
 * contrastar la vista administrativa.
 *
 * @return array<string, mixed>
 */
function portal_alumno_escenario(): array
{
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $plan = PlanEstudio::factory()->paraPrograma($programa)->create();
    $version = PlanVersion::factory()->forPlanEstudio($plan)->vigente()->create();
    $materia = Materia::factory()->paraPlanVersion($version)->create(['activo' => true]);

    $grupoA = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create([
        'clave' => 'ALUM-A-1',
        'activo' => true,
    ]);
    $grupoB = Grupo::factory()->paraMateria($materia)->paraPeriodo($periodo)->abierto()->create([
        'clave' => 'ALUM-B-1',
        'activo' => true,
    ]);
    GrupoHorario::factory()->paraGrupo($grupoA)->create();
    GrupoHorario::factory()->paraGrupo($grupoB)->create();

    $userA = User::factory()->deRol(RolUsuario::ALUMNO)->create();
    $alumnoA = Alumno::factory()->create();
    $alumnoA->forceFill(['user_id' => $userA->id])->save();

    $userB = User::factory()->deRol(RolUsuario::ALUMNO)->create();
    $alumnoB = Alumno::factory()->create();
    $alumnoB->forceFill(['user_id' => $userB->id])->save();

    $matriculaA = MatriculaPeriodo::factory()->activa()
        ->paraAlumno($alumnoA)->paraPeriodo($periodo)->paraPrograma($programa)
        ->create(['plan_version_id' => $version->id]);
    $matriculaB = MatriculaPeriodo::factory()->activa()
        ->paraAlumno($alumnoB)->paraPeriodo($periodo)->paraPrograma($programa)
        ->create(['plan_version_id' => $version->id]);

    InscripcionMateria::factory()->cursando()
        ->paraMatricula($matriculaA)->paraGrupo($grupoA)
        ->create(['materia_id' => $materia->id]);
    InscripcionMateria::factory()->cursando()
        ->paraMatricula($matriculaB)->paraGrupo($grupoB)
        ->create(['materia_id' => $materia->id]);

    // Cargos distintos por alumno: la fórmula y el filtrado se ven en dinero.
    $inscripcionA = Inscripcion::factory()->create(['alumno_id' => $alumnoA->id]);
    $inscripcionB = Inscripcion::factory()->create(['alumno_id' => $alumnoB->id]);
    ConceptoPago::factory()->create([
        'inscripcion_id' => $inscripcionA->id,
        'monto_total' => '5000.00',
        'fecha_vencimiento' => '2026-09-10',
    ]);
    ConceptoPago::factory()->create([
        'inscripcion_id' => $inscripcionB->id,
        'monto_total' => '9999.00',
        'fecha_vencimiento' => '2026-09-10',
    ]);

    $controlEscolar = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    // Formatos emitidos a cada uno (folio único como marcador visible).
    $generadoA = DocumentoGenerado::factory()->create([
        'alumno_id' => $alumnoA->id,
        'tipo' => TipoFormato::KARDEX,
        'folio' => 'FOL-A-2026',
        'emitido_por' => $controlEscolar->id,
    ]);
    $generadoB = DocumentoGenerado::factory()->create([
        'alumno_id' => $alumnoB->id,
        'tipo' => TipoFormato::KARDEX,
        'folio' => 'FOL-B-2026',
        'emitido_por' => $controlEscolar->id,
    ]);

    return [
        'userA' => $userA,
        'alumnoA' => $alumnoA,
        'userB' => $userB,
        'alumnoB' => $alumnoB,
        'grupoA' => $grupoA,
        'grupoB' => $grupoB,
        'generadoA' => $generadoA,
        'generadoB' => $generadoB,
        'controlEscolar' => $controlEscolar,
    ];
}

test('capacidad 2.0-16: portal_alumno.acceder por rol', function (string $rol, bool $esperado) {
    $usuario = User::factory()->deRol(RolUsuario::from($rol))->create();

    expect(Gate::forUser($usuario)->allows('portal_alumno.acceder'))->toBe($esperado);
})->with([
    [RolUsuario::ALUMNO->value, true],
    [RolUsuario::ADMIN->value, true],
    [RolUsuario::DOCENTE->value, false],
    [RolUsuario::CONTROL_ESCOLAR->value, false],
    [RolUsuario::ADMISIONES->value, false],
    [RolUsuario::ADMINISTRACION->value, false],
]);

test('el portal del alumno abre para ALUMNO y cierra para el resto', function (string $rol, int $codigo) {
    $this->actingAs(User::factory()->deRol(RolUsuario::from($rol))->create());

    $this->get('/alumno')->assertStatus($codigo);
})->with([
    [RolUsuario::ALUMNO->value, 200],
    [RolUsuario::ADMIN->value, 200],
    [RolUsuario::DOCENTE->value, 403],
    [RolUsuario::CONTROL_ESCOLAR->value, 403],
    [RolUsuario::ADMISIONES->value, 403],
    [RolUsuario::ADMINISTRACION->value, 403],
]);

test('tras iniciar sesión el ALUMNO entra a su portal', function () {
    $alumno = User::factory()->deRol(RolUsuario::ALUMNO)->create();

    $this->post('/login', ['email' => $alumno->email, 'password' => 'password'])
        ->assertRedirect(route('alumno.inicio'));
});

test('el dashboard y las pantallas administrativas devuelven 403 al ALUMNO', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ALUMNO)->create());

    foreach ([
        '/dashboard',
        '/alumnos',
        '/inscripciones',
        '/historial',
        '/formatos-academicos',
        '/bajas-equivalencias/bajas',
        '/bajas-equivalencias/equivalencias',
        '/becas-descuentos/becas',
        '/becas-descuentos/estado-cuenta',
        '/servicio-social',
        '/practicas-profesionales',
        '/egreso',
        '/titulacion',
        '/admin/grupos',
        '/docente',
    ] as $ruta) {
        $this->get($ruta)->assertForbidden();
    }
});

test('el sidebar del ALUMNO sólo muestra su portal', function () {
    $html = $this->actingAs(User::factory()->deRol(RolUsuario::ALUMNO)->create())
        ->get(route('alumno.inicio'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('Mi portal')
        ->not->toContain('Mis grupos')
        ->not->toContain('Becas y descuentos')
        ->not->toContain('Inscripciones')
        ->not->toContain('Dashboard');
});

test('la identidad sale de la cuenta: A nunca ve el expediente de B', function () {
    $escenario = portal_alumno_escenario();

    $this->actingAs($escenario['userA']);

    // Inicio: sólo los datos del alumno de la cuenta.
    $this->get(route('alumno.inicio'))
        ->assertOk()
        ->assertSee($escenario['alumnoA']->curp)
        ->assertDontSee($escenario['alumnoB']->curp);

    // Kardex / horario: sólo los grupos del alumno de la cuenta.
    foreach (['alumno.kardex', 'alumno.horario'] as $ruta) {
        $this->get(route($ruta))
            ->assertOk()
            ->assertSee('ALUM-A-1')
            ->assertDontSee('ALUM-B-1');
    }

    // Estado de cuenta: sólo los cargos del alumno de la cuenta.
    $this->get(route('alumno.estado.cuenta'))
        ->assertOk()
        ->assertSee('$5,000.00')
        ->assertDontSee('$9,999.00');

    // Documentos: sólo los formatos emitidos al alumno de la cuenta.
    $this->get(route('alumno.documentos'))
        ->assertOk()
        ->assertSee('FOL-A-2026')
        ->assertDontSee('FOL-B-2026');
});

test('ni ids de ruta ni query params cambian el expediente mostrado', function () {
    $escenario = portal_alumno_escenario();

    $this->actingAs($escenario['userA']);

    // No existe ruta con {alumno}: el id no se puede inyectar.
    $this->get('/alumno/kardex/' . $escenario['alumnoB']->id)->assertNotFound();
    $this->get('/alumno?alumno=' . $escenario['alumnoB']->id)
        ->assertOk()
        ->assertSee($escenario['alumnoA']->curp)
        ->assertDontSee($escenario['alumnoB']->curp);

    // El payload de Livewire tampoco altera el alcance de la consulta.
    Livewire::actingAs($escenario['userA'])
        ->test(\App\Livewire\Alumnos\AlumnoDocumentos::class)
        ->assertSee('FOL-A-2026')
        ->assertDontSee('FOL-B-2026');
});

test('los documentos y formatos ajenos quedan fuera de alcance', function () {
    $escenario = portal_alumno_escenario();

    // Policy: cada alumno sólo ve lo suyo.
    expect(Gate::forUser($escenario['userA'])->allows('view', $escenario['generadoA']))->toBeTrue()
        ->and(Gate::forUser($escenario['userA'])->allows('view', $escenario['generadoB']))->toBeFalse();

    // Descarga por HTTP: el formato de B es 403 para A.
    $this->actingAs($escenario['userA'])
        ->get(route('documentos.generados.descargar', $escenario['generadoB']))
        ->assertForbidden();
});

test('el estado de cuenta del portal usa la misma fórmula que el backoffice', function () {
    $escenario = portal_alumno_escenario();

    $saldoServicio = app(EstadoCuentaService::class)->resumen($escenario['alumnoA'])['saldo'];

    $portal = $this->actingAs($escenario['userA'])
        ->get(route('alumno.estado.cuenta'))
        ->assertOk()
        ->getContent();

    $backoffice = Livewire::actingAs($escenario['controlEscolar'])
        ->test(BecaAdmin::class)
        ->set('vista', 'estado-cuenta')
        ->call('seleccionarAlumnoEstadoCuenta', (string) $escenario['alumnoA']->id)
        ->html();

    $patron = '/\$([\d,]+\.\d{2})\s*<\/p>\s*<p class="mt-1 text-xs text-brand-muted">Saldo exigible/';

    expect(preg_match($patron, $portal, $portalMatch))->toBe(1)
        ->and(preg_match($patron, $backoffice, $backofficeMatch))->toBe(1)
        ->and($portalMatch[1])->toBe($backofficeMatch[1])
        ->and($portalMatch[1])->toBe(number_format($saldoServicio, 2));
});

test('el portal muestra estado vacío sin expediente vinculado', function () {
    $sinExpediente = User::factory()->deRol(RolUsuario::ALUMNO)->create();

    $this->actingAs($sinExpediente)
        ->get(route('alumno.inicio'))
        ->assertOk()
        ->assertSee('Sin expediente vinculado');

    $this->actingAs($sinExpediente)
        ->get(route('alumno.kardex'))
        ->assertOk()
        ->assertSee('Sin expediente vinculado');
});
