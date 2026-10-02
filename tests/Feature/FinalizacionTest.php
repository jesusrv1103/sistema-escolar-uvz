<?php

use App\Enums\EstatusConceptoPago;
use App\Enums\EstatusConvenio;
use App\Enums\EstatusDocumento;
use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Enums\TipoConsecutivo;
use App\Enums\TipoObservacion;
use App\Exceptions\FinalizacionInvalidaException;
use App\Livewire\Inscripciones\PanelEstados;
use App\Models\Auditoria;
use App\Models\Consecutivo;
use App\Models\Convenio;
use App\Models\Inscripcion;
use App\Models\Observacion;
use App\Models\Periodo;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\ConvenioService;
use App\Services\DocumentoService;
use App\Services\InscripcionService;
use App\Services\ObservacionService;
use App\Services\PagoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Fase 12 — Revisión final y número de control (FC-7/RB-8/RB-9/P2)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| F16 (caso feliz completo + trazabilidad), F17 (mensualidades/observaciones
| que NO bloquean), F18 (bloqueos independientes con ROLLBACK total), la
| conservación del NC, la autorización (ADMIN/CONTROL_ESCOLAR OK; ADMISIONES,
| ADMINISTRACION e inactivos 403) y la UI de PanelEstados. El año `YYYY`
| EXCLUSIVAMENTE de `periodos.fecha_inicio` (decisión aprobada Fase 12).
| Sin mocks: los fallos son eventos reales del modelo y los bloqueos son
| guardas reales del servicio server-side.
*/

/*|--------------------------------------------------------------------------
| Helpers F12 (sufijo de fase para no colisionar con la suite)
|--------------------------------------------------------------------------*/

/** PDF mínimo real: `finfo` lo detecta como application/pdf (%PDF-). */
function pdfF12(): string
{
    return "%PDF-1.4\n"
        ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n"
        ."trailer<</Root 1 0 R>>\n%%EOF\n";
}

/** Crea un tipo de documento obligatorio en el catálogo (§4.11). */
function tipoDocumentoF12(string $clave, bool $obligatorio = true, bool $activo = true): TipoDocumento
{
    return TipoDocumento::create([
        'clave' => $clave,
        'nombre' => 'Documento '.$clave,
        'obligatorio' => $obligatorio,
        'activo' => $activo,
    ]);
}

/** Convenio sintético para el sub-proceso four-eyes de la Fase 7. */
function convenioF12(array $sobrescribir = []): Convenio
{
    static $contador = 0;
    $contador++;

    return Convenio::create($sobrescribir + [
        'clave' => 'F12'.$contador,
        'nombre' => 'Convenio de fase 12 '.$contador,
        'tipo' => 'PORCENTAJE',
        'valor' => '10.00',
        'fecha_inicio' => null,
        'fecha_fin' => null,
        'activo' => true,
    ]);
}

/** Sube un archivo real vía el servicio SIEMPRE con el usuario indicado. */
function subirDocumentoF12(Inscripcion $inscripcion, TipoDocumento $tipo, User $usuario, string $contenido, string $nombre = 'expediente.pdf')
{
    auth()->setUser($usuario);

    try {
        return app(DocumentoService::class)->subir(
            $inscripcion,
            $tipo->id,
            UploadedFile::fake()->createWithContent($nombre, $contenido),
            $usuario,
        );
    } finally {
        auth()->logout();   // la autenticación siempre es explícita en cada test
    }
}

/** Crea una observación real (append-only) con autenticación explícita. */
function crearObservacionF12(Inscripcion $inscripcion, User $usuario, TipoObservacion $tipo, string $detalle): Observacion
{
    auth()->setUser($usuario);

    try {
        return app(ObservacionService::class)->crear($inscripcion, $tipo, $detalle, $usuario);
    } finally {
        auth()->logout();
    }
}

