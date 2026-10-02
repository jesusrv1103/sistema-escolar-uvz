<?php

use App\Enums\EstatusPago;
use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Exceptions\PagoInvalidoException;
use App\Models\Auditoria;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\User;
use App\Services\ComprobantePagoService;
use App\Services\InscripcionService;
use App\Services\PagoService;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Fase 9 — F26: comprobante institucional PDF (RB-19)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| Solo un pago VALIDADO genera comprobante; se genera BAJO DEMANDA (sin
| persistir el PDF), con branding desde `config('branding.*')`, autorización
| server-side por policy y sin información médica (RB-18). Sin mocks:
| servicios reales y FK reales de MySQL.
*/

/** PNG 1x1 real para la evidencia cargada (sin depender de GD). */
function pngF9(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

/** Escenario: inscripción con cargos y concepto INSCRIPCION pendiente. */
function escenarioComprobanteF9(): array
{
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();

    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::DATA_COMPLETE)->create();
    app(InscripcionService::class)->generarCargos($inscripcion, $admisiones);

    $concepto = $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail();

    return compact('admisiones', 'administracion', 'inscripcion', 'concepto');
}

/** Registra (ADMISIONES) y VALIDA (ADMINISTRACION) un pago con servicios reales. */
function pagoValidadoF9(array $escenario, array $sobrescribir = []): Pago
{
    $servicio = app(PagoService::class);
    $concepto = $escenario['concepto'];

    auth()->setUser($escenario['admisiones']);
    $pago = $servicio->registrar($concepto, $sobrescribir + [
        'monto' => $concepto->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
    ], $escenario['admisiones']);

    auth()->setUser($escenario['administracion']);
    $validado = $servicio->validar($pago, $escenario['administracion']);
    auth()->logout();

    return $validado;
}

/** Registra un intento PENDIENTE con los servicios reales. */
function pagoPendienteF9(array $escenario): Pago
{
    auth()->setUser($escenario['admisiones']);
    $pago = app(PagoService::class)->registrar($escenario['concepto'], [
        'monto' => $escenario['concepto']->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
    ], $escenario['admisiones']);
    auth()->logout();

    return $pago;
}

test('F26: un pago VALIDADO devuelve application/pdf con encabezado de descarga', function () {
    $escenario = escenarioComprobanteF9();
    $pago = pagoValidadoF9($escenario);

    $this->actingAs($escenario['administracion']);
    $respuesta = $this->get(route('pagos.comprobante-pdf', $pago));

    $respuesta->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload(sprintf('comprobante-%s.pdf', $pago->folio));

    $contenido = (string) $respuesta->getContent();
    expect($contenido)->not->toBe('')
        ->and(str_starts_with($contenido, '%PDF-'))->toBeTrue();   // cabecera real, sin bytes exactos
});

test('F26: un pago PENDIENTE o RECHAZADO no genera PDF', function () {
    $pendiente = escenarioComprobanteF9();
    $pagoPendiente = pagoPendienteF9($pendiente);

    expect(fn () => app(ComprobantePagoService::class)->generar($pagoPendiente))
        ->toThrow(PagoInvalidoException::class, 'VALIDADO');

    $this->actingAs($pendiente['administracion']);
    $this->get(route('pagos.comprobante-pdf', $pagoPendiente))->assertNotFound();

    // Rechazado: historial intacto, SIN folio ⇒ tampoco existe comprobante.
    auth()->setUser($pendiente['administracion']);
    $rechazado = app(PagoService::class)->rechazar($pagoPendiente, 'Referencia bancaria no encontrada', $pendiente['administracion']);
    auth()->logout();

    expect($rechazado->estatus)->toBe(EstatusPago::RECHAZADO)
        ->and($rechazado->folio)->toBeNull();

    $this->actingAs($pendiente['administracion']);
    $this->get(route('pagos.comprobante-pdf', $rechazado))->assertNotFound();

    expect(fn () => app(ComprobantePagoService::class)->generar($rechazado))
        ->toThrow(PagoInvalidoException::class, 'VALIDADO');
});

