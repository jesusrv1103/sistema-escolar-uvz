<?php

use App\Enums\EstatusDocumento;
use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Exceptions\DocumentoInvalidoException;
use App\Livewire\Documentos\PanelDocumentos;
use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\DocumentoService;
use App\Services\InscripcionService;
use App\Services\PagoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Fase 10 — Documentos: FC-6/RB-7 + almacenamiento privado (R5)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| F13 (carga completa ⇒ DOCUMENT_REVIEW), F14 (obligatorio faltante bloquea),
| F15 (rechazo con motivo ⇒ PENDING_DOCUMENTS), upsert por
| (inscripcion_id, tipo_documento_id), reemplazo con compensación de
| archivos, MIME real (finfo) ≤ 10 MB en disco PRIVADO, autorización según
| matriz §8.2 y descarga con ruta resuelta desde BD (anti path-traversal).
| Sin mocks: servicios reales, transiciones reales, policies reales y FK de
| MySQL. `Storage::fake('private')` solo aisla el filesystem (se permite).
*/

/** PDF mínimo real: `finfo` lo detecta como application/pdf (%PDF-). */
function pdfF10(): string
{
    return "%PDF-1.4\n"
        ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n"
        ."trailer<</Root 1 0 R>>\n%%EOF\n";
}

/** JPEG mínimo real (SOI + JFIF): `finfo` lo detecta como image/jpeg. */
function jpgF10(): string
{
    return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";
}

/** PNG 1x1 real para probar MIME sin depender de GD. */
function pngF10(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

/** Crea un tipo en el catálogo (§4.11). */
function tipoDocumentoF10(string $clave, bool $obligatorio = true, bool $activo = true): TipoDocumento
{
    return TipoDocumento::create([
        'clave' => $clave,
        'nombre' => 'Documento '.$clave,
        'obligatorio' => $obligatorio,
        'activo' => $activo,
    ]);
}

/**
 * Escenario: 4 roles de la matriz §8.2 e inscripción llevada por el flujo
 * REAL (generarCargos → registrar → validar) hasta PENDING_DOCUMENTS.
 * Aisla `Storage::fake('private')` y limpia la sesión autenticada.
 *
 * @return array{admin: User, admisiones: User, administracion: User, control: User, inscripcion: Inscripcion}
 */
function escenarioDocumentosF10(): array
{
    Storage::fake('private');

    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::DATA_COMPLETE)->create();

    auth()->setUser($admisiones);
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

    auth()->logout();

    return [
        'admin' => $admin,
        'admisiones' => $admisiones,
        'administracion' => $administracion,
        'control' => $control,
        'inscripcion' => $inscripcion->refresh(),
    ];
}

/** Sube un archivo real vía el servicio SIEMPRE con el usuario indicado. */
function subirDocumentoF10(Inscripcion $inscripcion, TipoDocumento $tipo, User $usuario, string $contenido, string $nombre = 'expediente.pdf'): Documento
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

/**
 * Usuario inexistente en BD (id fantasma): al escribirlo en `subido_por`
 * revienta la FK real de MySQL dentro de la transacción — falla REAL para
 * probar ROLLBACK + compensación de archivos, sin mocks.
 */
function fantasmaF10(): User
{
    $fantasma = new User();
    $fantasma->forceFill([
        'id' => 99999999,
        'name' => 'Usuario fantasma F10',
        'email' => 'fantasma-f10@uvz.local',
    ]);

    return $fantasma;
}

/*|--------------------------------------------------------------------------
| F13 / F14 / F15 — escenarios del plan
|--------------------------------------------------------------------------
*/

