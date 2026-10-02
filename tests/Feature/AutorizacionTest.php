<?php

use App\Enums\EstatusConceptoPago;
use App\Enums\EstatusConvenio;
use App\Enums\EstatusDocumento;
use App\Enums\EstatusPago;
use App\Enums\InscripcionEstatus;
use App\Enums\MetodoPago;
use App\Enums\RolUsuario;
use App\Enums\TipoConcepto;
use App\Enums\TipoContacto;
use App\Enums\TipoDocumento;
use App\Enums\TipoObservacion;
use App\Models\Alumno;
use App\Models\ConceptoPago;
use App\Models\Convenio;
use App\Models\Documento;
use App\Models\Inscripcion;
use App\Models\Observacion;
use App\Models\Pago;
use App\Models\Periodo;
use App\Models\Programa;
use App\Models\TipoDocumento as ModeloTipoDocumento;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Fase 2 — Seguridad y autorización base (F10 y matriz §8.2)
|--------------------------------------------------------------------------
| Todas las pruebas corren sobre `sistema_escolar_testing` (RefreshDatabase).
|
| F6–F9 — Pruebas de integración HTTP/Livewire de la matriz de autorización
| para los 16 flujos críticos. Estas pruebas llaman a las acciones reales
| (no solo gates) y verifican 403/200 según la matriz §8.2.
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

/**
 * Helper: crea un alumno completo con contactos e información médica.
 */
function crearAlumnoCompleto(): Alumno
{
    $user = User::factory()->create();
    $alumno = Alumno::factory()->create([
        'curp' => 'ZZZZ' . fake()->unique()->numerify('##########'), // 4 + 10 = 14, pero CURP es 18 chars
        'creado_por' => $user->id,
    ]);
    // Corregir CURP a 18 chars exactos
    $alumno->update(['curp' => 'ZZZZ' . fake()->unique()->numerify('############')]); // 4 + 14 = 18
    
    $alumno->contactos()->create([
        'tipo' => TipoContacto::TUTOR->value,
        'nombre' => fake()->name(),
        'parentesco' => 'Padre',
        'telefono' => fake()->phoneNumber(),
    ]);
    $alumno->contactos()->create([
        'tipo' => TipoContacto::EMERGENCIA->value,
        'nombre' => fake()->name(),
        'parentesco' => 'Madre',
        'telefono' => fake()->phoneNumber(),
    ]);
    $alumno->informacionMedica()->create([
        'tipo_sangre' => 'O+',
        'tiene_discapacidad' => false,
        'actualizado_por' => $user->id,
    ]);
    return $alumno;
}

/**
 * Helper: crea una inscripción en el estado indicado con todos los requisitos.
 */