/**
 * Escenario: los 4 roles de la matriz §8.2 e inscripción llevada por el flujo
 * REAL hasta FINAL_REVIEW con todas las guardas RB-8 cumplidas:
 * DATA_COMPLETE → [convenio resuelto] → generarCargos → pago INSCRIPCION
 * VALIDADO → documento obligatorio subido y ACEPTADO → FINAL_REVIEW.
 *
 * Opciones: `convenio` null|'aprobado'|'rechazado' (se resuelve ANTES de los
 * cargos, etapa previa F7), `periodo` y `alumno` para inscripciones
 * adicionales. Aisla `Storage::fake('private')` y deja la sesión sin autenticar.
 *
 * @return array{admin: User, admisiones: User, administracion: User, control: User, inscripcion: Inscripcion, tipo: TipoDocumento, documento: \App\Models\Documento, concepto: \App\Models\ConceptoPago}
 */
function escenarioFinalizacionF12(array $opciones = []): array
{
    $opciones += ['convenio' => null, 'periodo' => null, 'alumno' => null];

    Storage::fake('private');

    // Aislamiento del catálogo: los requisitos son globales (obligatorio +
    // activo); si la misma prueba ya construyó otro escenario, sus tipos se
    // desactivan para que solo cuenten los de ESTA inscripción.
    TipoDocumento::query()->update(['activo' => false]);

    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    $factory = Inscripcion::factory()->enEstatus(InscripcionEstatus::DATA_COMPLETE);
    if ($opciones['periodo'] !== null) {
        $factory = $factory->enPeriodo($opciones['periodo']);
    }
    if ($opciones['alumno'] !== null) {
        $factory = $factory->state(['alumno_id' => $opciones['alumno']->getKey()]);
    }
    $inscripcion = $factory->create();

    auth()->setUser($admisiones);

    // Convenio (opcional): se aplica y se resuelve ANTES de generar cargos
    // (etapa previa, F7). APROBADO ⇒ con descuento; RECHAZADO ⇒ resuelto sin
    // descuento (cargos normales). Ambos pueden finalizar (decisión aprobada
    // Fase 12); solo PENDIENTE_APROBACION bloquea RB-8.
    if ($opciones['convenio'] !== null) {
        $convenios = app(ConvenioService::class);
        $convenios->aplicar($inscripcion, convenioF12(), $admisiones);

        // Four-eyes (RB-17): quien aprueba/rechaza es ADMINISTRACION.
        auth()->setUser($administracion);
        if ($opciones['convenio'] === 'aprobado') {
            $convenios->aprobar($inscripcion, $administracion);
        } else {
            $convenios->rechazar($inscripcion, 'No procede el descuento solicitado', $administracion);
        }

        auth()->setUser($admisiones);
        $inscripcion->refresh();
    }

    // Cargos → registro y validación del pago INSCRIPCION → PENDING_DOCUMENTS.
    app(InscripcionService::class)->generarCargos($inscripcion, $admisiones);

    $concepto = $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail();
    $pago = app(PagoService::class)->registrar($concepto, [
        'monto' => $concepto->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
        'referencia' => null,
        'notas' => null,
        'comprobante' => null,
    ], $admisiones);

    auth()->setUser($administracion);
    app(PagoService::class)->validar($pago, $administracion);

    // Documento obligatorio cargado y ACEPTADO ⇒ DOCUMENT_REVIEW → FINAL_REVIEW.
    $tipo = tipoDocumentoF12('ACTA_NAC_'.$inscripcion->getKey());
    $documento = subirDocumentoF12($inscripcion, $tipo, $admisiones, pdfF12(), 'acta.pdf');

    auth()->setUser($control);
    app(DocumentoService::class)->aceptar($documento, $control);

    auth()->logout();

    return [
        'admin' => $admin,
        'admisiones' => $admisiones,
        'administracion' => $administracion,
        'control' => $control,
        'inscripcion' => $inscripcion->refresh(),
        'tipo' => $tipo,
        'documento' => $documento->refresh(),
        'concepto' => $concepto,
    ];
}