test('F13: la carga completa de los obligatorios avanza PENDING_DOCUMENTS a DOCUMENT_REVIEW', function () {
    $e = escenarioDocumentosF10();
    expect($e['inscripcion']->estatus)->toBe(InscripcionEstatus::PENDING_DOCUMENTS);

    $acta = tipoDocumentoF10('ACTA_NAC');
    $curp = tipoDocumentoF10('CURP');

    $doc1 = subirDocumentoF10($e['inscripcion'], $acta, $e['admisiones'], pdfF10(), 'acta de nacimiento.pdf');
    $doc2 = subirDocumentoF10($e['inscripcion'], $curp, $e['admisiones'], pngF10(), 'curp.png');

    // Documentos creados, metadata correcta y todos PENDIENTE.
    expect(Documento::query()->count())->toBe(2)
        ->and($doc1->estatus)->toBe(EstatusDocumento::PENDIENTE)
        ->and($doc2->estatus)->toBe(EstatusDocumento::PENDIENTE)
        ->and($doc1->mime)->toBe('application/pdf')
        ->and($doc2->mime)->toBe('image/png')
        ->and($doc1->tamano_kb)->toBeGreaterThan(0)
        ->and($doc1->nombre_original)->toBe('acta de nacimiento.pdf')
        ->and($doc1->subido_por)->toBe($e['admisiones']->id)
        ->and($doc1->inscripcion_id)->toBe($e['inscripcion']->id);

    // Archivos en disco PRIVADO + inscripción avanza con auditoría.
    Storage::disk('private')->assertExists($doc1->ruta);
    Storage::disk('private')->assertExists($doc2->ruta);
    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::DOCUMENT_REVIEW)
        ->and(Auditoria::query()->where('accion', 'documento_subido')->count())->toBe(2)
        ->and(Auditoria::query()->where('accion', 'estado_cambiado')->where('estado_nuevo', 'DOCUMENT_REVIEW')->count())->toBe(1);
});

test('F14: un obligatorio faltante mantiene PENDING_DOCUMENTS sin llegar a DOCUMENT_REVIEW ni FINAL_REVIEW', function () {
    $e = escenarioDocumentosF10();

    $acta = tipoDocumentoF10('ACTA_NAC');
    $curp = tipoDocumentoF10('CURP');
    $domicilio = tipoDocumentoF10('COMPROBANTE_DOM');   // tercer obligatorio: falta

    $doc1 = subirDocumentoF10($e['inscripcion'], $acta, $e['admisiones'], pdfF10());
    $doc2 = subirDocumentoF10($e['inscripcion'], $curp, $e['admisiones'], pdfF10());

    // Los documentos existentes permanecen, pero NO se avanza de etapa.
    expect(Documento::query()->count())->toBe(2)
        ->and($domicilio->documentos()->count())->toBe(0)
        ->and($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_DOCUMENTS)
        ->and(Inscripcion::query()->where('estatus', InscripcionEstatus::DOCUMENT_REVIEW->value)->count())->toBe(0)
        ->and(Inscripcion::query()->where('estatus', InscripcionEstatus::FINAL_REVIEW->value)->count())->toBe(0);
});

test('F14: mientras un obligatorio no esté ACEPTADO no se llega a FINAL_REVIEW', function () {
    $e = escenarioDocumentosF10();

    $acta = tipoDocumentoF10('ACTA_NAC');
    $curp = tipoDocumentoF10('CURP');

    $doc1 = subirDocumentoF10($e['inscripcion'], $acta, $e['admisiones'], pdfF10());
    $doc2 = subirDocumentoF10($e['inscripcion'], $curp, $e['admisiones'], pdfF10());

    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::DOCUMENT_REVIEW);

    auth()->setUser($e['control']);
    app(DocumentoService::class)->aceptar($doc1, $e['control']);

    expect($doc1->refresh()->estatus)->toBe(EstatusDocumento::ACEPTADO)
        ->and($doc2->refresh()->estatus)->toBe(EstatusDocumento::PENDIENTE)
        ->and($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::DOCUMENT_REVIEW)
        ->and($e['inscripcion']->documentos_validados_at)->toBeNull();
});

test('F15: el rechazo con motivo regresa a PENDING_DOCUMENTS y conserva el archivo', function () {
    $e = escenarioDocumentosF10();

    $acta = tipoDocumentoF10('ACTA_NAC');
    $curp = tipoDocumentoF10('CURP');

    $doc1 = subirDocumentoF10($e['inscripcion'], $acta, $e['admisiones'], pdfF10(), 'acta.pdf');
    $doc2 = subirDocumentoF10($e['inscripcion'], $curp, $e['admisiones'], pdfF10(), 'curp.pdf');
    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::DOCUMENT_REVIEW);

    $ruta = $doc1->ruta;
    auth()->setUser($e['control']);
    app(DocumentoService::class)->rechazar($doc1, 'El acta de nacimiento esta ilegible', $e['control']);

    $doc1->refresh();
    expect($doc1->estatus)->toBe(EstatusDocumento::RECHAZADO)
        ->and($doc1->motivo_rechazo)->toBe('El acta de nacimiento esta ilegible')
        ->and($doc1->validado_por)->toBe($e['control']->id)
        ->and($doc1->validado_at)->not->toBeNull()
        ->and($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_DOCUMENTS)
        ->and(Storage::disk('private')->exists($ruta))->toBeTrue()   // sin borrar archivo
        ->and(Auditoria::query()->where('accion', 'documento_rechazado')->count())->toBe(1)
        ->and(Auditoria::query()->where('accion', 'estado_cambiado')
            ->where('estado_anterior', 'DOCUMENT_REVIEW')
            ->where('estado_nuevo', 'PENDING_DOCUMENTS')->count())->toBe(1);
});

