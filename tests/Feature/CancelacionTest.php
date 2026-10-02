<?php

use App\Enums\EstatusConceptoPago;
use App\Enums\EstatusObservacion;
use App\Enums\EstatusPago;
use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Enums\TipoObservacion;
use App\Exceptions\CancelacionInvalidaException;
use App\Livewire\Inscripciones\ModalCancelarInscripcion;
use App\Livewire\Inscripciones\WizardInscripcion;
use App\Models\Auditoria;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\User;
use App\Services\InscripcionService;
use App\Services\ObservacionService;
use App\Services\PagoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Fase 13 — Cancelación de inscripción (FC-8/RB-11)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| F19 (solo ADMIN + motivo obligatorio + estados terminales rechazados) y
| F20 (efectos: solo conceptos PENDIENTE → CANCELADO; pagos/documentos/
| observaciones/número de control intactos, sin reembolsos), más la
| autorización (ADMISIONES/ADMINISTRACION/CONTROL_ESCOLAR/inactivo 403), el
| ROLLBACK TOTAL ante fallo real de la auditoría y la UI mínima.
| Sin mocks: la máquina de estados existente decide qué es cancelable.
*/

/*|--------------------------------------------------------------------------
| Helpers F13 (sufijo de fase para no colisionar con la suite)
|--------------------------------------------------------------------------*/

/**
 * Escenario real de efectos mixtos: DATA_COMPLETE → cargos → pago
 * INSCRIPCION VALIDADO → mensualidad 1 VALIDADO → intento de mensualidad 2
 * RECHAZADO → observación GENERAL abierta. Estado: PENDING_DOCUMENTS.
 *
 * Estado esperado: INSCRIPCION PAGADO, mensualidad 1 PAGADO y mensualidades
 * 2–4 PENDIENTE (conceptos); pagos 2 VALIDADO + 1 RECHAZADO (historial).
 *
 * @return array<string, mixed>
 */
function escenarioCancelacionF13(): array
{
    Storage::fake('private');

    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::DATA_COMPLETE)->create();

    auth()->setUser($admisiones);

    // Cargos: 1 INSCRIPCION + 4 MENSUALIDAD → PENDING_PAYMENT.
    app(InscripcionService::class)->generarCargos($inscripcion, $admisiones);

    $conceptoInscripcion = $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail();
    $mensualidades = $inscripcion->conceptosPago()->where('tipo', 'MENSUALIDAD')->orderBy('numero')->get();

    $datos = fn ($concepto): array => [
        'monto' => $concepto->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
        'referencia' => null,
        'notas' => null,
        'comprobante' => null,
    ];

    // 1) INSCRIPCION: pago VALIDADO (ADMISIONES registra, ADMINISTRACION
    //    valida) → PAYMENT_VALIDATED → PENDING_DOCUMENTS.
    $pagoInscripcion = app(PagoService::class)->registrar($conceptoInscripcion, $datos($conceptoInscripcion), $admisiones);

    auth()->setUser($administracion);
    app(PagoService::class)->validar($pagoInscripcion, $administracion);

    // 2) Mensualidad 1: pago VALIDADO (concepto ⇒ PAGADO).
    $pagoMensualidad = app(PagoService::class)->registrar($mensualidades[0], $datos($mensualidades[0]), $administracion);
    app(PagoService::class)->validar($pagoMensualidad, $administracion);

    // 3) Mensualidad 2: intento RECHAZADO (historial que debe conservarse
    //    íntegro; el concepto vuelve a admite nuevo intento ⇒ sigue PENDIENTE).
    $pagoRechazado = app(PagoService::class)->registrar($mensualidades[1], $datos($mensualidades[1]), $administracion);
    app(PagoService::class)->rechazar($pagoRechazado, 'Referencia no localizada en el banco', $administracion);

    // Observación GENERAL abierta: la cancelación no debe tocarla.
    $observacion = app(ObservacionService::class)->crear(
        $inscripcion,
        TipoObservacion::GENERAL,
        'Nota previa que la cancelación debe conservar',
        $administracion,
    );

    auth()->logout();

    return [
        'admin' => $admin,
        'admisiones' => $admisiones,
        'administracion' => $administracion,
        'control' => $control,
        'inscripcion' => $inscripcion->refresh(),
        'conceptoInscripcion' => $conceptoInscripcion->refresh(),
        'mensualidades' => $mensualidades->map->refresh(),
        'pagoInscripcion' => $pagoInscripcion->refresh(),
        'pagoMensualidad' => $pagoMensualidad->refresh(),
        'pagoRechazado' => $pagoRechazado->refresh(),
        'observacion' => $observacion,
    ];
}