/** Finaliza con autenticación explícita y devuelve la inscripción cerrada. */
function finalizarF12(Inscripcion $inscripcion, User $usuario): Inscripcion
{
    auth()->setUser($usuario);

    try {
        return app(InscripcionService::class)->finalizar($inscripcion, $usuario);
    } finally {
        auth()->logout();
    }
}

/**
 * Ejecuta finalizar() esperando el rechazo RB-8 y verifica el ROLLBACK total:
 * mismo estatus, sin hitos, sin número de control, sin fila de `consecutivos`
 * y sin auditoría de ENROLLED ni de número de control.
 */
function expectNoFinalizaF12(Inscripcion $inscripcion, User $control, string $mensaje): void
{
    $estatusAntes = $inscripcion->estatus;

    auth()->setUser($control);
    try {
        expect(fn () => app(InscripcionService::class)->finalizar($inscripcion, $control))
            ->toThrow(FinalizacionInvalidaException::class, $mensaje);
    } finally {
        auth()->logout();
    }

    $inscripcion->refresh();

    expect($inscripcion->estatus)->toBe($estatusAntes)
        ->and($inscripcion->finalizada_at)->toBeNull()
        ->and($inscripcion->validada_por)->toBeNull()
        ->and($inscripcion->alumno->numero_control)->toBeNull()
        ->and(Consecutivo::query()->where('tipo', TipoConsecutivo::NUMERO_CONTROL->value)->exists())->toBeFalse()
        ->and(Auditoria::query()->where('estado_nuevo', InscripcionEstatus::ENROLLED->value)->count())->toBe(0)
        ->and(Auditoria::query()->where('accion', 'numero_control_asignado')->count())->toBe(0);
}

/*|--------------------------------------------------------------------------
| F16 — Caso feliz completo
|--------------------------------------------------------------------------*/

test('F16: la finalizacion asigna numero de control, cierra ENROLLED y deja trazabilidad', function () {
    $e = escenarioFinalizacionF12();
    $inscripcion = $e['inscripcion'];

    expect($inscripcion->estatus)->toBe(InscripcionEstatus::FINAL_REVIEW)
        ->and($inscripcion->alumno->numero_control)->toBeNull()
        ->and($inscripcion->finalizada_at)->toBeNull()
        ->and($inscripcion->validada_por)->toBeNull();

    $finalizada = finalizarF12($inscripcion, $e['control']);

    // Estado y responsables de la transición final (FC-7).
    expect($finalizada->estatus)->toBe(InscripcionEstatus::ENROLLED)
        ->and($finalizada->finalizada_at)->not->toBeNull()
        ->and($finalizada->validada_por)->toBe($e['control']->id)
        ->and($finalizada->alumno->numero_control)->toBe('20260001')
        ->and($finalizada->alumno->numero_control)->toMatch('/^\d{8}$/');

    // Consecutivo: incremento único de la fila (tipo, año) — nunca MAX().
    $fila = Consecutivo::query()
        ->where('tipo', TipoConsecutivo::NUMERO_CONTROL->value)
        ->where('anio', 2026)
        ->firstOrFail();
    expect($fila->ultimo_valor)->toBe(1)
        ->and(Consecutivo::query()->count())->toBe(1);

    // Auditoría FINAL_REVIEW → ENROLLED con el usuario responsable.
    $cambio = Auditoria::query()
        ->where('accion', 'estado_cambiado')
        ->where('estado_nuevo', 'ENROLLED')
        ->firstOrFail();
    expect($cambio->estado_anterior)->toBe('FINAL_REVIEW')
        ->and($cambio->user_id)->toBe($e['control']->id);

    // Auditoría de la asignación del NC (RB-12) con IP/UA del servicio.
    $nc = Auditoria::query()->where('accion', 'numero_control_asignado')->get();
    expect($nc)->toHaveCount(1)
        ->and($nc[0]->user_id)->toBe($e['control']->id)
        ->and($nc[0]->datos['numero_control'])->toBe('20260001')
        ->and($nc[0]->datos['anio'])->toBe(2026)
        ->and($nc[0]->datos['conservado'])->toBe(false);
});