test('F15: el motivo vacio o demasiado corto rechaza el rechazo', function () {
    $e = escenarioDocumentosF10();

    $acta = tipoDocumentoF10('ACTA_NAC');
    $curp = tipoDocumentoF10('CURP');
    $doc = subirDocumentoF10($e['inscripcion'], $acta, $e['admisiones'], pdfF10());
    subirDocumentoF10($e['inscripcion'], $curp, $e['admisiones'], pdfF10());

    auth()->setUser($e['control']);

    expect(fn () => app(DocumentoService::class)->rechazar($doc, '', $e['control']))
        ->toThrow(DocumentoInvalidoException::class, 'motivo');
    expect(fn () => app(DocumentoService::class)->rechazar($doc, 'mala', $e['control']))
        ->toThrow(DocumentoInvalidoException::class, 'motivo');

    expect($doc->refresh()->estatus)->toBe(EstatusDocumento::PENDIENTE)
        ->and($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::DOCUMENT_REVIEW)
        ->and(Auditoria::query()->where('accion', 'documento_rechazado')->count())->toBe(0);
});

/*|--------------------------------------------------------------------------
| Archivo: formatos, MIME real, tamaño y almacenamiento privado
|--------------------------------------------------------------------------
*/

test('se admiten los formatos PDF, JPG, JPEG y PNG con su MIME real', function () {
    $e = escenarioDocumentosF10();

    $d1 = subirDocumentoF10($e['inscripcion'], tipoDocumentoF10('F_PDF'), $e['admisiones'], pdfF10(), 'doc.pdf');
    $d2 = subirDocumentoF10($e['inscripcion'], tipoDocumentoF10('F_JPG'), $e['admisiones'], jpgF10(), 'doc.jpg');
    $d3 = subirDocumentoF10($e['inscripcion'], tipoDocumentoF10('F_JPEG'), $e['admisiones'], jpgF10(), 'doc.jpeg');
    $d4 = subirDocumentoF10($e['inscripcion'], tipoDocumentoF10('F_PNG'), $e['admisiones'], pngF10(), 'doc.png');

    expect($d1->mime)->toBe('application/pdf')
        ->and($d2->mime)->toBe('image/jpeg')
        ->and($d3->mime)->toBe('image/jpeg')
        ->and($d4->mime)->toBe('image/png')
        ->and($d1->ruta)->toEndWith('.pdf')
        ->and($d2->ruta)->toEndWith('.jpg')
        ->and($d3->ruta)->toEndWith('.jpg')   // la extensión interna sigue al MIME real
        ->and($d4->ruta)->toEndWith('.png')
        ->and($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::DOCUMENT_REVIEW);
});

test('la extension prohibida se rechaza sin crear fila ni archivo', function () {
    $e = escenarioDocumentosF10();
    $tipo = tipoDocumentoF10('ACTA_NAC');

    auth()->setUser($e['admisiones']);
    expect(fn () => app(DocumentoService::class)->subir(
        $e['inscripcion'],
        $tipo->id,
        UploadedFile::fake()->createWithContent('notas.txt', 'hola mundo'),
        $e['admisiones'],
    ))->toThrow(DocumentoInvalidoException::class, 'Formato');

    expect(Documento::query()->count())->toBe(0);
    expect(Storage::disk('private')->allFiles())->toBe([]);
});

test('el MIME real incorrecto se rechaza aunque la extension diga pdf', function () {
    $e = escenarioDocumentosF10();
    $tipo = tipoDocumentoF10('ACTA_NAC');

    auth()->setUser($e['admisiones']);
    expect(fn () => app(DocumentoService::class)->subir(
        $e['inscripcion'],
        $tipo->id,
        UploadedFile::fake()->createWithContent('acta.pdf', 'esto no es un pdf sino texto plano'),
        $e['admisiones'],
    ))->toThrow(DocumentoInvalidoException::class, 'corresponde');

    expect(Documento::query()->count())->toBe(0);
    expect(Storage::disk('private')->allFiles())->toBe([]);
});