/** Cancela con autenticación explícita y devuelve la inscripción cerrada. */
function cancelarF13(Inscripcion $inscripcion, User $usuario, string $motivo): Inscripcion
{
    auth()->setUser($usuario);

    try {
        return app(InscripcionService::class)->cancelar($inscripcion, $motivo, $usuario);
    } finally {
        auth()->logout();
    }
}

/**
 * Ejecuta cancelar() esperando el rechazo y verifica que NADA cambió:
 * estatus, campos `cancelada_*`, conceptos, auditorías de cancelación.
 */
function expectNoCancelaF13(Inscripcion $inscripcion, User $usuario, string $motivo, string $mensaje): void
{
    $inscripcion->refresh();
    $antes = [
        'estatus' => $inscripcion->estatus,
        'cancelada_por' => $inscripcion->cancelada_por,
        // ISO: `refresh()` trae instancias Carbon distintas (toBe compara identidad).
        'cancelada_at' => $inscripcion->cancelada_at?->toISOString(),
        'cancelacion_motivo' => $inscripcion->cancelacion_motivo,
        'conceptos' => $inscripcion->conceptosPago()->pluck('estatus', 'id'),
        'cambios' => Auditoria::query()->where('estado_nuevo', 'CANCELLED')->count(),
        'negocio' => Auditoria::query()->where('accion', 'inscripcion_cancelada')->count(),
    ];

    auth()->setUser($usuario);
    try {
        expect(fn () => app(InscripcionService::class)->cancelar($inscripcion, $motivo, $usuario))
            ->toThrow(CancelacionInvalidaException::class, $mensaje);
    } finally {
        auth()->logout();
    }

    $inscripcion->refresh();

    expect($inscripcion->estatus)->toBe($antes['estatus'])
        ->and($inscripcion->cancelada_por)->toBe($antes['cancelada_por'])
        ->and($inscripcion->cancelada_at?->toISOString())->toBe($antes['cancelada_at'])
        ->and($inscripcion->cancelacion_motivo)->toBe($antes['cancelacion_motivo'])
        ->and($inscripcion->conceptosPago()->pluck('estatus', 'id'))->toEqual($antes['conceptos'])
        ->and(Auditoria::query()->where('estado_nuevo', 'CANCELLED')->count())->toBe($antes['cambios'])
        ->and(Auditoria::query()->where('accion', 'inscripcion_cancelada')->count())->toBe($antes['negocio']);
}

/** Instantánea financiera mínima de una colección de conceptos/pagos. */
function instantaneaF13(iterable $filas, callable $datos): array
{
    $salida = [];
    foreach ($filas as $fila) {
        $salida[$fila->id] = $datos($fila);
    }

    return $salida;
}

/*|--------------------------------------------------------------------------
| F19 — Solo ADMIN cancela y requiere motivo
|--------------------------------------------------------------------------*/

test('F19: solo ADMIN cancela — ADMIN con motivo valido cierra en CANCELLED', function () {
    $e = escenarioCancelacionF13();

    expect($e['inscripcion']->estatus)->toBe(InscripcionEstatus::PENDING_DOCUMENTS)
        ->and($e['inscripcion']->cancelada_at)->toBeNull()
        ->and($e['inscripcion']->cancelacion_motivo)->toBeNull();

    $motivo = 'La familia se muda a otra ciudad.';
    $cancelada = cancelarF13($e['inscripcion'], $e['admin'], $motivo);

    expect($cancelada->estatus)->toBe(InscripcionEstatus::CANCELLED)
        ->and($cancelada->cancelada_por)->toBe($e['admin']->id)
        ->and($cancelada->cancelada_at)->not->toBeNull()
        ->and($cancelada->cancelacion_motivo)->toBe($motivo);

    // Auditoría de la transición con usuario responsable y motivo (RB-12).
    $cambio = Auditoria::query()
        ->where('accion', 'estado_cambiado')
        ->where('estado_anterior', 'PENDING_DOCUMENTS')
        ->where('estado_nuevo', 'CANCELLED')
        ->firstOrFail();
    expect($cambio->user_id)->toBe($e['admin']->id)
        ->and($cambio->datos['motivo'])->toBe($motivo);

    // Auditoría de negocio con contexto mínimo (3 mensualidades PENDIENTE).
    $negocio = Auditoria::query()->where('accion', 'inscripcion_cancelada')->firstOrFail();
    expect($negocio->user_id)->toBe($e['admin']->id)
        ->and($negocio->datos['conceptos_cancelados'])->toBe(3);
});