function crearInscripcionConRequisitos(InscripcionEstatus $estatus, ?Alumno $alumno = null, ?Periodo $periodo = null, ?Programa $programa = null): Inscripcion
{
    $alumno ??= crearAlumnoCompleto();
    $periodo ??= Periodo::factory()->create(['activo' => true, 'cerrado_at' => null]);
    $programa ??= Programa::factory()->create(['activo' => true]);

    $inscripcion = Inscripcion::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_id' => $periodo->id,
        'programa_id' => $programa->id,
        'estatus' => $estatus->value,
        'monto_inscripcion' => 5000,
        'monto_mensualidad' => 2000,
    ]);

    // Si el estatus requiere cargos generados, créalos
    if (in_array($estatus, [
        InscripcionEstatus::PENDING_PAYMENT,
        InscripcionEstatus::PAYMENT_REVIEW,
        InscripcionEstatus::PAYMENT_VALIDATED,
        InscripcionEstatus::PENDING_DOCUMENTS,
        InscripcionEstatus::DOCUMENT_REVIEW,
        InscripcionEstatus::FINAL_REVIEW,
        InscripcionEstatus::ENROLLED,
    ], true)) {
        $conceptoInscripcion = $inscripcion->conceptosPago()->create([
            'tipo' => TipoConcepto::INSCRIPCION->value,
            'concepto' => 'Inscripción',
            'monto' => 5000,
            'descuento' => 0,
            'monto_total' => 5000,
            'fecha_vencimiento' => $periodo->fecha_inicio,
            'estatus' => EstatusConceptoPago::PENDIENTE->value,
        ]);
        for ($i = 1; $i <= 4; $i++) {
            $inscripcion->conceptosPago()->create([
                'tipo' => TipoConcepto::MENSUALIDAD->value,
                'numero' => $i,
                'concepto' => "Mensualidad $i",
                'monto' => 2000,
                'descuento' => 0,
                'monto_total' => 2000,
                'fecha_vencimiento' => $periodo->fecha_inicio->copy()->addMonths($i),
                'estatus' => EstatusConceptoPago::PENDIENTE->value,
            ]);
        }
    }

    // Si el estatus requiere pago validado, créalo
    if (in_array($estatus, [
        InscripcionEstatus::PAYMENT_VALIDATED,
        InscripcionEstatus::PENDING_DOCUMENTS,
        InscripcionEstatus::DOCUMENT_REVIEW,
        InscripcionEstatus::FINAL_REVIEW,
        InscripcionEstatus::ENROLLED,
    ], true)) {
        $conceptoInscripcion = $inscripcion->conceptoInscripcion;
        $pago = $conceptoInscripcion->pagos()->create([
            'monto' => 5000,
            'fecha_pago' => now()->toDateString(),
            'metodo' => MetodoPago::EFECTIVO->value,
            'estatus' => EstatusPago::VALIDADO->value,
            'folio' => 'PAG-' . now()->format('Ymd') . '-00000001',
            'validado_por' => User::factory()->deRol(RolUsuario::ADMIN)->create()->id,
            'validado_at' => now(),
            'registrado_por' => User::factory()->deRol(RolUsuario::ADMISIONES)->create()->id,
        ]);
        $conceptoInscripcion->update(['estatus' => EstatusConceptoPago::PAGADO->value, 'pagado_at' => now()]);
    }

    // Si el estatus requiere documentos aceptados, créalos
    if (in_array($estatus, [
        InscripcionEstatus::DOCUMENT_REVIEW,
        InscripcionEstatus::FINAL_REVIEW,
        InscripcionEstatus::ENROLLED,
    ], true)) {
        $tipoDoc = ModeloTipoDocumento::factory()->create(['obligatorio' => true, 'activo' => true]);
        $validador = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
        $subidor = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
        $inscripcion->documentos()->create([
            'tipo_documento_id' => $tipoDoc->id,
            'ruta' => 'private/test.pdf',
            'nombre_original' => 'test.pdf',
            'mime' => 'application/pdf',
            'tamano_kb' => 100,
            'estatus' => EstatusDocumento::ACEPTADO->value,
            'validado_por' => $validador->id,
            'validado_at' => now(),
            'subido_por' => $subidor->id,
        ]);
    }

    // Si el estatus requiere observaciones resueltas, créalas
    if (in_array($estatus, [
        InscripcionEstatus::FINAL_REVIEW,
        InscripcionEstatus::ENROLLED,
    ], true)) {
        $obs = $inscripcion->observaciones()->create([
            'tipo' => TipoObservacion::FALTANTE->value,
            'detalle' => 'Faltante resuelto para prueba',
            'estatus' => 'RESUELTA',
            'creada_por' => User::factory()->deRol(RolUsuario::ADMISIONES)->create()->id,
            'resuelta_por' => User::factory()->deRol(RolUsuario::ADMISIONES)->create()->id,
            'resuelta_at' => now(),
        ]);
    }

    return $inscripcion->refresh();
}

/**
 * F6: ADMISIONES no puede validar pago (solo registrar)
 */