test('un archivo mayor a 10 MB se rechaza', function () {
    $e = escenarioDocumentosF10();
    $tipo = tipoDocumentoF10('ACTA_NAC');

    auth()->setUser($e['admisiones']);
    expect(fn () => app(DocumentoService::class)->subir(
        $e['inscripcion'],
        $tipo->id,
        UploadedFile::fake()->create('grande.pdf', 11000),   // 11000 KB > 10240 KB
        $e['admisiones'],
    ))->toThrow(DocumentoInvalidoException::class, '10 MB');

    expect(Documento::query()->count())->toBe(0);
    expect(Storage::disk('private')->allFiles())->toBe([]);
});

test('el archivo se guarda en disco privado con ruta generada por el sistema', function () {
    $e = escenarioDocumentosF10();
    $tipo = tipoDocumentoF10('ACTA_NAC');

    $doc = subirDocumentoF10($e['inscripcion'], $tipo, $e['admisiones'], pdfF10(), 'acta del alumno final.pdf');

    expect(str_starts_with($doc->ruta, 'documentos/'))->toBeTrue()
        ->and(basename($doc->ruta))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.pdf$/')
        ->and(str_contains($doc->ruta, 'acta'))->toBeFalse()          // nombre original NO es el path
        ->and($doc->nombre_original)->toBe('acta del alumno final.pdf')
        ->and(file_exists(public_path($doc->ruta)))->toBeFalse()      // NUNCA en public/
        ->and(Storage::disk('private')->exists($doc->ruta))->toBeTrue()
        ->and($doc->mime)->toBe('application/pdf')
        ->and($doc->tamano_kb)->toBe((int) ceil(strlen(pdfF10()) / 1024));
});

/*|--------------------------------------------------------------------------
| UNIQUE + reemplazo (misma fila) + compensación
|--------------------------------------------------------------------------
*/

test('la recarga reemplaza la MISMA fila, limpia el rechazo y retira el archivo anterior tras el commit', function () {
    $e = escenarioDocumentosF10();
    $acta = tipoDocumentoF10('ACTA_NAC');   // único obligatorio

    $doc = subirDocumentoF10($e['inscripcion'], $acta, $e['admisiones'], pdfF10(), 'original.pdf');
    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::DOCUMENT_REVIEW);

    auth()->setUser($e['control']);
    app(DocumentoService::class)->rechazar($doc, 'Documento ilegible, vuelve a cargarlo', $e['control']);
    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_DOCUMENTS);

    $rutaVieja = $doc->ruta;
    $nuevo = subirDocumentoF10($e['inscripcion'], $acta, $e['admisiones'], pngF10(), 'reemplazo.png');

    expect(Documento::query()->count())->toBe(1)                    // sin duplicados: UNIQUE
        ->and($nuevo->id)->toBe($doc->id)                           // misma fila
        ->and($nuevo->estatus)->toBe(EstatusDocumento::PENDIENTE)   // revalida
        ->and($nuevo->motivo_rechazo)->toBeNull()
        ->and($nuevo->validado_por)->toBeNull()
        ->and($nuevo->validado_at)->toBeNull()
        ->and($nuevo->nombre_original)->toBe('reemplazo.png')
        ->and($nuevo->ruta)->not->toBe($rutaVieja)
        ->and(Storage::disk('private')->exists($rutaVieja))->toBeFalse()   // viejo borrado tras COMMIT
        ->and(Storage::disk('private')->allFiles())->toHaveCount(1)
        ->and(Auditoria::query()->where('accion', 'documento_reemplazado')->count())->toBe(1)
        ->and(Auditoria::query()->where('accion', 'documento_reemplazado')->value('estado_anterior'))->toBe('RECHAZADO');
});

test('un fallo real de BD al subir no deja filas ni archivos huerfanos', function () {
    $e = escenarioDocumentosF10();
    $tipo = tipoDocumentoF10('ACTA_NAC');

    auth()->setUser($e['admisiones']);
    expect(fn () => app(DocumentoService::class)->subir(
        $e['inscripcion'],
        $tipo->id,
        UploadedFile::fake()->createWithContent('acta.pdf', pdfF10()),
        fantasmaF10(),   // FK `subido_por` → users.id revienta dentro de la transacción
    ))->toThrow(QueryException::class);

    expect(Documento::query()->count())->toBe(0)
        ->and(Storage::disk('private')->allFiles())->toBe([])                  // compensación
        ->and($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_DOCUMENTS)
        ->and(Auditoria::query()->where('accion', 'documento_subido')->count())->toBe(0);
});