test('F19: ADMISIONES, ADMINISTRACION y CONTROL_ESCOLAR reciben 403', function () {
    $e = escenarioCancelacionF13();

    foreach (['admisiones', 'administracion', 'control'] as $rol) {
        auth()->setUser($e[$rol]);
        try {
            expect(fn () => app(InscripcionService::class)->cancelar($e['inscripcion'], 'Motivo valido de cancelacion', $e[$rol]))
                ->toThrow(AuthorizationException::class);
        } finally {
            auth()->logout();
        }
    }

    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_DOCUMENTS)
        ->and($e['inscripcion']->cancelada_at)->toBeNull()
        ->and($e['inscripcion']->cancelacion_motivo)->toBeNull()
        ->and(Auditoria::query()->where('estado_nuevo', 'CANCELLED')->exists())->toBeFalse()
        ->and($e['mensualidades'][1]->refresh()->estatus)->toBe(EstatusConceptoPago::PENDIENTE);
});

test('F19: un usuario inactivo recibe 403 aunque sea ADMIN', function () {
    $e = escenarioCancelacionF13();
    $inactivo = User::factory()->deRol(RolUsuario::ADMIN)->inactivo()->create();

    auth()->setUser($inactivo);
    try {
        expect(fn () => app(InscripcionService::class)->cancelar($e['inscripcion'], 'Motivo valido de cancelacion', $inactivo))
            ->toThrow(AuthorizationException::class);
    } finally {
        auth()->logout();
    }

    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_DOCUMENTS)
        ->and($e['inscripcion']->cancelada_at)->toBeNull()
        ->and(Auditoria::query()->where('estado_nuevo', 'CANCELLED')->exists())->toBeFalse();
});

test('F19: el motivo es obligatorio — vacio, solo espacios y gigante se rechazan', function () {
    $e = escenarioCancelacionF13();

    expectNoCancelaF13($e['inscripcion'], $e['admin'], '', 'motivo');
    expectNoCancelaF13($e['inscripcion'], $e['admin'], "     \n   ", 'motivo');
    expectNoCancelaF13($e['inscripcion'], $e['admin'], str_repeat('x', 2001), '2000');

    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_DOCUMENTS);
});

test('F19: ENROLLED no cancelable — rechazo explicito de estado terminal', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::ENROLLED)->create();

    expectNoCancelaF13($inscripcion, $admin, 'Motivo valido de cancelacion', 'Inscripción completada');

    expect($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::ENROLLED);
});

test('F19: CANCELLED no se vuelve a cancelar (sin idempotencia silenciosa)', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::PENDING_PAYMENT)->create();

    cancelarF13($inscripcion, $admin, 'Primera cancelacion valida');

    expectNoCancelaF13($inscripcion, $admin, 'Segundo intento con otro motivo', 'Cancelada');

    $inscripcion->refresh();
    expect($inscripcion->estatus)->toBe(InscripcionEstatus::CANCELLED)
        ->and($inscripcion->cancelacion_motivo)->toBe('Primera cancelacion valida')
        ->and(Auditoria::query()->where('accion', 'estado_cambiado')->where('estado_nuevo', 'CANCELLED')->count())->toBe(1)
        ->and(Auditoria::query()->where('accion', 'inscripcion_cancelada')->count())->toBe(1);
});