test('F6: ADMISIONES no puede validar pago (acción Livewire)', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $inscripcion = crearInscripcionConRequisitos(InscripcionEstatus::PAYMENT_REVIEW);
    $concepto = $inscripcion->conceptoInscripcion;
    $pago = $concepto->pagos()->create([
        'monto' => 5000,
        'fecha_pago' => now()->toDateString(),
        'metodo' => MetodoPago::EFECTIVO->value,
        'estatus' => EstatusPago::PENDIENTE->value,
        'registrado_por' => $admisiones->id,
    ]);

    // Intentar validar como ADMISIONES → 403
    // El componente ModalValidarPago espera una Inscripcion, no un Pago
    Livewire::actingAs($admisiones)
        ->test(\App\Livewire\Pagos\ModalValidarPago::class, ['inscripcion' => $inscripcion])
        ->call('validar', $pago->id)
        ->assertForbidden();
});

/**
 * F7: ADMISIONES no puede validar documentos (solo cargar)
 */
test('F7: ADMISIONES no puede validar documentos (acción Livewire)', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $inscripcion = crearInscripcionConRequisitos(InscripcionEstatus::DOCUMENT_REVIEW);
    $documento = $inscripcion->documentos()->first();

    // Intentar validar como ADMISIONES → 403
    Livewire::actingAs($admisiones)
        ->test(\App\Livewire\Documentos\PanelDocumentos::class, ['inscripcion' => $inscripcion])
        ->call('aceptar', $documento->id)
        ->assertForbidden();
});

/**
 * F8: ADMINISTRACION no puede asignar número de control (finalizar inscripción)
 */
test('F8: ADMINISTRACION no puede asignar número de control (acción Livewire)', function () {
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $inscripcion = crearInscripcionConRequisitos(InscripcionEstatus::FINAL_REVIEW);

    // Intentar finalizar como ADMINISTRACION → 403
    Livewire::actingAs($administracion)
        ->test(\App\Livewire\Inscripciones\PanelEstados::class, ['inscripcion' => $inscripcion])
        ->call('finalizar')
        ->assertForbidden();
});

/**
 * F9: CONTROL_ESCOLAR no puede validar pagos (solo registrar)
 */
test('F9: CONTROL_ESCOLAR no puede validar pagos (acción Livewire)', function () {
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $inscripcion = crearInscripcionConRequisitos(InscripcionEstatus::PAYMENT_REVIEW);
    $concepto = $inscripcion->conceptoInscripcion;
    $pago = $concepto->pagos()->create([
        'monto' => 5000,
        'fecha_pago' => now()->toDateString(),
        'metodo' => MetodoPago::EFECTIVO->value,
        'estatus' => EstatusPago::PENDIENTE->value,
        'registrado_por' => User::factory()->deRol(RolUsuario::ADMISIONES)->create()->id,
    ]);

    // Intentar validar como CONTROL_ESCOLAR → 403
    Livewire::actingAs($control)
        ->test(\App\Livewire\Pagos\ModalValidarPago::class, ['inscripcion' => $inscripcion])
        ->call('validar', $pago->id)
        ->assertForbidden();
});

/**
 * Matriz completa de GATES (capacidades definidas en AuthServiceProvider::CAPACIDADES)
 * Estas son las capacidades que se definen como Gates, no las que resuelven las Policies.
 */