test('un fallo real de BD al reemplazar conserva el archivo anterior y elimina el nuevo', function () {
    $e = escenarioDocumentosF10();
    $tipo = tipoDocumentoF10('ACTA_NAC');

    $doc = subirDocumentoF10($e['inscripcion'], $tipo, $e['admisiones'], pdfF10(), 'original.pdf');
    $rutaVieja = $doc->ruta;

    auth()->setUser($e['admisiones']);
    expect(fn () => app(DocumentoService::class)->subir(
        $e['inscripcion'],
        $tipo->id,
        UploadedFile::fake()->createWithContent('nuevo.pdf', pdfF10()),
        fantasmaF10(),
    ))->toThrow(QueryException::class);

    expect($doc->refresh()->ruta)->toBe($rutaVieja)                 // fila intacta
        ->and(Storage::disk('private')->exists($rutaVieja))->toBeTrue()   // archivo viejo NO se tocó
        ->and(Storage::disk('private')->allFiles())->toHaveCount(1)       // sin huérfano nuevo
        ->and(Auditoria::query()->where('accion', 'documento_reemplazado')->count())->toBe(0);
});

/*|--------------------------------------------------------------------------
| Catálogo: tipos inactivos/eliminados y opcionales
|--------------------------------------------------------------------------
*/

test('un tipo inactivo o eliminado del catalogo no admite cargas nuevas', function () {
    $e = escenarioDocumentosF10();

    $inactivo = tipoDocumentoF10('INACTIVO', true, false);
    $eliminado = tipoDocumentoF10('ELIMINADO');
    $eliminado->delete();

    expect(fn () => subirDocumentoF10($e['inscripcion'], $inactivo, $e['admisiones'], pdfF10()))
        ->toThrow(DocumentoInvalidoException::class, 'no está activo');
    expect(fn () => subirDocumentoF10($e['inscripcion'], $eliminado, $e['admisiones'], pdfF10()))
        ->toThrow(DocumentoInvalidoException::class, 'eliminado');

    expect(Documento::query()->count())->toBe(0)
        ->and($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_DOCUMENTS);
});

test('un tipo desactivado despues conserva su documento historico visible en el panel', function () {
    $e = escenarioDocumentosF10();
    $tipo = tipoDocumentoF10('HISTORICO');
    $doc = subirDocumentoF10($e['inscripcion'], $tipo, $e['admisiones'], pdfF10(), 'historico.pdf');

    $tipo->forceFill(['activo' => false])->save();

    expect(Documento::query()->find($doc->id))->not->toBeNull()
        ->and(Storage::disk('private')->exists($doc->ruta))->toBeTrue();

    $this->actingAs($e['admisiones']);
    $componente = Livewire::test(PanelDocumentos::class, ['inscripcion' => $e['inscripcion']]);
    $componente
        ->assertSee('historico.pdf')
        ->assertSee('Histórico');
});

test('un tipo opcional no bloquea el avance a DOCUMENT_REVIEW ni a FINAL_REVIEW', function () {
    $e = escenarioDocumentosF10();

    $obligatorio = tipoDocumentoF10('ACTA_NAC', true);
    $opcional = tipoDocumentoF10('FOTOGRAFIA', false);

    $docOblig = subirDocumentoF10($e['inscripcion'], $obligatorio, $e['admisiones'], pdfF10(), 'acta.pdf');

    // Falta el opcional: el obligatorio cargado igual avanza la etapa.
    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::DOCUMENT_REVIEW);

    $docOpc = subirDocumentoF10($e['inscripcion'], $opcional, $e['admisiones'], pngF10(), 'foto.png');

    auth()->setUser($e['control']);
    app(DocumentoService::class)->aceptar($docOblig, $e['control']);

    // Aceptados TODOS los obligatorios ⇒ FINAL_REVIEW aunque el opcional siga PENDIENTE.
    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::FINAL_REVIEW)
        ->and($e['inscripcion']->documentos_validados_at)->not->toBeNull()
        ->and($docOpc->refresh()->estatus)->toBe(EstatusDocumento::PENDIENTE);
});

/*|--------------------------------------------------------------------------
| Autorización (matriz §8.2)
|--------------------------------------------------------------------------
*/