test('F16: el anio YYYY sale de periodo.fecha_inicio aunque se finalice en otro anio', function () {
    // Periodo de ingreso en 2027; "hoy" en pruebas es 02/10/2026: el número
    // usa el año del PERIODO (decisión aprobada Fase 12), no el año actual.
    $periodo = Periodo::create([
        'clave' => 'PER-2027-F12',
        'nombre' => 'Enero 2027 (Fase 12)',
        'fecha_inicio' => '2027-01-11',
        'fecha_fin' => '2027-05-01',
        'activo' => true,
        'cerrado_at' => null,
    ]);

    $e = escenarioFinalizacionF12(['periodo' => $periodo]);
    $finalizada = finalizarF12($e['inscripcion'], $e['control']);

    expect($finalizada->alumno->numero_control)->toBe('20270001')
        ->and(Consecutivo::query()->where('anio', 2027)->firstOrFail()->ultimo_valor)->toBe(1)
        ->and(Consecutivo::query()->where('anio', 2026)->exists())->toBeFalse();
});

test('F16: si el alumno ya tiene numero de control se conserva sin incrementar el consecutivo', function () {
    $e = escenarioFinalizacionF12();
    $e['inscripcion']->alumno->forceFill(['numero_control' => '20250042'])->save();

    $finalizada = finalizarF12($e['inscripcion'], $e['control']);

    expect($finalizada->estatus)->toBe(InscripcionEstatus::ENROLLED)
        ->and($finalizada->alumno->numero_control)->toBe('20250042')
        ->and(Consecutivo::query()->where('tipo', TipoConsecutivo::NUMERO_CONTROL->value)->exists())->toBeFalse();

    $auditoria = Auditoria::query()->where('accion', 'numero_control_asignado')->firstOrFail();
    expect($auditoria->datos['numero_control'])->toBe('20250042')
        ->and($auditoria->datos['conservado'])->toBe(true);
});

test('F16: las inscripciones posteriores conservan el mismo numero de control', function () {
    $e = escenarioFinalizacionF12();
    $alumno = $e['inscripcion']->alumno;
    $primero = finalizarF12($e['inscripcion'], $e['control'])->alumno->numero_control;

    // Segundo periodo real (otro año): el NC es permanente para el alumno.
    $periodo2027 = Periodo::create([
        'clave' => 'PER-2027B-F12',
        'nombre' => 'Periodo adicional 2027 (Fase 12)',
        'fecha_inicio' => '2027-08-01',
        'fecha_fin' => '2027-12-18',
        'activo' => true,
        'cerrado_at' => null,
    ]);
    $e2 = escenarioFinalizacionF12(['alumno' => $alumno, 'periodo' => $periodo2027]);
    $segundo = finalizarF12($e2['inscripcion'], $e2['control'])->alumno->numero_control;

    expect($primero)->toBe('20260001')
        ->and($segundo)->toBe($primero)
        ->and(Consecutivo::query()->where('anio', 2027)->exists())->toBeFalse();

    $auditorias = Auditoria::query()
        ->where('accion', 'numero_control_asignado')
        ->orderBy('id')
        ->get();
    expect($auditorias)->toHaveCount(2)
        ->and($auditorias[0]->datos['conservado'])->toBe(false)
        ->and($auditorias[1]->datos['conservado'])->toBe(true);
});

test('F16: un convenio APROBADO no bloquea la finalizacion', function () {
    $e = escenarioFinalizacionF12(['convenio' => 'aprobado']);

    expect($e['inscripcion']->convenio_estatus)->toBe(EstatusConvenio::APROBADO);

    $finalizada = finalizarF12($e['inscripcion'], $e['control']);

    expect($finalizada->estatus)->toBe(InscripcionEstatus::ENROLLED)
        ->and($finalizada->alumno->numero_control)->toBe('20260001');
});

/*|--------------------------------------------------------------------------
| F17 — Lo que NO bloquea la finalización
|--------------------------------------------------------------------------*/