test('F26: los cuatro roles autorizados descargan el comprobante (matriz 8.2)', function () {
    $escenario = escenarioComprobanteF9();
    $pago = pagoValidadoF9($escenario);

    $roles = [
        RolUsuario::ADMIN,
        RolUsuario::ADMISIONES,
        RolUsuario::ADMINISTRACION,
        RolUsuario::CONTROL_ESCOLAR,
    ];

    foreach ($roles as $rol) {
        $this->actingAs(User::factory()->deRol($rol)->create());
        $this->get(route('pagos.comprobante-pdf', $pago))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
});

test('F26: el comprobante PDF exige autenticacion y usuario activo', function () {
    $escenario = escenarioComprobanteF9();
    $pago = pagoValidadoF9($escenario);

    $this->get(route('pagos.comprobante-pdf', $pago))->assertRedirect(route('login'));

    // Cuenta inactiva: Gate::before la niega todo (RB-21) ⇒ 403.
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->inactivo()->create());
    $this->get(route('pagos.comprobante-pdf', $pago))->assertForbidden();
});

test('los datos del comprobante incluyen folio, monto, metodo y branding', function () {
    $escenario = escenarioComprobanteF9();
    $pago = pagoValidadoF9($escenario, ['metodo' => 'TRANSFERENCIA', 'referencia' => 'REF-998877']);

    $datos = app(ComprobantePagoService::class)->datos($pago->fresh());

    expect($datos['folio'])->toBe($pago->folio)
        ->and($datos['monto'])->toBe('$'.number_format((float) $pago->monto, 2))
        ->and($datos['monto'])->toContain(number_format((float) $escenario['concepto']->monto_total, 2))
        ->and($datos['moneda'])->toBe('MXN')
        ->and($datos['metodo'])->toBe('Transferencia')
        ->and($datos['referencia'])->toBe('REF-998877')
        ->and($datos['alumno'])->toBe(trim($pago->conceptoPago->inscripcion->alumno->nombre.' '.$pago->conceptoPago->inscripcion->alumno->apellidos))
        ->and($datos['programa'])->not->toBe('')
        // Branding consumido desde config (§23.2), nunca hardcodeado.
        ->and($datos['institucion'])->toBe(config('branding.name'))
        ->and($datos['contacto']['telefono'])->toBe(config('branding.phone'))
        ->and($datos['colores']['primario'])->toBe(config('branding.primary_color'))
        ->and($datos['logo'])->toStartWith('data:image/');
});

test('referencia y numero_control nulos no rompen la generacion del PDF', function () {
    $escenario = escenarioComprobanteF9();
    $pago = pagoValidadoF9($escenario);   // EFECTIVO sin referencia; NC aún NULL (Fase 12)

    $datos = app(ComprobantePagoService::class)->datos($pago);

    expect($datos['referencia'])->toBeNull()
        ->and($datos['numeroControl'])->toBeNull();

    $this->actingAs($escenario['administracion']);
    $this->get(route('pagos.comprobante-pdf', $pago))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    // Con numero_control presente también se renderiza.
    $pago->conceptoPago->inscripcion->alumno->forceFill(['numero_control' => '20260001'])->save();
    expect(app(ComprobantePagoService::class)->datos($pago->fresh())['numeroControl'])->toBe('20260001');
});

