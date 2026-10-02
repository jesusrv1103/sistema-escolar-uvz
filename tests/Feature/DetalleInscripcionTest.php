<?php

use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Livewire\Inscripciones\DetalleInscripcion;
use App\Livewire\Inscripciones\ListaInscripciones;
use App\Livewire\Inscripciones\WizardInscripcion;
use App\Models\Alumno;
use App\Models\Auditoria;
use App\Models\ConceptoPago;
use App\Models\Convenio;
use App\Models\Documento;
use App\Models\InformacionMedica;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Fase 14 — Detalle operativo de la inscripción (lectura)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| Cubren: estado general con hitos, convenio legible (no aplica / estados /
| quién aplicó y aprobó), pagos con folio y enlaces con policy, documentos
| sin exponer archivos, historial de auditoría (ADMIN, orden inverso,
| append-only y sin payload sensible), privacidad (RB-18), autorización
| server-side, número de control en el listado y la integración en la
| vista existente SIN rutas nuevas.
| Sin mocks: la auditoría real es la de AuditoriaService.
*/

/*|--------------------------------------------------------------------------
| Helpers F14 (sufijo de fase para no colisionar con la suite)
|--------------------------------------------------------------------------*/

/**
 * Escenario de lectura: 4 roles + inscripción ENROLLED con número de
 * control, hitos, 3 conceptos con pagos en los 3 estatus, 3 documentos en
 * los 3 estatus, 2 auditorías reales (la segunda con payload sensible) e
 * información médica marcada que NUNCA debe aparecer.
 *
 * @return array<string, mixed>
 */
function escenarioDetalleF14(): array
{
    Storage::fake('private');

    $usuarios = [
        'admin' => User::factory()->deRol(RolUsuario::ADMIN)->create(),
        'admisiones' => User::factory()->deRol(RolUsuario::ADMISIONES)->create(),
        'administracion' => User::factory()->deRol(RolUsuario::ADMINISTRACION)->create(),
        'control' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create(),
    ];

    $alumno = Alumno::factory()->create(['numero_control' => '20260141']);

    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::ENROLLED)->create([
        'alumno_id' => $alumno->id,
        'datos_completados_at' => now()->subDays(5),
        'pagos_validados_at' => now()->subDays(4),
        'documentos_validados_at' => now()->subDays(3),
        'finalizada_at' => now()->subDays(2),
        'validada_por' => $usuarios['control']->id,
    ]);

    // Conceptos: INSCRIPCION + 2 mensualidades.
    $conceptoInscripcion = ConceptoPago::factory()->inscripcion()->create(['inscripcion_id' => $inscripcion->id]);
    $mensualidad1 = ConceptoPago::factory()->create(['inscripcion_id' => $inscripcion->id, 'numero' => 1]);
    $mensualidad2 = ConceptoPago::factory()->create(['inscripcion_id' => $inscripcion->id, 'numero' => 2]);

    // Pagos en los 3 estatus (fábrica de escenarios; la lógica es de PagoService).
    $pagoValidado = Pago::factory()->create([
        'concepto_pago_id' => $conceptoInscripcion->id,
        'estatus' => 'VALIDADO',
        'folio' => 'PAG-20261002-00000141',
        'metodo' => 'TRANSFERENCIA',
        'referencia' => 'REF-F14-9988',
        'fecha_pago' => now()->subDays(6)->toDateString(),
        'validado_por' => $usuarios['administracion']->id,
        'validado_at' => now()->subDays(6),
        'comprobante_ruta' => 'pagos/ruta-privada-comprobante-f14.pdf',
        'comprobante_nombre_original' => 'comprobante-original-f14.pdf',
    ]);
    $pagoPendiente = Pago::factory()->create(['concepto_pago_id' => $mensualidad1->id]);
    $pagoRechazado = Pago::factory()->rechazado('Referencia F14 no localizada')
        ->create(['concepto_pago_id' => $mensualidad2->id]);

    // Documentos en los 3 estatus (dos obligatorios y uno opcional).
    $documentos = collect([
        documentoDetalleF14($inscripcion, $usuarios, 'ACEPTADO', 'F14D_ACTA', true),
        documentoDetalleF14($inscripcion, $usuarios, 'PENDIENTE', 'F14D_COMPROBANTE', true),
        documentoDetalleF14($inscripcion, $usuarios, 'RECHAZADO', 'F14D_EXTRA', false),
    ]);

    // 2 auditorías REALES (la más reciente contiene un payload sensible que
    // jamás debe mostrarse en la vista).
    auth()->setUser($usuarios['admisiones']);
    $inscripcion->registrarAuditoria(
        'estado_cambiado',
        ['estatus' => 'DRAFT'],
        ['estatus' => 'DATA_COMPLETE'],
        ['origen' => 'wizard'],
    );
    auth()->logout();

    auth()->setUser($usuarios['administracion']);
    $inscripcion->registrarAuditoria('pago_validado', [], [], [
        'ruta_interna' => '/var/private/payload-secreto-f14.pdf',
    ]);
    auth()->logout();

    // Dato médico marcado: prohibido en dashboard, listados y detalle (RB-18).
    InformacionMedica::factory()->create([
        'alumno_id' => $alumno->id,
        'alergias' => 'ALERGIA-F14-NO-MOSTRAR',
    ]);

    return $usuarios + [
        'alumno' => $alumno,
        'inscripcion' => $inscripcion,
        'pagoValidado' => $pagoValidado,
        'pagoPendiente' => $pagoPendiente,
        'pagoRechazado' => $pagoRechazado,
        'documentos' => $documentos,
    ];
}

