<?php

use App\Enums\InscripcionEstatus;
use App\Enums\MetodoPago;
use App\Enums\RolUsuario;
use App\Livewire\Documentos\PanelDocumentos;
use App\Livewire\Inscripciones\DetalleInscripcion;
use App\Livewire\Inscripciones\ListaInscripciones;
use App\Livewire\Inscripciones\PanelEstados;
use App\Livewire\Pagos\ModalRegistrarPago;
use App\Models\Auditoria;
use App\Models\ConceptoPago;
use App\Models\Convenio;
use App\Models\Documento;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Corrección transversal — interfaz completamente en español
|--------------------------------------------------------------------------
|
| 6 pruebas acordadas para la capa de PRESENTACIÓN:
|  1. El listado NO expone ENROLLED/CANCELLED visibles (strip_tags, porque
|     `value="ENROLLED"` permanece en el filtro).
|  2. Las opciones del filtro de estatus se muestran en español y
|     conservan el `value` técnico para la query.
|  3. Detalle y revisión presentan los estatus en español.
|  4. Pagos, documentos y convenios muestran etiquetas en español.
|  5. La auditoría traduce acciones y estados para presentación.
|  6. La base de datos conserva los valores técnicos intactos.
|
| Sin mocks y sin migraciones: solo presentación (docs/reglas-desarrollo.md §18).
|
*/

/**
 * Texto visible real de un HTML: sin etiquetas, sin <script> y con
 * espacios colapsados. Es la base para aseverar "sin valores técnicos
 * visibles" mientras `value="ENROLLED"` sigue existiendo en el HTML crudo.
 */
function textoVisibleUiEs(string $html): string
{
    $sinScripts = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);

    return trim(preg_replace('/\s+/', ' ', strip_tags($sinScripts)));
}

/**
 * Escenario de presentación: 4 roles, inscripción ENROLLED, 2 conceptos,
 * pagos VALIDADO/PENDIENTE/RECHAZADO, documentos ACEPTADO/RECHAZADO,
 * convenio APROBADO (PORCENTAJE) y 2 auditorías reales.
 *
 * @return array<string, mixed>
 */
function escenarioUiEs(): array
{
    Storage::fake('private');

    $usuarios = [
        'admin' => User::factory()->deRol(RolUsuario::ADMIN)->create(),
        'admisiones' => User::factory()->deRol(RolUsuario::ADMISIONES)->create(),
        'administracion' => User::factory()->deRol(RolUsuario::ADMINISTRACION)->create(),
        'control' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create(),
    ];

    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::ENROLLED)->create([
        'datos_completados_at' => now()->subDays(5),
        'pagos_validados_at' => now()->subDays(4),
        'documentos_validados_at' => now()->subDays(3),
        'finalizada_at' => now()->subDays(2),
        'validada_por' => $usuarios['control']->id,
    ]);

    $conceptoInscripcion = ConceptoPago::factory()->inscripcion()->create(['inscripcion_id' => $inscripcion->id]);
    $mensualidad1 = ConceptoPago::factory()->create(['inscripcion_id' => $inscripcion->id, 'numero' => 1]);

    $pagoValidado = Pago::factory()->create([
        'concepto_pago_id' => $conceptoInscripcion->id,
        'estatus' => 'VALIDADO',
        'folio' => 'PAG-20261002-00000201',
        'metodo' => 'TRANSFERENCIA',
        'referencia' => 'REF-UIES-9988',
        'fecha_pago' => now()->subDays(6)->toDateString(),
        'validado_por' => $usuarios['administracion']->id,
        'validado_at' => now()->subDays(6),
    ]);
    $pagoPendiente = Pago::factory()->create(['concepto_pago_id' => $mensualidad1->id]);
    $pagoRechazado = Pago::factory()->rechazado('Referencia UIES no localizada')
        ->create(['concepto_pago_id' => $mensualidad1->id]);

    $documentos = [
        documentoUiEs($inscripcion, $usuarios, 'ACEPTADO', 'UIES_ACTA', true),
        documentoUiEs($inscripcion, $usuarios, 'RECHAZADO', 'UIES_EXTRA', false),
    ];

    $convenio = Convenio::create([
        'clave' => 'UIES-PROMO',
        'nombre' => 'Promoción UIES',
        'tipo' => 'PORCENTAJE',
        'valor' => '15.00',
        'activo' => true,
    ]);

    $inscripcion->forceFill([
        'convenio_id' => $convenio->id,
        'convenio_estatus' => 'APROBADO',
        'monto_descuento' => '225.00',
        'convenio_aplicado_por' => $usuarios['admisiones']->id,
        'convenio_aplicado_at' => now()->subDays(3),
        'convenio_aprobado_por' => $usuarios['administracion']->id,
        'convenio_aprobado_at' => now()->subDays(2),
    ])->save();

    // 2 auditorías reales (la segunda con payload privado jamás visible).
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
        'ruta_interna' => '/var/private/payload-privada-tries.pdf',
    ]);
    auth()->logout();

    return $usuarios + [
        'inscripcion' => $inscripcion,
        'convenio' => $convenio,
        'conceptoInscripcion' => $conceptoInscripcion,
        'pagoValidado' => $pagoValidado,
        'pagoPendiente' => $pagoPendiente,
        'pagoRechazado' => $pagoRechazado,
        'documentos' => $documentos,
    ];
}