test('los cuatro roles de la matriz pueden cargar documentos', function () {
    $e = escenarioDocumentosF10();
    $tipo = tipoDocumentoF10('ACTA_NAC');

    foreach (['admin', 'admisiones', 'administracion', 'control'] as $i => $clave) {
        $doc = subirDocumentoF10($e['inscripcion'], $tipo, $e[$clave], pdfF10(), "acta{$i}.pdf");
        expect($doc->subido_por)->toBe($e[$clave]->id);
    }

    expect(Documento::query()->count())->toBe(1)                        // reemplazos, misma fila
        ->and(Auditoria::query()->where('accion', 'documento_subido')->count())->toBe(1)
        ->and(Auditoria::query()->where('accion', 'documento_reemplazado')->count())->toBe(3);
});

test('ADMISIONES y ADMINISTRACION no aceptan ni rechazan documentos', function () {
    $e = escenarioDocumentosF10();
    $acta = tipoDocumentoF10('ACTA_NAC');
    $curp = tipoDocumentoF10('CURP');

    $doc = subirDocumentoF10($e['inscripcion'], $acta, $e['admisiones'], pdfF10());
    subirDocumentoF10($e['inscripcion'], $curp, $e['admisiones'], pdfF10());

    auth()->setUser($e['admisiones']);
    expect(fn () => app(DocumentoService::class)->aceptar($doc, $e['admisiones']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(DocumentoService::class)->rechazar($doc, 'motivo valido aqui', $e['admisiones']))
        ->toThrow(AuthorizationException::class);

    auth()->setUser($e['administracion']);
    expect(fn () => app(DocumentoService::class)->aceptar($doc, $e['administracion']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(DocumentoService::class)->rechazar($doc, 'motivo valido aqui', $e['administracion']))
        ->toThrow(AuthorizationException::class);

    // Nada cambió: sigue PENDIENTE y la inscripción sigue en revisión.
    expect($doc->refresh()->estatus)->toBe(EstatusDocumento::PENDIENTE)
        ->and($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::DOCUMENT_REVIEW);
});

test('CONTROL_ESCOLAR acepta y ADMIN cierra la revision hacia FINAL_REVIEW', function () {
    $e = escenarioDocumentosF10();
    $acta = tipoDocumentoF10('ACTA_NAC');
    $curp = tipoDocumentoF10('CURP');

    $doc1 = subirDocumentoF10($e['inscripcion'], $acta, $e['admisiones'], pdfF10());
    $doc2 = subirDocumentoF10($e['inscripcion'], $curp, $e['admisiones'], pdfF10());

    auth()->setUser($e['control']);
    app(DocumentoService::class)->aceptar($doc1, $e['control']);
    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::DOCUMENT_REVIEW);

    auth()->setUser($e['admin']);
    app(DocumentoService::class)->aceptar($doc2, $e['admin']);

    expect($doc2->refresh()->estatus)->toBe(EstatusDocumento::ACEPTADO)
        ->and($doc2->validado_por)->toBe($e['admin']->id)
        ->and($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::FINAL_REVIEW)
        ->and($e['inscripcion']->documentos_validados_at)->not->toBeNull();
});

test('el panel exige autorizacion server-side para validar documentos', function () {
    $e = escenarioDocumentosF10();
    $acta = tipoDocumentoF10('ACTA_NAC');
    $curp = tipoDocumentoF10('CURP');
    $doc = subirDocumentoF10($e['inscripcion'], $acta, $e['admisiones'], pdfF10());
    subirDocumentoF10($e['inscripcion'], $curp, $e['admisiones'], pdfF10());

    $this->actingAs($e['admisiones']);
    Livewire::test(PanelDocumentos::class, ['inscripcion' => $e['inscripcion']])
        ->call('aceptar', $doc->id)->assertForbidden();

    $this->actingAs($e['administracion']);
    Livewire::test(PanelDocumentos::class, ['inscripcion' => $e['inscripcion']])
        ->call('abrirRechazo', $doc->id)->assertForbidden();

    // CONTROL_ESCOLAR sí puede abrir el formulario de rechazo.
    $this->actingAs($e['control']);
    Livewire::test(PanelDocumentos::class, ['inscripcion' => $e['inscripcion']])
        ->call('abrirRechazo', $doc->id)->assertSet('documentoRechazo', $doc->id);
});

/*|--------------------------------------------------------------------------
| PanelDocumentos (recepción + UI)
|--------------------------------------------------------------------------
*/

test('el panel recibe un archivo y lo delega al DocumentoService', function () {
    $e = escenarioDocumentosF10();
    $tipo = tipoDocumentoF10('ACTA_NAC');

    $this->actingAs($e['admisiones']);
    Livewire::test(PanelDocumentos::class, ['inscripcion' => $e['inscripcion']])
        ->call('abrirCarga', $tipo->id)
        ->set('archivo', UploadedFile::fake()->createWithContent('acta.pdf', pdfF10()))
        ->call('cargar')
        ->assertHasNoErrors();

    $doc = Documento::query()->firstOrFail();
    expect($doc->estatus)->toBe(EstatusDocumento::PENDIENTE)
        ->and($doc->nombre_original)->toBe('acta.pdf')
        ->and($doc->mime)->toBe('application/pdf')
        ->and($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::DOCUMENT_REVIEW);
});

test('el panel muestra estados, obligatoriedad, acciones y el motivo de rechazo', function () {
    $e = escenarioDocumentosF10();

    $acta = tipoDocumentoF10('ACTA_NAC');
    tipoDocumentoF10('FOTOGRAFIA', false);

    $doc = subirDocumentoF10($e['inscripcion'], $acta, $e['admisiones'], pdfF10(), 'acta de nacimiento.pdf');

    auth()->setUser($e['control']);
    app(DocumentoService::class)->rechazar($doc, 'Imagen borrosa, vuelve a capturarla', $e['control']);

    $this->actingAs($e['admisiones']);
    Livewire::test(PanelDocumentos::class, ['inscripcion' => $e['inscripcion']])
        ->assertSee('Documento ACTA_NAC')
        ->assertSee('Obligatorio')
        ->assertSee('Opcional')
        ->assertSee('acta de nacimiento.pdf')
        ->assertSee('RECHAZADO')
        ->assertSee('Imagen borrosa, vuelve a capturarla')
        ->assertSee('Descargar')
        ->assertSee('Subir');   // tras el rechazo, ADMISIONES reemplaza
});

test('el panel no ofrece carga fuera de las etapas del flujo de documentos', function () {
    $e = escenarioDocumentosF10();
    tipoDocumentoF10('ACTA_NAC');

    // Inscripción todavía en PENDING_PAYMENT: los documentos aún no aplican.
    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::PENDING_PAYMENT)->create();

    $this->actingAs($e['admisiones']);
    Livewire::test(PanelDocumentos::class, ['inscripcion' => $inscripcion])
        ->assertDontSee('Subir')
        ->assertDontSee('Reemplazar');
});