/**
 * Documento sintético con estatus directo (la Fase 14 solo LEE): sin
 * llamadas a DocumentoService para no mezclar flujos de la Fase 10.
 */
function documentoDetalleF14(Inscripcion $inscripcion, array $usuarios, string $estatus, string $clave, bool $obligatorio): Documento
{
    $tipo = TipoDocumento::create([
        'clave' => $clave,
        'nombre' => 'Documento '.$clave,
        'obligatorio' => $obligatorio,
        'activo' => true,
    ]);

    $documento = new Documento();
    $documento->forceFill([
        'inscripcion_id' => $inscripcion->id,
        'tipo_documento_id' => $tipo->id,
        'ruta' => 'docs/ruta-privada-'.$clave.'.pdf',
        'nombre_original' => 'original-'.$clave.'.pdf',
        'mime' => 'application/pdf',
        'tamano_kb' => 15,
        'subido_por' => $usuarios['admisiones']->id,
        'estatus' => $estatus,
        'validado_por' => $estatus === 'PENDIENTE' ? null : $usuarios['control']->id,
        'validado_at' => $estatus === 'PENDIENTE' ? null : now()->subDay(),
        'motivo_rechazo' => $estatus === 'RECHAZADO' ? 'No legible F14' : null,
    ])->save();

    return $documento;
}

/** Renderiza el detalle con el usuario dado y deja la sesión autenticada. */
function detalleF14Como(User $usuario, Inscripcion $inscripcion): object
{
    auth()->setUser($usuario);

    return Livewire::test(DetalleInscripcion::class, ['inscripcion' => $inscripcion]);
}

/*|--------------------------------------------------------------------------
| Estado general (§14) y cancelación (§20)
|--------------------------------------------------------------------------*/