test('el PDF se renderiza escapando contenido y sin informacion medica', function () {
    $escenario = escenarioComprobanteF9();
    $pago = pagoValidadoF9($escenario);

    $alumno = $pago->conceptoPago->inscripcion->alumno;
    $alumno->forceFill(['nombre' => '<script>ALUMNO-XYZ'])->save();
    $alumno->informacionMedica()->create([
        'alergias' => 'ALERGIA-SECRETA-F9',
        'condiciones' => 'CONDICION-SECRETA-F9',
        'actualizado_por' => $escenario['admisiones']->id,
    ]);

    $datos = app(ComprobantePagoService::class)->datos($pago->fresh());
    $html = view('pdf.comprobante-pago', $datos)->render();

    // Escapado de datos provenientes de BD (sin {!! !!}).
    expect($html)->toContain('&lt;script&gt;ALUMNO-XYZ')
        ->and($html)->not->toContain('<script>ALUMNO-XYZ')
        ->and($html)->toContain($pago->folio);

    // Nunca información médica ni otros datos sensibles (RB-18): ni en los
    // datos preparados ni en el HTML renderizado.
    expect(json_encode($datos, JSON_THROW_ON_ERROR))
        ->not->toContain('ALERGIA-SECRETA-F9')
        ->not->toContain('CONDICION-SECRETA-F9');
    expect($html)
        ->not->toContain('ALERGIA-SECRETA-F9')
        ->not->toContain('CONDICION-SECRETA-F9')
        ->and($html)->not->toContain('CURP');
});

test('generar el PDF no persiste archivos ni modifica el pago ni la auditoria', function () {
    Storage::fake('private');
    $escenario = escenarioComprobanteF9();
    $pago = pagoValidadoF9($escenario);

    $atributosAntes = $pago->refresh()->getAttributes();
    $auditoriasAntes = Auditoria::query()->count();

    $this->actingAs($escenario['administracion']);
    $respuesta = $this->get(route('pagos.comprobante-pdf', $pago));

    $respuesta->assertOk();

    // 1) El PDF vive solo en la respuesta: sin copia en disco privado.
    expect(Storage::disk('private')->allFiles())->toBe([]);

    // 2) El pago queda intacto (estatus, folio, validación, montos).
    $pago->refresh();
    expect($pago->getAttributes())->toBe($atributosAntes);

    // 3) Descargar no escribe auditoría de negocio (la arquitectura no la audita).
    expect(Auditoria::query()->count())->toBe($auditoriasAntes);
});

test('la ruta del PDF no interfiere con la evidencia cargada', function () {
    Storage::fake('private');
    Storage::disk('private')->put('pagos/evidencia.png', pngF9());

    $escenario = escenarioComprobanteF9();
    $pago = pagoValidadoF9($escenario);
    $pago->forceFill([
        'comprobante_ruta' => 'pagos/evidencia.png',
        'comprobante_nombre_original' => 'evidencia del alumno.png',
    ])->save();

    $this->actingAs($escenario['administracion']);

    // Evidencia (Fase 8) sigue sirviendo el archivo subido…
    $this->get(route('pagos.comprobante', $pago))
        ->assertOk()
        ->assertDownload('evidencia del alumno.png');

    // …y el PDF (Fase 9) genera su propio documento con nombre por folio.
    $this->get(route('pagos.comprobante-pdf', $pago))
        ->assertOk()
        ->assertDownload(sprintf('comprobante-%s.pdf', $pago->folio));

    // Pago VALIDADO sin evidencia: la evidencia 404, el PDF sí existe.
    Storage::disk('private')->delete('pagos/evidencia.png');
    $pago->forceFill(['comprobante_ruta' => null, 'comprobante_nombre_original' => null])->save();

    $this->get(route('pagos.comprobante', $pago))->assertNotFound();
    $this->get(route('pagos.comprobante-pdf', $pago))->assertOk();
});

test('un pago VALIDADO con integridad incompleta no genera comprobante', function () {
    $escenario = escenarioComprobanteF9();
    $pago = pagoValidadoF9($escenario);

    // Folio ausente a pesar de estar VALIDADO (estado inconsistente).
    $pago->forceFill(['folio' => null])->save();
    $sinFolio = $pago->fresh();

    expect(fn () => app(ComprobantePagoService::class)->datos($sinFolio))
        ->toThrow(PagoInvalidoException::class, 'folio');
    expect(fn () => app(ComprobantePagoService::class)->generar($sinFolio))
        ->toThrow(PagoInvalidoException::class);

    $this->actingAs($escenario['administracion']);
    $this->get(route('pagos.comprobante-pdf', $sinFolio))->assertNotFound();
});