test('F17: las mensualidades pendientes no bloquean y quedan intactas tras ENROLLED', function () {
    $e = escenarioFinalizacionF12();

    $mensualidades = $e['inscripcion']->conceptosPago()->where('tipo', 'MENSUALIDAD')->get();
    expect($mensualidades)->toHaveCount(4)
        ->and($mensualidades->every(fn ($m): bool => $m->estatus === EstatusConceptoPago::PENDIENTE))->toBeTrue()
        ->and($e['inscripcion']->conceptoInscripcion->estatus)->toBe(EstatusConceptoPago::PAGADO);

    $finalizada = finalizarF12($e['inscripcion'], $e['control']);

    $tras = $e['inscripcion']->conceptosPago()->where('tipo', 'MENSUALIDAD')->get();
    expect($finalizada->estatus)->toBe(InscripcionEstatus::ENROLLED)
        ->and($tras)->toHaveCount(4)
        ->and($tras->every(fn ($m): bool => $m->estatus === EstatusConceptoPago::PENDIENTE))->toBeTrue()
        ->and($tras->every(fn ($m): bool => $m->pagado_at === null))->toBeTrue()
        ->and($e['inscripcion']->conceptoInscripcion->refresh()->estatus)->toBe(EstatusConceptoPago::PAGADO);
});

test('F17: observaciones GENERAL, DOCUMENTO y PAGO abiertas no bloquean', function () {
    $e = escenarioFinalizacionF12();

    crearObservacionF12($e['inscripcion'], $e['admisiones'], TipoObservacion::GENERAL, 'Nota general que no bloquea el cierre');
    crearObservacionF12($e['inscripcion'], $e['admisiones'], TipoObservacion::DOCUMENTO, 'Observacion de documento que no bloquea');
    crearObservacionF12($e['inscripcion'], $e['admisiones'], TipoObservacion::PAGO, 'Observacion de pago que no bloquea');

    $finalizada = finalizarF12($e['inscripcion'], $e['control']);

    expect($finalizada->estatus)->toBe(InscripcionEstatus::ENROLLED)
        ->and($finalizada->alumno->numero_control)->toBe('20260001')
        ->and($e['inscripcion']->observaciones()->where('estatus', 'ABIERTA')->count())->toBe(3);
});

/*|--------------------------------------------------------------------------
| F18 — Bloqueos independientes con ROLLBACK total
|--------------------------------------------------------------------------*/

test('F18: el concepto INSCRIPCION no PAGADO bloquea', function () {
    $e = escenarioFinalizacionF12();

    $concepto = $e['inscripcion']->conceptoInscripcion;
    $concepto->estatus = EstatusConceptoPago::PENDIENTE;
    $concepto->save();

    expectNoFinalizaF12($e['inscripcion'], $e['control'], 'concepto Inscripción no está Pagado');
});

test('F18: un documento obligatorio ausente bloquea', function () {
    $e = escenarioFinalizacionF12();

    // El catálogo crece tras llegar a FINAL_REVIEW: el nuevo obligatorio no
    // tiene documento ⇒ la guarda RB-8.2 exige que exista ACEPTADO.
    tipoDocumentoF12('COMPROBANTE_DOM');

    expectNoFinalizaF12($e['inscripcion'], $e['control'], 'documentos obligatorios');
});

test('F18: un documento obligatorio PENDIENTE bloquea', function () {
    $e = escenarioFinalizacionF12();

    $e['documento']->estatus = EstatusDocumento::PENDIENTE;
    $e['documento']->save();

    expectNoFinalizaF12($e['inscripcion'], $e['control'], 'documentos obligatorios');
});

test('F18: un documento obligatorio RECHAZADO bloquea', function () {
    $e = escenarioFinalizacionF12();

    $e['documento']->estatus = EstatusDocumento::RECHAZADO;
    $e['documento']->save();

    expectNoFinalizaF12($e['inscripcion'], $e['control'], 'documentos obligatorios');
});