test('el detalle muestra estado general con alumno, control, programa, periodo, estatus e hitos', function () {
    $e = escenarioDetalleF14();

    detalleF14Como($e['admin'], $e['inscripcion'])
        ->assertSee('Estado general')
        ->assertSee($e['alumno']->apellidos)
        ->assertSee('20260141')
        ->assertSee($e['inscripcion']->programa->nombre)
        ->assertSee($e['inscripcion']->periodo->nombre)
        ->assertSee('ENROLLED')
        // Hitos ya almacenados, sin estados derivados inventados.
        ->assertSee('Datos completos')
        ->assertSee('Pagos validados')
        ->assertSee('Documentos validados')
        ->assertSee('Finalizada')
        ->assertSee($e['control']->name)
        // Sin cancelación: no debe aparecer el bloque de cancelada.
        ->assertDontSee('Inscripción cancelada');

    auth()->logout();
});

test('el detalle muestra la cancelacion con fecha, responsable y motivo', function () {
    $e = escenarioDetalleF14();

    $cancelada = Inscripcion::factory()->enEstatus(InscripcionEstatus::CANCELLED)->create([
        'cancelada_at' => now()->subHour(),
        'cancelada_por' => $e['admin']->id,
        'cancelacion_motivo' => 'La familia se muda de país (motivo F14)',
    ]);

    detalleF14Como($e['admin'], $cancelada)
        ->assertSee('Inscripción cancelada')
        ->assertSee('La familia se muda de país (motivo F14)')
        ->assertSee($e['admin']->name)
        ->assertSee('no hay reapertura en el MVP')
        ->assertDontSee('Finalizada');

    auth()->logout();
});

/*|--------------------------------------------------------------------------
| Convenio (§15)
|--------------------------------------------------------------------------*/

test('el convenio se muestra legible: no aplica, estados y quien aplico/aprobo', function () {
    $e = escenarioDetalleF14();

    // Sin convenio → "No aplica".
    detalleF14Como($e['admin'], $e['inscripcion'])
        ->assertSee('Convenio')
        ->assertSee('No aplica (sin convenio')
        ->assertDontSee('PENDIENTE_APROBACION');
    auth()->logout();

    $convenio = Convenio::create([
        'clave' => 'F14-PROMO',
        'nombre' => 'Promoción F14',
        'tipo' => 'MONTO',
        'valor' => '200.00',
        'activo' => true,
    ]);

    // APROBADO con snapshot y responsables.
    $e['inscripcion']->forceFill([
        'convenio_id' => $convenio->id,
        'convenio_estatus' => 'APROBADO',
        'monto_descuento' => '200.00',
        'convenio_aplicado_por' => $e['admisiones']->id,
        'convenio_aplicado_at' => now()->subDays(2),
        'convenio_aprobado_por' => $e['administracion']->id,
        'convenio_aprobado_at' => now()->subDay(),
    ])->save();

    detalleF14Como($e['admin'], $e['inscripcion'])
        ->assertSee('APROBADO')
        ->assertSee('Promoción F14')
        ->assertSee('Descuento aplicado: $200.00')
        ->assertSee('Aplicado por '.$e['admisiones']->name)
        ->assertSee('Aprobado por '.$e['administracion']->name)
        ->assertDontSee('No aplica');
    auth()->logout();

    // PENDIENTE_APROBACION (nunca tuvo aprobación: se limpia el hito).
    $e['inscripcion']->forceFill([
        'convenio_estatus' => 'PENDIENTE_APROBACION',
        'convenio_aprobado_por' => null,
        'convenio_aprobado_at' => null,
    ])->save();
    detalleF14Como($e['admin'], $e['inscripcion'])
        ->assertSee('PENDIENTE_APROBACION')
        ->assertDontSee('Aprobado por ');
    auth()->logout();

    // RECHAZADO con motivo legible.
    $e['inscripcion']->forceFill([
        'convenio_estatus' => 'RECHAZADO',
        'convenio_motivo' => 'Documento de promoción no vigente F14',
    ])->save();
    detalleF14Como($e['admin'], $e['inscripcion'])
        ->assertSee('RECHAZADO')
        ->assertSee('Documento de promoción no vigente F14');
    auth()->logout();
});

/*|--------------------------------------------------------------------------
| Pagos (§16)
|--------------------------------------------------------------------------*/