dataset('gates_matriz', [
    // alumno.create, alumno.update, alumno.delete
    ['alumno.create', RolUsuario::ADMIN->value, true],
    ['alumno.create', RolUsuario::ADMISIONES->value, true],
    ['alumno.create', RolUsuario::ADMINISTRACION->value, false],
    ['alumno.create', RolUsuario::CONTROL_ESCOLAR->value, false],

    ['alumno.update', RolUsuario::ADMIN->value, true],
    ['alumno.update', RolUsuario::ADMISIONES->value, true],
    ['alumno.update', RolUsuario::ADMINISTRACION->value, false],
    ['alumno.update', RolUsuario::CONTROL_ESCOLAR->value, false],

    ['alumno.delete', RolUsuario::ADMIN->value, true],
    ['alumno.delete', RolUsuario::ADMISIONES->value, true],
    ['alumno.delete', RolUsuario::ADMINISTRACION->value, false],
    ['alumno.delete', RolUsuario::CONTROL_ESCOLAR->value, false],

    // informacionMedica.view, informacionMedica.edit
    ['informacionMedica.view', RolUsuario::ADMIN->value, true],
    ['informacionMedica.view', RolUsuario::ADMISIONES->value, true],
    ['informacionMedica.view', RolUsuario::ADMINISTRACION->value, false],
    ['informacionMedica.view', RolUsuario::CONTROL_ESCOLAR->value, true],

    ['informacionMedica.edit', RolUsuario::ADMIN->value, true],
    ['informacionMedica.edit', RolUsuario::ADMISIONES->value, true],
    ['informacionMedica.edit', RolUsuario::ADMINISTRACION->value, false],
    ['informacionMedica.edit', RolUsuario::CONTROL_ESCOLAR->value, false],

    // inscripcion.create, inscripcion.update, inscripcion.generarCargos, inscripcion.finalizar, inscripcion.cancelar
    ['inscripcion.create', RolUsuario::ADMIN->value, true],
    ['inscripcion.create', RolUsuario::ADMISIONES->value, true],
    ['inscripcion.create', RolUsuario::ADMINISTRACION->value, false],
    ['inscripcion.create', RolUsuario::CONTROL_ESCOLAR->value, false],

    ['inscripcion.update', RolUsuario::ADMIN->value, true],
    ['inscripcion.update', RolUsuario::ADMISIONES->value, true],
    ['inscripcion.update', RolUsuario::ADMINISTRACION->value, false],
    ['inscripcion.update', RolUsuario::CONTROL_ESCOLAR->value, false],

    ['inscripcion.generarCargos', RolUsuario::ADMIN->value, true],
    ['inscripcion.generarCargos', RolUsuario::ADMISIONES->value, true],
    ['inscripcion.generarCargos', RolUsuario::ADMINISTRACION->value, false],
    ['inscripcion.generarCargos', RolUsuario::CONTROL_ESCOLAR->value, false],

    ['inscripcion.finalizar', RolUsuario::ADMIN->value, true],
    ['inscripcion.finalizar', RolUsuario::ADMISIONES->value, false],
    ['inscripcion.finalizar', RolUsuario::ADMINISTRACION->value, false],
    ['inscripcion.finalizar', RolUsuario::CONTROL_ESCOLAR->value, true],

    ['inscripcion.cancelar', RolUsuario::ADMIN->value, true],
    ['inscripcion.cancelar', RolUsuario::ADMISIONES->value, false],
    ['inscripcion.cancelar', RolUsuario::ADMINISTRACION->value, false],
    ['inscripcion.cancelar', RolUsuario::CONTROL_ESCOLAR->value, false],

    // convenio.aplicar, convenio.aprobar
    ['convenio.aplicar', RolUsuario::ADMIN->value, true],
    ['convenio.aplicar', RolUsuario::ADMISIONES->value, true],
    ['convenio.aplicar', RolUsuario::ADMINISTRACION->value, false],
    ['convenio.aplicar', RolUsuario::CONTROL_ESCOLAR->value, false],

    ['convenio.aprobar', RolUsuario::ADMIN->value, true],
    ['convenio.aprobar', RolUsuario::ADMISIONES->value, false],
    ['convenio.aprobar', RolUsuario::ADMINISTRACION->value, true],
    ['convenio.aprobar', RolUsuario::CONTROL_ESCOLAR->value, false],

    // pago.registrar, pago.validar
    ['pago.registrar', RolUsuario::ADMIN->value, true],
    ['pago.registrar', RolUsuario::ADMISIONES->value, true],
    ['pago.registrar', RolUsuario::ADMINISTRACION->value, true],
    ['pago.registrar', RolUsuario::CONTROL_ESCOLAR->value, false],

    ['pago.validar', RolUsuario::ADMIN->value, true],
    ['pago.validar', RolUsuario::ADMISIONES->value, false],
    ['pago.validar', RolUsuario::ADMINISTRACION->value, true],
    ['pago.validar', RolUsuario::CONTROL_ESCOLAR->value, false],

    // documento.cargar, documento.validar
    ['documento.cargar', RolUsuario::ADMIN->value, true],
    ['documento.cargar', RolUsuario::ADMISIONES->value, true],
    ['documento.cargar', RolUsuario::ADMINISTRACION->value, true],
    ['documento.cargar', RolUsuario::CONTROL_ESCOLAR->value, true],

    ['documento.validar', RolUsuario::ADMIN->value, true],
    ['documento.validar', RolUsuario::ADMISIONES->value, false],
    ['documento.validar', RolUsuario::ADMINISTRACION->value, false],
    ['documento.validar', RolUsuario::CONTROL_ESCOLAR->value, true],

    // observacion.crear, observacion.resolver
    ['observacion.crear', RolUsuario::ADMIN->value, true],
    ['observacion.crear', RolUsuario::ADMISIONES->value, true],
    ['observacion.crear', RolUsuario::ADMINISTRACION->value, true],
    ['observacion.crear', RolUsuario::CONTROL_ESCOLAR->value, true],

    ['observacion.resolver', RolUsuario::ADMIN->value, true],
    ['observacion.resolver', RolUsuario::ADMISIONES->value, true],
    ['observacion.resolver', RolUsuario::ADMINISTRACION->value, true],
    ['observacion.resolver', RolUsuario::CONTROL_ESCOLAR->value, true],

    // auditoria.view
    ['auditoria.view', RolUsuario::ADMIN->value, true],
    ['auditoria.view', RolUsuario::ADMISIONES->value, false],
    ['auditoria.view', RolUsuario::ADMINISTRACION->value, false],
    ['auditoria.view', RolUsuario::CONTROL_ESCOLAR->value, false],

    // usuario.manage
    ['usuario.manage', RolUsuario::ADMIN->value, true],
    ['usuario.manage', RolUsuario::ADMISIONES->value, false],
    ['usuario.manage', RolUsuario::ADMINISTRACION->value, false],
    ['usuario.manage', RolUsuario::CONTROL_ESCOLAR->value, false],
]);