test('F18: una observacion FALTANTE abierta bloquea', function () {
    $e = escenarioFinalizacionF12();

    crearObservacionF12($e['inscripcion'], $e['admisiones'], TipoObservacion::FALTANTE, 'Falta acta de nacimiento original para el expediente');

    expectNoFinalizaF12($e['inscripcion'], $e['control'], 'observaciones de tipo Faltante');
});

test('F18: un convenio PENDIENTE_APROBACION bloquea', function () {
    $e = escenarioFinalizacionF12();

    // La inscripción nunca llegaría aquí con convenio pendiente (los cargos
    // lo bloquean): se fija el estado de BD que RB-8 debe rechazar.
    $e['inscripcion']->forceFill([
        'convenio_id' => convenioF12()->getKey(),
        'convenio_estatus' => EstatusConvenio::PENDIENTE_APROBACION->value,
    ])->save();

    expectNoFinalizaF12($e['inscripcion'], $e['control'], 'Pendiente de aprobación');
});

test('F16: un convenio RECHAZADO se considera resuelto y no bloquea la finalizacion', function () {
    // Decisión aprobada Fase 12: RECHAZADO = resuelto SIN descuento (la Fase 7
    // ya permite generar cargos con RECHAZADO); bloquearlo aquí dejaría un
    // estado imposible de resolver. Solo PENDIENTE_APROBACION bloquea.
    $e = escenarioFinalizacionF12(['convenio' => 'rechazado']);

    expect($e['inscripcion']->estatus)->toBe(InscripcionEstatus::FINAL_REVIEW)
        ->and($e['inscripcion']->convenio_estatus)->toBe(EstatusConvenio::RECHAZADO);

    $finalizada = finalizarF12($e['inscripcion'], $e['control']);

    expect($finalizada->estatus)->toBe(InscripcionEstatus::ENROLLED)
        ->and($finalizada->alumno->numero_control)->toBe('20260001');
});

test('F16: el convenio RECHAZADO conserva monto_descuento = 0 y los cargos intactos', function () {
    $e = escenarioFinalizacionF12(['convenio' => 'rechazado']);
    $inscripcion = $e['inscripcion'];

    // RECHAZADO ⇒ sin descuento: montos originales de la inscripción.
    expect($inscripcion->monto_descuento)->toBe('0.00')
        ->and($inscripcion->monto_inscripcion)->toBe('1500.00');

    $concepto = $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail();
    $mensualidades = $inscripcion->conceptosPago()->where('tipo', 'MENSUALIDAD')->get();

    expect($concepto->monto)->toBe('1500.00')
        ->and($concepto->descuento)->toBe('0.00')
        ->and($concepto->monto_total)->toBe('1500.00')
        ->and($mensualidades)->toHaveCount(4)
        ->and($mensualidades->every(fn ($m): bool => $m->monto_total === '1200.00' && $m->descuento === '0.00'))->toBeTrue();

    // La finalización NO recalcula descuentos ni modifica los cargos.
    finalizarF12($inscripcion, $e['control']);

    $conceptoTras = $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail();
    $mensualidadesTras = $inscripcion->conceptosPago()->where('tipo', 'MENSUALIDAD')->get();

    expect($inscripcion->refresh()->monto_descuento)->toBe('0.00')
        ->and($conceptoTras->monto)->toBe('1500.00')
        ->and($conceptoTras->descuento)->toBe('0.00')
        ->and($conceptoTras->monto_total)->toBe('1500.00')
        ->and($conceptoTras->estatus)->toBe(EstatusConceptoPago::PAGADO)
        ->and($mensualidadesTras)->toHaveCount(4)
        ->and($mensualidadesTras->every(fn ($m): bool => $m->monto_total === '1200.00' && $m->descuento === '0.00' && $m->estatus === EstatusConceptoPago::PENDIENTE))->toBeTrue();
});