/**
 * Documento sintético con estatus directo (solo LEE; sin DocumentoService).
 */
function documentoUiEs(Inscripcion $inscripcion, array $usuarios, string $estatus, string $clave, bool $obligatorio): Documento
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
        'motivo_rechazo' => $estatus === 'RECHAZADO' ? 'No legible UIES' : null,
    ])->save();

    return $documento;
}

/*|--------------------------------------------------------------------------
| 1) Listado: sin ENROLLED/CANCELLED visibles
|--------------------------------------------------------------------------*/

test('el listado no expone ENROLLED ni CANCELLED: solo etiquetas en español', function () {
    $e = escenarioUiEs();
    Inscripcion::factory()->enEstatus(InscripcionEstatus::CANCELLED)->create();
    Inscripcion::factory()->enEstatus(InscripcionEstatus::DRAFT)->create();

    $this->actingAs($e['admisiones']);
    $listado = Livewire::test(ListaInscripciones::class);
    $html = $listado->html();
    $texto = textoVisibleUiEs($html);

    // Sin valores técnicos en el texto visible…
    expect($texto)->not->toContain('ENROLLED')
        ->and($texto)->not->toContain('CANCELLED')
        // …y con las etiquetas acordadas.
        ->and($texto)->toContain('Inscripción completada')
        ->and($texto)->toContain('Cancelada')
        // El value técnico permanece SOLO en el atributo del <option> y en
        // el wire:model del filtro (query/BD intactas).
        ->and($html)->toContain('<option value="ENROLLED">Inscripción completada</option>')
        ->and($html)->toContain('<option value="CANCELLED">Cancelada</option>')
        ->and($html)->toContain('wire:model.live="filtroEstatus"');
});

/*|--------------------------------------------------------------------------
| 2) Filtros en español
|--------------------------------------------------------------------------*/

test('las opciones del filtro de estatus muestran etiqueta española con value tecnico', function () {
    $e = escenarioUiEs();

    $this->actingAs($e['admisiones']);
    $html = Livewire::test(ListaInscripciones::class)->html();

    foreach (InscripcionEstatus::cases() as $caso) {
        expect($html)->toContain('<option value="'.$caso->value.'">'.$caso->label().'</option>')
            ->and($caso->label())->not->toBe($caso->value);
    }

    // El filtro activo sigue escribiendo el valor técnico en la URL.
    $filtrado = Livewire::test(ListaInscripciones::class)->set('filtroEstatus', 'ENROLLED');
    expect($filtrado->get('filtroEstatus'))->toBe('ENROLLED')
        ->and(textoVisibleUiEs($filtrado->html()))->not->toContain('ENROLLED');
});