test('matriz de gates: {capacidad} para {rol} → {esperado}', function (string $capacidad, string $rol, bool $esperado) {
    $usuario = User::factory()->deRol(RolUsuario::from($rol))->create();
    $permite = Gate::forUser($usuario)->allows($capacidad);
    expect($permite)->toBe($esperado, "Gate {$capacidad} para {$rol}: esperaba " . ($esperado ? 'true' : 'false'));
})->with('gates_matriz');

/**
 * Tests de políticas (Policy-based authorization) para acciones que NO son gates
 * sino métodos de Policy: view, viewAny, descargarComprobantePdf, descargarComprobante, etc.
 */
test('políticas: InscripcionPolicy::viewAny permite a los 4 roles activos', function () {
    foreach ([RolUsuario::ADMIN, RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR] as $rol) {
        $usuario = User::factory()->deRol($rol)->create();
        $policy = app(\App\Policies\InscripcionPolicy::class);
        expect($policy->viewAny($usuario))->toBeTrue("viewAny para {$rol->value}");
    }
});

test('políticas: InscripcionPolicy::viewAny deniega inactivo', function () {
    $inactivo = User::factory()->deRol(RolUsuario::ADMIN)->inactivo()->create();
    $policy = app(\App\Policies\InscripcionPolicy::class);
    expect($policy->viewAny($inactivo))->toBeFalse();
});

test('políticas: InscripcionPolicy::view permite a los 4 roles activos', function () {
    $inscripcion = Inscripcion::factory()->create();
    foreach ([RolUsuario::ADMIN, RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR] as $rol) {
        $usuario = User::factory()->deRol($rol)->create();
        $policy = app(\App\Policies\InscripcionPolicy::class);
        expect($policy->view($usuario, $inscripcion))->toBeTrue("view para {$rol->value}");
    }
});