test('F18: finalizar fuera de FINAL_REVIEW se rechaza explicitamente', function () {
    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::DATA_COMPLETE)->create();
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    expectNoFinalizaF12($inscripcion, $control, 'La finalización solo procede desde Revisión final');
});

test('F18: ENROLLED es terminal — repetir finalizar no genera otro numero', function () {
    $e = escenarioFinalizacionF12();
    finalizarF12($e['inscripcion'], $e['control']);

    auth()->setUser($e['control']);
    try {
        expect(fn () => app(InscripcionService::class)->finalizar($e['inscripcion'], $e['control']))
            ->toThrow(FinalizacionInvalidaException::class, 'Inscripción completada');
    } finally {
        auth()->logout();
    }

    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::ENROLLED)
        ->and($e['inscripcion']->alumno->numero_control)->toBe('20260001')
        ->and(Consecutivo::firstOrFail()->ultimo_valor)->toBe(1)
        ->and(Auditoria::query()->where('accion', 'numero_control_asignado')->count())->toBe(1);
});

/*|--------------------------------------------------------------------------
| Rollback total (sin mocks)
|--------------------------------------------------------------------------*/

test('rollback: si falla la auditoria de la transicion no quedan NC, consecutivo ni ENROLLED', function () {
    $e = escenarioFinalizacionF12();
    $previas = Auditoria::query()->count();

    // Fallo REAL en la escritura de `auditoria` (evento del modelo real): el
    // número de control YA se asignó dentro de la transacción ⇒ ROLLBACK
    // completo del NC, del consecutivo y de la transición a ENROLLED.
    Auditoria::creating(function (): void {
        throw new RuntimeException('Fallo real al escribir la auditoria F12');
    });

    try {
        auth()->setUser($e['control']);
        expect(fn () => app(InscripcionService::class)->finalizar($e['inscripcion'], $e['control']))
            ->toThrow(RuntimeException::class, 'Fallo real al escribir la auditoria');
    } finally {
        auth()->logout();
        Auditoria::flushEventListeners();   // se limpia el gancho para el resto de la suite
    }

    $e['inscripcion']->refresh();
    expect($e['inscripcion']->estatus)->toBe(InscripcionEstatus::FINAL_REVIEW)
        ->and($e['inscripcion']->finalizada_at)->toBeNull()
        ->and($e['inscripcion']->validada_por)->toBeNull()
        ->and($e['inscripcion']->alumno->numero_control)->toBeNull()
        ->and(Consecutivo::query()->where('tipo', TipoConsecutivo::NUMERO_CONTROL->value)->exists())->toBeFalse()
        ->and(Auditoria::query()->count())->toBe($previas);
});

/*|--------------------------------------------------------------------------
| Autorización (matriz §8.2 / F8)
|--------------------------------------------------------------------------*/

test('autorizacion: ADMIN puede finalizar', function () {
    $e = escenarioFinalizacionF12();

    $finalizada = finalizarF12($e['inscripcion'], $e['admin']);

    expect($finalizada->estatus)->toBe(InscripcionEstatus::ENROLLED)
        ->and($finalizada->alumno->numero_control)->toBe('20260001')
        ->and($finalizada->validada_por)->toBe($e['admin']->id);
});

test('autorizacion: ADMISIONES y ADMINISTRACION reciben 403 y nada cambia', function () {
    $e = escenarioFinalizacionF12();

    foreach (['admisiones', 'administracion'] as $rol) {
        auth()->setUser($e[$rol]);
        try {
            expect(fn () => app(InscripcionService::class)->finalizar($e['inscripcion'], $e[$rol]))
                ->toThrow(AuthorizationException::class);
        } finally {
            auth()->logout();
        }
    }

    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::FINAL_REVIEW)
        ->and($e['inscripcion']->alumno->numero_control)->toBeNull()
        ->and(Consecutivo::query()->exists())->toBeFalse()
        ->and(Auditoria::query()->where('estado_nuevo', 'ENROLLED')->count())->toBe(0);
});