test('el detalle lista los pagos con folio, metodo, estatus y enlaces con policy sin rutas privadas', function () {
    $e = escenarioDetalleF14();

    detalleF14Como($e['administracion'], $e['inscripcion'])
        ->assertSee('Pagos')
        // VALIDADO: folio, método, referencia, fecha y validador.
        ->assertSee('PAG-20261002-00000141')
        ->assertSee('TRANSFERENCIA')
        ->assertSee('REF-F14-9988')
        ->assertSee(now()->subDays(6)->format('d/m/Y'))
        ->assertSee($e['administracion']->name)
        // PENDIENTE y RECHAZADO con su motivo (historial conservado).
        ->assertSee('PENDIENTE')
        ->assertSee('RECHAZADO')
        ->assertSee('Referencia F14 no localizada')
        // Enlaces a comprobante y PDF por las rutas existentes con policy.
        ->assertSee('Ver comprobante')
        ->assertSee(route('pagos.comprobante', $e['pagoValidado']), false)
        ->assertSee('Comprobante PDF')
        ->assertSee(route('pagos.comprobante-pdf', $e['pagoValidado']), false)
        // Jamás rutas privadas ni nombres internos de archivo.
        ->assertDontSee('ruta-privada-comprobante-f14')
        ->assertDontSee('comprobante-original-f14.pdf');

    auth()->logout();
});

/*|--------------------------------------------------------------------------
| Documentos (§17)
|--------------------------------------------------------------------------*/

test('el detalle lista documentos con tipo, obligatorio, estatus y validacion sin exponer archivos', function () {
    $e = escenarioDetalleF14();

    detalleF14Como($e['control'], $e['inscripcion'])
        ->assertSee('Documentos')
        ->assertSee('Documento F14D_ACTA')
        ->assertSee('Documento F14D_EXTRA')
        ->assertSee('Obligatorio')
        ->assertSee('Opcional')
        ->assertSee('ACEPTADO')
        ->assertSee('PENDIENTE')
        ->assertSee('RECHAZADO')
        ->assertSee('No legible F14')
        // Acciones: se reutiliza el panel existente (sin segunda implementación).
        ->assertSee(route('inscripciones.documentos', $e['inscripcion']), false)
        // Sin path privado, sin UUID interno ni nombre original.
        ->assertDontSee('ruta-privada-F14D_ACTA')
        ->assertDontSee('original-F14D_ACTA.pdf');

    auth()->logout();
});

/*|--------------------------------------------------------------------------
| Historial de auditoría (§18)
|--------------------------------------------------------------------------*/

test('el historial de auditoria se muestra para ADMIN en orden inverso y sin payload tecnico', function () {
    $e = escenarioDetalleF14();

    $campos = ['id', 'accion', 'estado_anterior', 'estado_nuevo', 'datos', 'user_id'];
    $antes = Auditoria::query()->orderBy('id')->get($campos)->toArray();

    detalleF14Como($e['admin'], $e['inscripcion'])
        ->assertSee('Historial de auditoría')
        // Más reciente primero (pago_validado fue el segundo registro).
        ->assertSeeInorder(['pago_validado', 'estado_cambiado'])
        // Usuario responsable y cambios de estado.
        ->assertSee($e['admisiones']->name)
        ->assertSee($e['administracion']->name)
        ->assertSee('DRAFT')
        ->assertSee('DATA_COMPLETE')
        // El payload `datos` (aunque no sea sensible) NUNCA se muestra crudo.
        ->assertDontSee('payload-secreto-f14')
        ->assertDontSee('ruta_interna');

    auth()->logout();

    // Append-only: renderizar el detalle no crea, modifica ni elimina nada.
    $despues = Auditoria::query()->orderBy('id')->get($campos)->toArray();
    expect($despues)->toEqual($antes)
        ->and(Auditoria::query()->count())->toBe(2);
});

