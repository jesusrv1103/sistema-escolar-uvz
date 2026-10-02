<?php

use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Livewire\Dashboard\PanelOperativo;
use App\Models\ConceptoPago;
use App\Models\Documento;
use App\Models\InformacionMedica;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\DashboardService;

/*
|--------------------------------------------------------------------------
| Fase 14 — Panel operativo y contadores (FC-9 / RB-20)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| Cubren: los 4 contadores exactos con una mezcla sintética de estados,
| el aislamiento de registros irrelevantes y los soft deletes, la
| autorización de los 4 roles con usuario inactivo bloqueado y la
| privacidad del panel (sin información médica, hashes ni rutas privadas).
| Sin mocks: los contadores salen SIEMPRE de DashboardService.
*/

/*|--------------------------------------------------------------------------
| Helpers F14 (sufijo de fase para no colisionar con la suite)
|--------------------------------------------------------------------------*/

/**
 * Mezcla sintética exacta de FC-9/RB-20:
 *  - Inscripciones: DRAFT + PENDING_PAYMENT + FINAL_REVIEW (pendientes = 3),
 *    ENROLLED (completadas = 1) y CANCELLED (en NINGUNO de los dos).
 *  - Pagos: PENDIENTE + VALIDADO + RECHAZADO (solo PENDIENTE cuenta = 1).
 *  - Documentos: PENDIENTE + ACEPTADO + RECHAZADO (solo PENDIENTE cuenta = 1).
 *
 * @return array<string, mixed>
 */
function escenarioPanelF14(): array
{
    $usuarios = [
        'admin' => User::factory()->deRol(RolUsuario::ADMIN)->create(),
        'admisiones' => User::factory()->deRol(RolUsuario::ADMISIONES)->create(),
        'administracion' => User::factory()->deRol(RolUsuario::ADMINISTRACION)->create(),
        'control' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create(),
    ];

    $draft = Inscripcion::factory()->enEstatus(InscripcionEstatus::DRAFT)->create();
    $pendiente = Inscripcion::factory()->enEstatus(InscripcionEstatus::PENDING_PAYMENT)->create();
    $revision = Inscripcion::factory()->enEstatus(InscripcionEstatus::FINAL_REVIEW)->create();
    $completada = Inscripcion::factory()->enEstatus(InscripcionEstatus::ENROLLED)->create();
    $cancelada = Inscripcion::factory()->enEstatus(InscripcionEstatus::CANCELLED)->create();

    // Pagos (3 estados) sobre conceptos de la inscripción PENDING_PAYMENT.
    $conceptoInscripcion = ConceptoPago::factory()->inscripcion()->create(['inscripcion_id' => $pendiente->id]);
    $conceptoMensualidad = ConceptoPago::factory()->create(['inscripcion_id' => $pendiente->id, 'numero' => 1]);

    $pagoPendiente = Pago::factory()->create(['concepto_pago_id' => $conceptoInscripcion->id]);
    $pagoValidado = Pago::factory()->create([
        'concepto_pago_id' => $conceptoMensualidad->id,
        'estatus' => 'VALIDADO',
        'folio' => 'PAG-20261002-00000001',
        'validado_por' => $usuarios['administracion']->id,
        'validado_at' => now(),
    ]);
    $pagoRechazado = Pago::factory()->rechazado()->create(['concepto_pago_id' => $conceptoMensualidad->id]);

    // Documentos (3 estados) sobre la inscripción en FINAL_REVIEW.
    $documentos = collect([
        documentoF14($revision, $usuarios, 'PENDIENTE', 'F14_PENDIENTE'),
        documentoF14($revision, $usuarios, 'ACEPTADO', 'F14_ACEPTADO'),
        documentoF14($revision, $usuarios, 'RECHAZADO', 'F14_RECHAZADO'),
    ]);

    return $usuarios + [
        'draft' => $draft,
        'pendiente' => $pendiente,
        'revision' => $revision,
        'completada' => $completada,
        'cancelada' => $cancelada,
        'pagoPendiente' => $pagoPendiente,
        'pagoValidado' => $pagoValidado,
        'pagoRechazado' => $pagoRechazado,
        'documentos' => $documentos,
    ];
}

/**
 * Documento sintético con estatus directo (la Fase 14 solo LEE): la
 * validación real vive en DocumentoService y no se toca aquí.
 */
function documentoF14(Inscripcion $inscripcion, array $usuarios, string $estatus, string $clave): Documento
{
    $tipo = TipoDocumento::create([
        'clave' => $clave,
        'nombre' => 'Documento '.$clave,
        'obligatorio' => true,
        'activo' => true,
    ]);

    $documento = new Documento();
    $documento->forceFill([
        'inscripcion_id' => $inscripcion->id,
        'tipo_documento_id' => $tipo->id,
        'ruta' => 'docs/ruta-privada-'.$clave.'.pdf',
        'nombre_original' => 'original-'.$clave.'.pdf',
        'mime' => 'application/pdf',
        'tamano_kb' => 12,
        'subido_por' => $usuarios['admisiones']->id,
        'estatus' => $estatus,
        'validado_por' => $estatus === 'PENDIENTE' ? null : $usuarios['control']->id,
        'validado_at' => $estatus === 'PENDIENTE' ? null : now(),
        'motivo_rechazo' => $estatus === 'RECHAZADO' ? 'No legible F14' : null,
    ])->save();

    return $documento;
}

/*|--------------------------------------------------------------------------
| Contadores (DashboardService)
|--------------------------------------------------------------------------*/