test('F19: estados de distintas etapas cancelan segun la maquina existente', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $estados = ['DRAFT', 'PENDING_PAYMENT', 'PAYMENT_REVIEW', 'PENDING_DOCUMENTS', 'FINAL_REVIEW'];

    foreach ($estados as $indice => $estado) {
        $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::from($estado))->create();
        $motivo = 'Motivo de cancelación en etapa '.$estado;

        $cancelada = cancelarF13($inscripcion, $admin, $motivo);

        expect($cancelada->estatus)->toBe(InscripcionEstatus::CANCELLED)
            ->and($cancelada->cancelada_por)->toBe($admin->id)
            ->and($cancelada->cancelacion_motivo)->toBe($motivo)
            ->and(Auditoria::query()->where('accion', 'estado_cambiado')->where('estado_nuevo', 'CANCELLED')->count())
            ->toBe($indice + 1);
    }
});

/*|--------------------------------------------------------------------------
| F20 — Efectos sobre conceptos y pagos
|--------------------------------------------------------------------------*/

test('F20: conceptos mixtos — solo PENDIENTE pasa a CANCELADO y los PAGADO quedan intactos', function () {
    $e = escenarioCancelacionF13();

    // Estado previo exigido: INSCRIPCION PAGADO, mensualidad 1 PAGADO, 2–4 PENDIENTE.
    expect($e['conceptoInscripcion']->estatus)->toBe(EstatusConceptoPago::PAGADO)
        ->and($e['mensualidades'][0]->estatus)->toBe(EstatusConceptoPago::PAGADO)
        ->and($e['mensualidades'][1]->estatus)->toBe(EstatusConceptoPago::PENDIENTE)
        ->and($e['mensualidades'][2]->estatus)->toBe(EstatusConceptoPago::PENDIENTE)
        ->and($e['mensualidades'][3]->estatus)->toBe(EstatusConceptoPago::PENDIENTE);

    $antes = instantaneaF13($e['inscripcion']->conceptosPago()->get(), fn ($c): array => [
        'monto' => $c->monto,
        'descuento' => $c->descuento,
        'monto_total' => $c->monto_total,
        'pagado_at' => $c->pagado_at?->toISOString(),
        'estatus' => $c->estatus->value,
    ]);

    cancelarF13($e['inscripcion'], $e['admin'], 'Cancelación con pagos parciales de mensualidades');

    $despues = instantaneaF13($e['inscripcion']->conceptosPago()->get(), fn ($c): array => [
        'monto' => $c->monto,
        'descuento' => $c->descuento,
        'monto_total' => $c->monto_total,
        'pagado_at' => $c->pagado_at?->toISOString(),
        'estatus' => $c->estatus->value,
    ]);

    // Efecto exacto: PENDIENTE → CANCELADO; todo lo demás idéntico.
    foreach ($antes as $id => $datos) {
        $esperado = $datos['estatus'] === EstatusConceptoPago::PENDIENTE->value
            ? EstatusConceptoPago::CANCELADO->value
            : $datos['estatus'];

        expect($despues[$id]['estatus'])->toBe($esperado)
            ->and($despues[$id]['monto'])->toBe($datos['monto'])
            ->and($despues[$id]['descuento'])->toBe($datos['descuento'])
            ->and($despues[$id]['monto_total'])->toBe($datos['monto_total'])
            ->and($despues[$id]['pagado_at'])->toBe($datos['pagado_at']);
    }

    expect($e['conceptoInscripcion']->refresh()->estatus)->toBe(EstatusConceptoPago::PAGADO)
        ->and($e['mensualidades'][0]->refresh()->estatus)->toBe(EstatusConceptoPago::PAGADO)
        ->and($e['mensualidades'][1]->refresh()->estatus)->toBe(EstatusConceptoPago::CANCELADO)
        ->and($e['mensualidades'][2]->refresh()->estatus)->toBe(EstatusConceptoPago::CANCELADO)
        ->and($e['mensualidades'][3]->refresh()->estatus)->toBe(EstatusConceptoPago::CANCELADO);
});