test('autorizacion: un usuario inactivo recibe 403 al finalizar', function () {
    $e = escenarioFinalizacionF12();
    $inactivo = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->inactivo()->create();

    auth()->setUser($inactivo);
    try {
        expect(fn () => app(InscripcionService::class)->finalizar($e['inscripcion'], $inactivo))
            ->toThrow(AuthorizationException::class);
    } finally {
        auth()->logout();
    }

    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::FINAL_REVIEW)
        ->and($e['inscripcion']->alumno->numero_control)->toBeNull()
        ->and(Consecutivo::query()->exists())->toBeFalse();
});

/*|--------------------------------------------------------------------------
| PanelEstados (UI)
|--------------------------------------------------------------------------*/

test('UI: PanelEstados muestra los requisitos y el boton solo a roles autorizados', function () {
    $e = escenarioFinalizacionF12();

    $this->actingAs($e['control']);
    Livewire::test(PanelEstados::class, ['inscripcion' => $e['inscripcion']])
        ->assertSee('Revisión final')
        ->assertSee('Pago del concepto de inscripción')
        ->assertSee('Documentos obligatorios')
        ->assertSee('Observaciones de tipo Faltante abiertas')
        ->assertSee('Convenio')
        ->assertSee('Se asigna al finalizar')
        ->assertSee('Finalizar inscripción');
    auth()->logout();

    // ADMISIONES puede leer el panel, pero sin el botón de cierre (@can).
    $this->actingAs($e['admisiones']);
    Livewire::test(PanelEstados::class, ['inscripcion' => $e['inscripcion']])
        ->assertSee('Pago del concepto de inscripción')
        ->assertDontSee('Finalizar inscripción');
    auth()->logout();
});

test('UI: finalizar desde PanelEstados cierra ENROLLED y muestra el numero de control', function () {
    $e = escenarioFinalizacionF12();

    $this->actingAs($e['control']);
    Livewire::test(PanelEstados::class, ['inscripcion' => $e['inscripcion']])
        ->call('finalizar')
        ->assertHasNoErrors()
        ->assertSee('Inscripción completada')
        ->assertSee('20260001');
    auth()->logout();

    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::ENROLLED)
        ->and($e['inscripcion']->alumno->numero_control)->toBe('20260001');
});

test('UI: con requisitos faltantes el llamado forzado falla server-side', function () {
    $e = escenarioFinalizacionF12();
    crearObservacionF12($e['inscripcion'], $e['admisiones'], TipoObservacion::FALTANTE, 'Falta acta de nacimiento original');

    $this->actingAs($e['control']);
    Livewire::test(PanelEstados::class, ['inscripcion' => $e['inscripcion']])
        ->call('finalizar')
        ->assertHasErrors('panel')
        ->assertSee('Requisitos de la revisión final incompletos')
        ->assertDontSee('Inscripción completada');
    auth()->logout();

    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::FINAL_REVIEW)
        ->and($e['inscripcion']->alumno->numero_control)->toBeNull()
        ->and(Consecutivo::query()->exists())->toBeFalse();
});

test('UI: un rol sin permiso no puede forzar finalizar desde el componente', function () {
    $e = escenarioFinalizacionF12();

    // El método se fuerza por Livewire, pero la autorización server-side
    // responde 403 y nada se ejecuta.
    $this->actingAs($e['admisiones']);
    Livewire::test(PanelEstados::class, ['inscripcion' => $e['inscripcion']])
        ->call('finalizar')
        ->assertStatus(403);
    auth()->logout();

    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::FINAL_REVIEW)
        ->and($e['inscripcion']->alumno->numero_control)->toBeNull()
        ->and(Consecutivo::query()->exists())->toBeFalse();
});

test('UI: la ruta de revision final exige sesion activa', function () {
    $e = escenarioFinalizacionF12();

    $this->get(route('inscripciones.revision-final', $e['inscripcion']))
        ->assertRedirect(route('login'));

    $this->actingAs($e['control'])
        ->get(route('inscripciones.revision-final', $e['inscripcion']))
        ->assertOk();
    auth()->logout();
});