test('el panel de documentos no expone informacion medica', function () {
    $e = escenarioDocumentosF10();
    $tipo = tipoDocumentoF10('ACTA_NAC');
    subirDocumentoF10($e['inscripcion'], $tipo, $e['admisiones'], pdfF10());

    $e['inscripcion']->alumno->informacionMedica()->create([
        'tipo_sangre' => 'AB-',
        'alergias' => 'ALERTA-MEDICA-F10-SECRETA',
        'tiene_discapacidad' => false,
        'actualizado_por' => $e['administracion']->id,
    ]);

    $this->actingAs($e['administracion']);
    Livewire::test(PanelDocumentos::class, ['inscripcion' => $e['inscripcion']])
        ->assertDontSee('ALERTA-MEDICA-F10-SECRETA')
        ->assertSee('Documento ACTA_NAC');
});

/*|--------------------------------------------------------------------------
| Descarga privada (GET /documentos/{documento}/archivo)
|--------------------------------------------------------------------------
*/

test('los cuatro roles descargan el archivo con su nombre original', function () {
    $e = escenarioDocumentosF10();
    $tipo = tipoDocumentoF10('ACTA_NAC');
    $doc = subirDocumentoF10($e['inscripcion'], $tipo, $e['admisiones'], pdfF10(), 'acta de nacimiento.pdf');

    foreach (['admin', 'admisiones', 'administracion', 'control'] as $clave) {
        $this->actingAs($e[$clave]);
        $this->get(route('documentos.archivo', $doc))
            ->assertOk()
            ->assertDownload('acta de nacimiento.pdf');
    }
});

test('la descarga exige autenticacion y usuario activo', function () {
    $e = escenarioDocumentosF10();
    $tipo = tipoDocumentoF10('ACTA_NAC');
    $doc = subirDocumentoF10($e['inscripcion'], $tipo, $e['admisiones'], pdfF10());

    $this->get(route('documentos.archivo', $doc))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->inactivo()->create());
    $this->get(route('documentos.archivo', $doc))->assertForbidden();
});