/*|--------------------------------------------------------------------------
| 3) Detalle y revisión final
|--------------------------------------------------------------------------*/

test('el detalle y la revision final presentan los estatus en español', function () {
    $e = escenarioUiEs();

    $this->actingAs($e['admin']);
    $detalle = Livewire::test(DetalleInscripcion::class, ['inscripcion' => $e['inscripcion']])
        ->assertSee('Inscripción completada');

    $textoDetalle = textoVisibleUiEs($detalle->html());
    expect($textoDetalle)->not->toContain('ENROLLED')
        ->and($textoDetalle)->not->toContain('CANCELLED');
    auth()->logout();

    // Revisión final con un estatus intermedio: etiqueta, no valor técnico.
    $enDocumentos = Inscripcion::factory()->enEstatus(InscripcionEstatus::PENDING_DOCUMENTS)->create();

    $this->actingAs($e['control']);
    $revision = Livewire::test(PanelEstados::class, ['inscripcion' => $enDocumentos])
        ->assertSee('Estado actual: Documentos pendientes')
        ->assertSee('Pago del concepto de inscripción');

    $textoRevision = textoVisibleUiEs($revision->html());
    expect($textoRevision)->not->toContain('PENDING_DOCUMENTS')
        ->and($textoRevision)->not->toContain('FINAL_REVIEW')
        ->and($textoRevision)->not->toContain('ENROLLED');
    auth()->logout();
});

/*|--------------------------------------------------------------------------
| 4) Pagos, documentos y convenios
|--------------------------------------------------------------------------*/

test('pagos, documentos y convenios muestran etiquetas en español', function () {
    $e = escenarioUiEs();

    $this->actingAs($e['admin']);
    $detalle = Livewire::test(DetalleInscripcion::class, ['inscripcion' => $e['inscripcion']])
        ->assertSee('Transferencia')
        ->assertSee('Validado')
        ->assertSee('Pendiente')
        ->assertSee('Rechazado')
        ->assertSee('Aceptado')
        ->assertSee('Aprobado')
        ->assertSee('Porcentaje');

    $texto = textoVisibleUiEs($detalle->html());
    expect($texto)->not->toContain('TRANSFERENCIA')
        ->and($texto)->not->toContain('VALIDADO')
        ->and($texto)->not->toContain('PENDIENTE')
        ->and($texto)->not->toContain('RECHAZADO')
        ->and($texto)->not->toContain('ACEPTADO')
        ->and($texto)->not->toContain('APROBADO')
        ->and($texto)->not->toContain('PORCENTAJE');
    auth()->logout();

    // Modal de captura: opciones en español, value técnico intacto.
    $this->actingAs($e['admisiones']);
    $modal = Livewire::test(ModalRegistrarPago::class, ['inscripcion' => $e['inscripcion']])
        ->call('abrir');
    $htmlModal = $modal->html();

    foreach (MetodoPago::cases() as $metodo) {
        expect($htmlModal)->toContain('<option value="'.$metodo->value.'">'.$metodo->label().'</option>');
    }

    expect(textoVisibleUiEs($htmlModal))->not->toContain('TRANSFERENCIA')
        ->and(textoVisibleUiEs($htmlModal))->not->toContain('TARJETA')
        ->and(textoVisibleUiEs($htmlModal))->not->toContain('EFECTIVO');
    auth()->logout();

    // Panel de documentos: estatus visibles en español.
    $this->actingAs($e['control']);
    Livewire::test(PanelDocumentos::class, ['inscripcion' => $e['inscripcion']])
        ->assertSee('Aceptado')
        ->assertSee('Rechazado')
        ->assertSee('Inscripción completada');
    auth()->logout();
});

/*|--------------------------------------------------------------------------
| 5) Auditoría en presentación
|--------------------------------------------------------------------------*/