test('el historial de auditoria no esta disponible para los otros 3 roles', function () {
    $e = escenarioDetalleF14();

    $porRol = [$e['admisiones'], $e['administracion'], $e['control']];

    foreach ($porRol as $usuario) {
        // La capacidad auditoria.view (§8.2) es solo de ADMIN: ni la sección
        // ni la auditoría aparecen; el resto del detalle sigue siendo lectura.
        detalleF14Como($usuario, $e['inscripcion'])
            ->assertSee('Estado general')
            ->assertSee('Pagos')
            ->assertDontSee('Historial de auditoría')
            ->assertDontSee('estado_cambiado')
            ->assertDontSee('pago_validado');
        auth()->logout();
    }
});

/*|--------------------------------------------------------------------------
| Privacidad (§19 / RB-18)
|--------------------------------------------------------------------------*/

test('privacidad: el detalle no expone informacion medica, hashes ni rutas privadas', function () {
    $e = escenarioDetalleF14();

    // ADMINISTRACION: ve el detalle operativo pero sin nada sensible.
    detalleF14Como($e['administracion'], $e['inscripcion'])
        ->assertDontSee('ALERGIA-F14-NO-MOSTRAR')
        ->assertDontSee($e['admisiones']->password)
        ->assertDontSee('ruta-privada-comprobante-f14')
        ->assertDontSee('ruta-privada-F14D_ACTA')
        ->assertDontSee('payload-secreto-f14')
        ->assertDontSee('Historial de auditoría');
    auth()->logout();

    // ADMIN (único que ve historial) tampoco obtiene información médica ni hashes.
    detalleF14Como($e['admin'], $e['inscripcion'])
        ->assertSee('Historial de auditoría')
        ->assertDontSee('ALERGIA-F14-NO-MOSTRAR')
        ->assertDontSee($e['admisiones']->password)
        ->assertDontSee('ruta-privada-comprobante-f14');
    auth()->logout();
});

/*|--------------------------------------------------------------------------
| Autorización
|--------------------------------------------------------------------------*/

test('autorizacion: el detalle exige usuario activo y respeta la vista de la ruta', function () {
    $e = escenarioDetalleF14();

    $inactivo = User::factory()->deRol(RolUsuario::ADMIN)->inactivo()->create();
    $this->actingAs($inactivo);

    // Acceso Livewire forzado ⇒ 403 en mount() (server-side).
    Livewire::test(DetalleInscripcion::class, ['inscripcion' => $e['inscripcion']])
        ->assertStatus(403);

    // La página existente también bloquea al inactivo.
    $this->get(route('inscripciones.ver', $e['inscripcion']))->assertForbidden();
});

/*|--------------------------------------------------------------------------
| Listados e integración (§12 / §13)
|--------------------------------------------------------------------------*/

test('el listado operativo muestra el numero de control del alumno', function () {
    $e = escenarioDetalleF14();

    $this->actingAs($e['admisiones']);

    Livewire::test(ListaInscripciones::class)
        ->assertSee('20260141')
        ->assertSee($e['alumno']->curp)
        ->assertDontSee('ALERGIA-F14-NO-MOSTRAR');

    auth()->logout();
});

test('la vista de inscripcion existente integra el detalle sin rutas nuevas', function () {
    $e = escenarioDetalleF14();

    // La ruta actual responde OK (sin nueva ruta: el detalle se embebe).
    $this->actingAs($e['admin'])
        ->get(route('inscripciones.ver', $e['inscripcion']))
        ->assertOk();

    // El wizard renderiza el detalle completo (convención de la suite:
    // el contenido se verifica con Livewire::test).
    Livewire::test(WizardInscripcion::class, ['inscripcion' => $e['inscripcion']])
        ->assertSee('Estado general')
        ->assertSee('Pagos')
        ->assertSee('Documentos')
        ->assertSee('Historial de auditoría');

    auth()->logout();
});