test('políticas: DocumentoPolicy::view y viewAny permiten a los 4 roles activos', function () {
    $inscripcion = Inscripcion::factory()->create();
    $tipoDoc = ModeloTipoDocumento::factory()->create(['activo' => true]);
    $documento = $inscripcion->documentos()->create([
        'tipo_documento_id' => $tipoDoc->id,
        'ruta' => 'private/test.pdf',
        'nombre_original' => 'test.pdf',
        'mime' => 'application/pdf',
        'tamano_kb' => 100,
        'estatus' => EstatusDocumento::PENDIENTE->value,
        'subido_por' => User::factory()->deRol(RolUsuario::ADMISIONES)->create()->id,
    ]);
    foreach ([RolUsuario::ADMIN, RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR] as $rol) {
        $usuario = User::factory()->deRol($rol)->create();
        $policy = app(\App\Policies\DocumentoPolicy::class);
        expect($policy->viewAny($usuario))->toBeTrue("viewAny para {$rol->value}");
        expect($policy->view($usuario, $documento))->toBeTrue("view para {$rol->value}");
    }
});

test('políticas: PagoPolicy::descargarComprobantePdf permite a los 4 roles con lectura', function () {
    $pago = Pago::factory()->create(['estatus' => EstatusPago::VALIDADO->value, 'folio' => 'PAG-20261001-00000001']);
    foreach ([RolUsuario::ADMIN, RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR] as $rol) {
        $usuario = User::factory()->deRol($rol)->create();
        $policy = app(\App\Policies\PagoPolicy::class);
        expect($policy->descargarComprobantePdf($usuario, $pago))->toBeTrue("descargarComprobantePdf para {$rol->value}");
    }
});

test('políticas: PagoPolicy::descargarComprobantePdf deniega inactivo', function () {
    $pago = Pago::factory()->create(['estatus' => EstatusPago::VALIDADO->value, 'folio' => 'PAG-20261001-00000001']);
    $inactivo = User::factory()->deRol(RolUsuario::ADMIN)->inactivo()->create();
    $policy = app(\App\Policies\PagoPolicy::class);
    expect($policy->descargarComprobantePdf($inactivo, $pago))->toBeFalse();
});

test('políticas: PagoPolicy::descargarComprobante permite a los 4 roles activos', function () {
    $pago = Pago::factory()->create();
    foreach ([RolUsuario::ADMIN, RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR] as $rol) {
        $usuario = User::factory()->deRol($rol)->create();
        $policy = app(\App\Policies\PagoPolicy::class);
        expect($policy->descargarComprobante($usuario, $pago))->toBeTrue("descargarComprobante para {$rol->value}");
    }
});

test('políticas: InformacionMedicaPolicy restringe ADMINISTRACION', function () {
    $user = User::factory()->create();
    $alumno = Alumno::factory()->create(['creado_por' => $user->id]);
    $infoMedica = $alumno->informacionMedica()->create([
        'tipo_sangre' => 'O+', 
        'tiene_discapacidad' => false,
        'actualizado_por' => $user->id,
    ]);

    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();

    $policy = app(\App\Policies\InformacionMedicaPolicy::class);

    expect($policy->view($admin, $infoMedica))->toBeTrue();
    expect($policy->view($admisiones, $infoMedica))->toBeTrue();
    expect($policy->view($control, $infoMedica))->toBeTrue(); // solo en revisión final
    expect($policy->view($administracion, $infoMedica))->toBeFalse();

    expect($policy->update($admin, $infoMedica))->toBeTrue();
    expect($policy->update($admisiones, $infoMedica))->toBeTrue();
    expect($policy->update($control, $infoMedica))->toBeFalse();
    expect($policy->update($administracion, $infoMedica))->toBeFalse();
});

/**
 * Tests de integración HTTP/Livewire para los 4 flujos F6-F9 (ya definidos arriba)
 * y acciones críticas específicas.
 */