test('la descarga resuelve la ruta solo desde BD e impide path traversal', function () {
    $e = escenarioDocumentosF10();
    $tipo = tipoDocumentoF10('ACTA_NAC');
    $doc = subirDocumentoF10($e['inscripcion'], $tipo, $e['admisiones'], pdfF10());
    $rutaOriginal = $doc->ruta;

    $this->actingAs($e['admin']);

    // Path traversal: aunque la BD tenga un path arbitrario, jamás sale del disco privado.
    $doc->forceFill(['ruta' => '../../../.env'])->save();
    $this->get(route('documentos.archivo', $doc))->assertNotFound();

    // Archivo inexistente en disco → 404 manejado.
    $doc->forceFill(['ruta' => 'documentos/no-existe.pdf'])->save();
    $this->get(route('documentos.archivo', $doc))->assertNotFound();

    // Documento eliminado de la BD → 404 por model binding.
    $doc->delete();
    $this->get('/documentos/'.$doc->id.'/archivo')->assertNotFound();
    Storage::disk('private')->assertExists($rutaOriginal);   // el borrado en BD no toca el disco
});

test('las rutas de descarga de documentos y de pagos son independientes', function () {
    $e = escenarioDocumentosF10();
    $tipo = tipoDocumentoF10('ACTA_NAC');
    $doc = subirDocumentoF10($e['inscripcion'], $tipo, $e['admisiones'], pdfF10());

    $pago = Pago::query()->firstOrFail();

    expect(route('documentos.archivo', $doc))->toEndWith('/documentos/'.$doc->id.'/archivo')
        ->and(route('pagos.comprobante', $pago))->toEndWith('/pagos/'.$pago->id.'/comprobante')
        ->and(route('pagos.comprobante-pdf', $pago))->toEndWith('/pagos/'.$pago->id.'/comprobante-pdf');

    // Cada namespace resuelve por su propio modelo (404 si no existe).
    $this->actingAs($e['admin']);
    $this->get('/documentos/999999/archivo')->assertNotFound();
    $this->get('/pagos/999999/comprobante')->assertNotFound();
    $this->get('/pagos/999999/comprobante-pdf')->assertNotFound();
});

/*|--------------------------------------------------------------------------
| Auditoría completa del ciclo
|--------------------------------------------------------------------------
*/

test('el ciclo subir, rechazar, reemplazar y aceptar deja su auditoria completa', function () {
    $e = escenarioDocumentosF10();

    $acta = tipoDocumentoF10('ACTA_NAC');
    $curp = tipoDocumentoF10('CURP');

    $doc1 = subirDocumentoF10($e['inscripcion'], $acta, $e['admisiones'], pdfF10(), 'acta.pdf');
    $doc2 = subirDocumentoF10($e['inscripcion'], $curp, $e['admisiones'], pdfF10(), 'curp.pdf');

    auth()->setUser($e['control']);
    app(DocumentoService::class)->rechazar($doc1, 'No se lee el acta de nacimiento', $e['control']);

    // ADMISIONES reemplaza el rechazado (FC-6) y la etapa vuelve a revisión.
    $doc1 = subirDocumentoF10($e['inscripcion'], $acta, $e['admisiones'], pngF10(), 'acta-v2.png');

    auth()->setUser($e['control']);
    app(DocumentoService::class)->aceptar($doc1, $e['control']);
    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::DOCUMENT_REVIEW);

    app(DocumentoService::class)->aceptar($doc2, $e['control']);
    expect($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::FINAL_REVIEW);

    expect(Auditoria::query()->where('accion', 'documento_subido')->count())->toBe(2)
        ->and(Auditoria::query()->where('accion', 'documento_rechazado')->count())->toBe(1)
        ->and(Auditoria::query()->where('accion', 'documento_reemplazado')->count())->toBe(1)
        ->and(Auditoria::query()->where('accion', 'documento_aceptado')->count())->toBe(2)
        ->and(Auditoria::query()->where('accion', 'estado_cambiado')
            ->where('estado_anterior', 'DOCUMENT_REVIEW')
            ->where('estado_nuevo', 'PENDING_DOCUMENTS')->count())->toBe(1)
        ->and(Auditoria::query()->where('accion', 'estado_cambiado')->where('estado_nuevo', 'FINAL_REVIEW')->count())->toBe(1);
});