test('F20: pagos VALIDADO y RECHAZADO intactos — sin reembolsos ni cambios financieros', function () {
    $e = escenarioCancelacionF13();

    $datosPago = fn (Pago $p): array => [
        'estatus' => $p->estatus->value,
        'folio' => $p->folio,
        'referencia' => $p->referencia,
        'monto' => $p->monto,
        'validado_por' => $p->validado_por,
        'validado_at' => $p->validado_at?->toISOString(),
        'registrado_por' => $p->registrado_por,
        'comprobante_ruta' => $p->comprobante_ruta,
    ];

    $antes = instantaneaF13(Pago::query()->orderBy('id')->get(), $datosPago);

    cancelarF13($e['inscripcion'], $e['admin'], 'Cancelación conservando el historial de pagos');

    $despues = instantaneaF13(Pago::query()->orderBy('id')->get(), $datosPago);

    // Idénticos uno a uno: sin deletes, sin updates financieros, sin reembolsos.
    expect($despues)->toEqual($antes)
        ->and(Pago::query()->count())->toBe(3)
        ->and($e['pagoInscripcion']->refresh()->estatus)->toBe(EstatusPago::VALIDADO)
        ->and($e['pagoInscripcion']->folio)->not->toBeNull()
        ->and($e['pagoMensualidad']->refresh()->estatus)->toBe(EstatusPago::VALIDADO)
        ->and($e['pagoMensualidad']->folio)->not->toBeNull()
        ->and($e['pagoRechazado']->refresh()->estatus)->toBe(EstatusPago::RECHAZADO)
        ->and($e['pagoRechazado']->folio)->toBeNull();

    // Nada más se tocó: observación abierta intacta y NC sin asignar.
    expect($e['observacion']->refresh()->estatus)->toBe(EstatusObservacion::ABIERTA)
        ->and($e['inscripcion']->refresh()->alumno->numero_control)->toBeNull();
});

/*|--------------------------------------------------------------------------
| Rollback total (sin mocks)
|--------------------------------------------------------------------------*/

test('rollback: si la auditoria falla no queda ningun efecto de la cancelacion', function () {
    $e = escenarioCancelacionF13();
    $inscripcion = $e['inscripcion'];

    $previas = Auditoria::query()->count();
    $estatusAntes = $inscripcion->estatus;
    $conceptosAntes = $inscripcion->conceptosPago()->pluck('estatus', 'id');
    $datosPago = fn (Pago $p): array => ['estatus' => $p->estatus->value, 'folio' => $p->folio, 'monto' => $p->monto];
    $pagosAntes = instantaneaF13(Pago::query()->orderBy('id')->get(), $datosPago);

    // Fallo REAL en la escritura de `auditoria` (evento del modelo real),
    // después de cancelar los conceptos y de intentar la transición ⇒
    // ROLLBACK TOTAL de todo lo anterior.
    Auditoria::creating(function (): void {
        throw new RuntimeException('Fallo real al escribir la auditoria F13');
    });

    try {
        auth()->setUser($e['admin']);
        expect(fn () => app(InscripcionService::class)->cancelar($inscripcion, 'Motivo que nunca debe persistir', $e['admin']))
            ->toThrow(RuntimeException::class, 'Fallo real al escribir la auditoria');
    } finally {
        auth()->logout();
        Auditoria::flushEventListeners();   // limpia el gancho para el resto de la suite
    }

    $inscripcion->refresh();
    expect($inscripcion->estatus)->toBe($estatusAntes)
        ->and($inscripcion->cancelada_por)->toBeNull()
        ->and($inscripcion->cancelada_at)->toBeNull()
        ->and($inscripcion->cancelacion_motivo)->toBeNull()
        ->and($inscripcion->conceptosPago()->pluck('estatus', 'id'))->toEqual($conceptosAntes)
        ->and(instantaneaF13(Pago::query()->orderBy('id')->get(), $datosPago))->toEqual($pagosAntes)
        ->and(Auditoria::query()->count())->toBe($previas);
});

/*|--------------------------------------------------------------------------
| UI — ModalCancelarInscripcion
|--------------------------------------------------------------------------*/