test('los 4 contadores reflejan exactamente la mezcla sintetica de estados', function () {
    escenarioPanelF14();

    // Pendientes = DRAFT + PENDING_PAYMENT + FINAL_REVIEW; CANCELLED y
    // ENROLLED no cuentan. Pagos/documentos: solo PENDIENTE.
    expect(app(DashboardService::class)->contadores())->toBe([
        'inscripciones_pendientes' => 3,
        'pagos_por_validar' => 1,
        'documentos_pendientes' => 1,
        'inscripciones_completadas' => 1,
    ]);
});

test('los contadores ignoran registros irrelevantes, canceladas y soft deletes', function () {
    $e = escenarioPanelF14();
    $servicio = app(DashboardService::class);

    // Registros irrelevantes: otra CANCELLED, otro pago VALIDADO, otro
    // RECHAZADO y otro documento ACEPTADO/RECHAZADO no mueven ningún contador.
    Inscripcion::factory()->enEstatus(InscripcionEstatus::CANCELLED)->create();
    Pago::factory()->create([
        'concepto_pago_id' => ConceptoPago::factory()->create(['inscripcion_id' => $e['revision']->id])->id,
        'estatus' => 'VALIDADO',
        'folio' => 'PAG-20261002-00000002',
        'validado_at' => now(),
    ]);
    Pago::factory()->rechazado()->create([
        'concepto_pago_id' => ConceptoPago::factory()->create(['inscripcion_id' => $e['revision']->id])->id,
    ]);
    $usuarios = ['admisiones' => $e['admisiones'], 'control' => $e['control']];
    documentoF14($e['draft'], $usuarios, 'ACEPTADO', 'F14_EXTRA_A');
    documentoF14($e['draft'], $usuarios, 'RECHAZADO', 'F14_EXTRA_R');

    expect($servicio->contadores())->toBe([
        'inscripciones_pendientes' => 3,
        'pagos_por_validar' => 1,
        'documentos_pendientes' => 1,
        'inscripciones_completadas' => 1,
    ]);

    // Una ENROLLED nueva SÍ cuenta como completada.
    Inscripcion::factory()->enEstatus(InscripcionEstatus::ENROLLED)->create();
    expect($servicio->inscripcionesCompletadas())->toBe(2);

    // Soft-deleted: fuera de los contadores operativos (scopes normales).
    $e['draft']->delete();
    expect($servicio->inscripcionesPendientes())->toBe(2)
        ->and(Inscripcion::withTrashed()->count())->toBe(7);
});

/*|--------------------------------------------------------------------------
| Autorización (matriz §8.2)
|--------------------------------------------------------------------------*/

test('autorizacion: los 4 roles abren el dashboard y el panel', function () {
    foreach ([RolUsuario::ADMIN, RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR] as $rol) {
        $usuario = User::factory()->deRol($rol)->create();
        $this->actingAs($usuario);

        // La página real monta el panel server-side…
        $this->get('/dashboard')->assertOk();

        // …y el componente Livewire autoriza en mount() (viewAny de
        // Inscripcion: la capacidad de lectura/listados existente).
        Livewire::test(PanelOperativo::class)->assertSee('Panel operativo');

        auth()->logout();
    }
});

test('autorizacion: un usuario inactivo no accede al panel ni al dashboard', function () {
    $inactivo = User::factory()->deRol(RolUsuario::ADMIN)->inactivo()->create();
    $this->actingAs($inactivo);

    // Página: el mount del panel aborta con 403 (Gate::before).
    $this->get('/dashboard')->assertForbidden();

    // Acceso Livewire forzado: también server-side.
    Livewire::test(PanelOperativo::class)->assertStatus(403);
});

/*|--------------------------------------------------------------------------
| UI
|--------------------------------------------------------------------------*/

test('UI: el panel muestra exactamente los 4 contadores aprobados y sus enlaces', function () {
    $e = escenarioPanelF14();

    $this->actingAs($e['admin']);

    Livewire::test(PanelOperativo::class)
        // Exactamente los 4 indicadores aprobados (sin extras).
        ->assertSee('Inscripciones pendientes')
        ->assertSee('Pagos por validar')
        ->assertSee('Documentos pendientes')
        ->assertSee('Inscripciones completadas')
        // Contadores desde DashboardService (3 pendientes / 1 completada).
        ->assertSee('>3</p>', false)
        ->assertSee('>1</p>', false)
        ->assertDontSee('>2</p>', false)
        // Definiciones literales de FC-9, sin porcentajes ni tendencias.
        ->assertSee('Estatus distinto de ENROLLED y CANCELLED.')
        ->assertSee('Pagos con estatus PENDIENTE (cuatro ojos).')
        ->assertSee('Documentos cargados con estatus PENDIENTE.')
        ->assertSee('Inscripciones con estatus ENROLLED.')
        // Enlaces solo a pantallas existentes (listado + filtro aprobado).
        ->assertSee(route('inscripciones.lista'), false)
        ->assertSee('filtroEstatus=ENROLLED', false)
        ->assertDontSee('Gráfica');

    auth()->logout();
});

/*|--------------------------------------------------------------------------
| Privacidad
|--------------------------------------------------------------------------*/

test('privacidad: el panel no expone informacion medica, hashes ni rutas privadas', function () {
    $e = escenarioPanelF14();

    InformacionMedica::factory()->create([
        'alumno_id' => $e['draft']->alumno_id,
        'alergias' => 'ALERGIA-F14-NO-MOSTRAR',
    ]);

    $this->actingAs($e['administracion']);

    Livewire::test(PanelOperativo::class)
        // Ni información médica (RB-18)…
        ->assertDontSee('ALERGIA-F14-NO-MOSTRAR')
        // …ni hashes de credenciales…
        ->assertDontSee($e['administracion']->password)
        // …ni rutas internas de archivos.
        ->assertDontSee('ruta-privada-F14_PENDIENTE');

    auth()->logout();
});