test('el historial de auditoria traduce acciones y estados a español', function () {
    $e = escenarioUiEs();

    $this->actingAs($e['admin']);
    $detalle = Livewire::test(DetalleInscripcion::class, ['inscripcion' => $e['inscripcion']])
        ->assertSee('Historial de auditoría')
        ->assertSee('Estado cambiado')
        ->assertSee('Pago validado')
        ->assertSee('Borrador')
        ->assertSee('Datos completos')
        // El payload privado jamás aparece (append-only sin exposición).
        ->assertDontSee('payload-privada-tries.pdf');

    $texto = textoVisibleUiEs($detalle->html());
    expect($texto)->not->toContain('estado_cambiado')
        ->and($texto)->not->toContain('pago_validado')
        ->and($texto)->not->toContain('DRAFT')
        ->and($texto)->not->toContain('DATA_COMPLETE');
    auth()->logout();
});

/*|--------------------------------------------------------------------------
| 6) La BD conserva los valores técnicos
|--------------------------------------------------------------------------*/

test('la presentacion en español no altera los valores tecnicos persistidos', function () {
    $e = escenarioUiEs();

    // Render de varias superficies: la traducción es solo presentación.
    $this->actingAs($e['admin']);
    Livewire::test(DetalleInscripcion::class, ['inscripcion' => $e['inscripcion']]);
    auth()->logout();

    $this->actingAs($e['admisiones']);
    Livewire::test(ListaInscripciones::class);
    auth()->logout();

    // Inscripción: enum técnico intacto y valor crudo en BD.
    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::ENROLLED)
        ->and($e['inscripcion']->estatus->value)->toBe('ENROLLED')
        ->and(DB::table('inscripciones')->where('id', $e['inscripcion']->id)->value('estatus'))->toBe('ENROLLED')
        ->and(DB::table('inscripciones')->where('id', $e['inscripcion']->id)->value('convenio_estatus'))->toBe('APROBADO');

    // Pagos, conceptos y documentos: valores técnicos sin traducir.
    expect(DB::table('pagos')->where('id', $e['pagoValidado']->id)->value('estatus'))->toBe('VALIDADO')
        ->and(DB::table('pagos')->where('id', $e['pagoValidado']->id)->value('metodo'))->toBe('TRANSFERENCIA')
        ->and(DB::table('pagos')->where('id', $e['pagoPendiente']->id)->value('estatus'))->toBe('PENDIENTE')
        ->and(DB::table('pagos')->where('id', $e['pagoRechazado']->id)->value('estatus'))->toBe('RECHAZADO')
        ->and(DB::table('conceptos_pago')->where('id', $e['conceptoInscripcion']->id)->value('tipo'))->toBe('INSCRIPCION')
        ->and(DB::table('conceptos_pago')->where('id', $e['conceptoInscripcion']->id)->value('estatus'))->toBe('PENDIENTE')
        ->and(DB::table('documentos')->where('id', $e['documentos'][0]->id)->value('estatus'))->toBe('ACEPTADO')
        ->and(DB::table('documentos')->where('id', $e['documentos'][1]->id)->value('estatus'))->toBe('RECHAZADO')
        ->and(DB::table('convenios')->where('id', $e['convenio']->id)->value('tipo'))->toBe('PORCENTAJE');

    // Auditoría: acciones y estados técnicos intactos (append-only).
    $auditorias = Auditoria::query()->orderBy('id')->get(['id', 'accion', 'estado_anterior', 'estado_nuevo', 'datos']);
    expect($auditorias)->toHaveCount(2)
        ->and($auditorias[0]->accion)->toBe('estado_cambiado')
        ->and($auditorias[0]->estado_anterior)->toBe('DRAFT')
        ->and($auditorias[0]->estado_nuevo)->toBe('DATA_COMPLETE')
        ->and($auditorias[1]->accion)->toBe('pago_validado');
});