test('UI: el panel de cancelacion se muestra solo a ADMIN y solo en estados cancelables', function () {
    $e = escenarioCancelacionF13();

    // ADMIN en estado cancelable: ve la acción y los tres avisos.
    $this->actingAs($e['admin']);
    Livewire::test(ModalCancelarInscripcion::class, ['inscripcion' => $e['inscripcion']])
        ->assertSee('Cancelar inscripción')
        ->assertSee('reembolso')
        ->assertSee('definitiva en el MVP')
        ->assertSee('wire:click="abrir"', false);
    auth()->logout();

    // ADMISIONES: sin capacidad inscripcion.cancelar ⇒ no ve nada.
    $this->actingAs($e['admisiones']);
    Livewire::test(ModalCancelarInscripcion::class, ['inscripcion' => $e['inscripcion']])
        ->assertDontSee('Cancelar inscripción');
    auth()->logout();

    // ENROLLED (terminal): ADMIN ve el panel pero SIN botón de cancelar.
    $enrolled = Inscripcion::factory()->enEstatus(InscripcionEstatus::ENROLLED)->create();
    $this->actingAs($e['admin']);
    Livewire::test(ModalCancelarInscripcion::class, ['inscripcion' => $enrolled])
        ->assertSee('No cancelable (Inscripción completada)')
        ->assertDontSee('wire:click="abrir"', false);
    auth()->logout();
});

test('UI: cancelar desde el componente cierra en CANCELLED y deja sin boton de reapertura', function () {
    $e = escenarioCancelacionF13();

    $this->actingAs($e['admin']);
    Livewire::test(ModalCancelarInscripcion::class, ['inscripcion' => $e['inscripcion']])
        ->call('abrir')
        ->assertSee('Motivo de la cancelación (obligatorio)')
        ->set('motivo', 'La familia se muda de país.')
        ->call('cancelar')
        ->assertHasNoErrors()
        ->assertSee('No cancelable (Cancelada)')
        ->assertDontSee('wire:click="abrir"', false)
        ->assertDontSee('Confirmar cancelación');
    auth()->logout();

    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::CANCELLED)
        ->and($e['inscripcion']->cancelacion_motivo)->toBe('La familia se muda de país.')
        ->and($e['mensualidades'][1]->refresh()->estatus)->toBe(EstatusConceptoPago::CANCELADO);
});

test('UI: el componente exige motivo no vacio (vacio y solo espacios)', function () {
    $e = escenarioCancelacionF13();

    $this->actingAs($e['admin']);
    Livewire::test(ModalCancelarInscripcion::class, ['inscripcion' => $e['inscripcion']])
        ->call('abrir')
        ->set('motivo', '')
        ->call('cancelar')
        ->assertHasErrors(['motivo' => 'required']);

    Livewire::test(ModalCancelarInscripcion::class, ['inscripcion' => $e['inscripcion']])
        ->call('abrir')
        ->set('motivo', '     ')
        ->call('cancelar')
        ->assertHasErrors(['motivo' => 'required']);
    auth()->logout();

    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_DOCUMENTS)
        ->and($e['inscripcion']->cancelacion_motivo)->toBeNull()
        ->and(Auditoria::query()->where('estado_nuevo', 'CANCELLED')->exists())->toBeFalse();
});

test('UI: un rol sin permiso no puede forzar cancelar desde el componente (403)', function () {
    $e = escenarioCancelacionF13();

    // El método se fuerza por Livewire, pero la autorización server-side
    // responde 403 y nada se ejecuta.
    $this->actingAs($e['admisiones']);
    Livewire::test(ModalCancelarInscripcion::class, ['inscripcion' => $e['inscripcion']])
        ->call('cancelar')
        ->assertStatus(403);
    auth()->logout();

    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_DOCUMENTS)
        ->and($e['inscripcion']->cancelada_at)->toBeNull()
        ->and(Auditoria::query()->where('estado_nuevo', 'CANCELLED')->exists())->toBeFalse();
});

test('UI: la vista de inscripcion incluye el panel de cancelacion para ADMIN', function () {
    $e = escenarioCancelacionF13();

    // La ruta responde OK (el include no rompe la página)…
    $this->actingAs($e['admin'])
        ->get(route('inscripciones.ver', $e['inscripcion']))
        ->assertOk();
    auth()->logout();

    // … y la vista del wizard renderiza el panel para ADMIN
    // (convención de la suite: el contenido se verifica con Livewire::test).
    $this->actingAs($e['admin']);
    Livewire::test(WizardInscripcion::class, ['inscripcion' => $e['inscripcion']])
        ->assertSee('Cancelar inscripción');
    auth()->logout();
});
