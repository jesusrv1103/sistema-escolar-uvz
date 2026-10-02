<?php

use App\Enums\EstatusConceptoPago;
use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Exceptions\PagoInvalidoException;
use App\Livewire\Pagos\ModalRegistrarPago;
use App\Livewire\Pagos\ModalValidarPago;
use App\Models\ConceptoPago;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\User;
use App\Services\InscripcionService;
use App\Services\PagoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Fase 8 — Registro de pagos: FC-5 + comprobante privado (R5)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| Reglas de registro (monto exacto, fecha no futura, referencia obligatoria,
| comprobante PDF/JPG/PNG ≤ 10 MB en disco PRIVADO con compensación de
| archivos huérfanos), política de reintentos (un solo intento activo,
| históricos RECHAZADO intactos) y autorización según matriz §8.2.
| Sin mocks: servicios reales, estados reales y FK reales de MySQL.
*/

/** PNG 1x1 real para probar MIME sin depender de GD. */
function pngF8(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

/** Escenario: inscripción con cargos generados (INSCRIPCION + 4 MENSUALIDADES). */
function escenarioRegistroF8(array $sobrescribir = []): array
{
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();

    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::DATA_COMPLETE)->create($sobrescribir);
    app(InscripcionService::class)->generarCargos($inscripcion, $admisiones);

    return [
        'admisiones' => $admisiones,
        'administracion' => $administracion,
        'inscripcion' => $inscripcion->refresh(),
        'concepto' => $inscripcion->conceptosPago()->where('tipo', 'INSCRIPCION')->firstOrFail(),
    ];
}

/** Datos base de un intento válido sobre un concepto. */
function datosF8(ConceptoPago $concepto, array $sobrescribir = []): array
{
    return $sobrescribir + [
        'monto' => $concepto->monto_total,
        'fecha_pago' => now()->toDateString(),
        'metodo' => 'EFECTIVO',
        'referencia' => null,
        'notas' => null,
        'comprobante' => null,
    ];
}

test('CONTROL_ESCOLAR no registra pagos (componente 403 y servicio server-side)', function () {
    $escenario = escenarioRegistroF8();
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    $this->actingAs($control);
    Livewire::test(ModalRegistrarPago::class, ['inscripcion' => $escenario['inscripcion']])
        ->set('conceptoId', $escenario['concepto']->id)
        ->set('fecha_pago', now()->toDateString())
        ->set('metodo', 'EFECTIVO')
        ->call('registrar')
        ->assertForbidden();

    expect(fn () => app(PagoService::class)->registrar($escenario['concepto'], datosF8($escenario['concepto']), $control))
        ->toThrow(AuthorizationException::class);

    expect($escenario['concepto']->pagos()->count())->toBe(0)
        ->and($escenario['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_PAYMENT);
});

test('ADMISIONES registra pagos desde el componente (INSCRIPCION → PAYMENT_REVIEW)', function () {
    $escenario = escenarioRegistroF8();

    $this->actingAs($escenario['admisiones']);
    Livewire::test(ModalRegistrarPago::class, ['inscripcion' => $escenario['inscripcion']])
        ->set('conceptoId', $escenario['concepto']->id)
        ->set('fecha_pago', now()->toDateString())
        ->set('metodo', 'EFECTIVO')
        ->call('registrar')
        ->assertHasNoErrors();

    $pago = $escenario['concepto']->pagos()->firstOrFail();

    expect($pago->estatus)->toBe(App\Enums\EstatusPago::PENDIENTE)
        ->and($pago->registrado_por)->toBe($escenario['admisiones']->id)
        ->and((float) $pago->monto)->toBe(1500.00)   // monto_total del servidor, no editable
        ->and($escenario['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PAYMENT_REVIEW);
});

test('ADMINISTRACION tambien puede registrar pagos (matriz 8.2)', function () {
    $escenario = escenarioRegistroF8();

    $this->actingAs($escenario['administracion']);
    $pago = app(PagoService::class)->registrar($escenario['concepto'], datosF8($escenario['concepto']), $escenario['administracion']);

    expect($pago->estatus)->toBe(App\Enums\EstatusPago::PENDIENTE)
        ->and($pago->registrado_por)->toBe($escenario['administracion']->id)
        ->and($escenario['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PAYMENT_REVIEW);
});

test('un monto distinto al monto_total o en cero es rechazado (sin pagos parciales)', function () {
    $escenario = escenarioRegistroF8();
    $this->actingAs($escenario['admisiones']);

    expect(fn () => app(PagoService::class)->registrar($escenario['concepto'], datosF8($escenario['concepto'], ['monto' => '1000.00']), $escenario['admisiones']))
        ->toThrow(PagoInvalidoException::class, 'exactamente');
    expect(fn () => app(PagoService::class)->registrar($escenario['concepto'], datosF8($escenario['concepto'], ['monto' => '0.00']), $escenario['admisiones']))
        ->toThrow(PagoInvalidoException::class, 'mayor a 0');

    expect($escenario['concepto']->pagos()->count())->toBe(0)
        ->and($escenario['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_PAYMENT);
});

test('la fecha de pago no puede ser futura', function () {
    $escenario = escenarioRegistroF8();
    $this->actingAs($escenario['admisiones']);

    expect(fn () => app(PagoService::class)->registrar(
        $escenario['concepto'],
        datosF8($escenario['concepto'], ['fecha_pago' => now()->addDay()->toDateString()]),
        $escenario['admisiones'],
    ))->toThrow(PagoInvalidoException::class, 'futura');

    expect($escenario['concepto']->pagos()->count())->toBe(0);
});

test('la referencia es obligatoria para TRANSFERENCIA y para TARJETA', function () {
    $escenario = escenarioRegistroF8();
    $this->actingAs($escenario['admisiones']);

    foreach (['TRANSFERENCIA', 'TARJETA'] as $metodo) {
        expect(fn () => app(PagoService::class)->registrar(
            $escenario['concepto'],
            datosF8($escenario['concepto'], ['metodo' => $metodo, 'referencia' => null]),
            $escenario['admisiones'],
        ))->toThrow(PagoInvalidoException::class, 'obligatoria');
    }

    expect($escenario['concepto']->pagos()->count())->toBe(0);

    // Con referencia sí se registra.
    $pago = app(PagoService::class)->registrar(
        $escenario['concepto'],
        datosF8($escenario['concepto'], ['metodo' => 'TRANSFERENCIA', 'referencia' => 'REF-001122']),
        $escenario['admisiones'],
    );

    expect($pago->metodo->value)->toBe('TRANSFERENCIA')
        ->and($pago->referencia)->toBe('REF-001122');
});

test('un metodo de pago invalido es rechazado', function () {
    $escenario = escenarioRegistroF8();
    $this->actingAs($escenario['admisiones']);

    expect(fn () => app(PagoService::class)->registrar(
        $escenario['concepto'],
        datosF8($escenario['concepto'], ['metodo' => 'BITCOIN']),
        $escenario['admisiones'],
    ))->toThrow(PagoInvalidoException::class, 'método');

    expect($escenario['concepto']->pagos()->count())->toBe(0);
});

test('el comprobante mayor a 10 MB es rechazado y no escribe nada', function () {
    Storage::fake('private');
    $escenario = escenarioRegistroF8();
    $this->actingAs($escenario['admisiones']);

    expect(fn () => app(PagoService::class)->registrar(
        $escenario['concepto'],
        datosF8($escenario['concepto'], ['comprobante' => UploadedFile::fake()->create('grande.pdf', 11000)]),
        $escenario['admisiones'],
    ))->toThrow(PagoInvalidoException::class, '10 MB');

    Storage::disk('private')->assertDirectoryEmpty('pagos');
    expect($escenario['concepto']->pagos()->count())->toBe(0);
});

test('un comprobante con MIME real invalido es rechazado (aunque la extension diga pdf)', function () {
    Storage::fake('private');
    $escenario = escenarioRegistroF8();
    $this->actingAs($escenario['admisiones']);

    expect(fn () => app(PagoService::class)->registrar(
        $escenario['concepto'],
        datosF8($escenario['concepto'], ['comprobante' => UploadedFile::fake()->createWithContent('comprobante.pdf', 'esto no es un pdf real, es texto plano')]),
        $escenario['admisiones'],
    ))->toThrow(PagoInvalidoException::class, 'contenido');

    Storage::disk('private')->assertDirectoryEmpty('pagos');
    expect($escenario['concepto']->pagos()->count())->toBe(0);
});

test('un comprobante con extension no admitida es rechazado', function () {
    Storage::fake('private');
    $escenario = escenarioRegistroF8();
    $this->actingAs($escenario['admisiones']);

    expect(fn () => app(PagoService::class)->registrar(
        $escenario['concepto'],
        datosF8($escenario['concepto'], ['comprobante' => UploadedFile::fake()->createWithContent('notas.txt', pngF8())]),
        $escenario['admisiones'],
    ))->toThrow(PagoInvalidoException::class, 'Formato');

    Storage::disk('private')->assertDirectoryEmpty('pagos');
    expect($escenario['concepto']->pagos()->count())->toBe(0);
});

test('el comprobante valido se guarda en el disco PRIVADO con ruta interna y nombre original separado', function () {
    Storage::fake('private');
    $escenario = escenarioRegistroF8();
    $this->actingAs($escenario['admisiones']);

    $pago = app(PagoService::class)->registrar(
        $escenario['concepto'],
        datosF8($escenario['concepto'], ['comprobante' => UploadedFile::fake()->createWithContent('recibo del alumno.png', pngF8())]),
        $escenario['admisiones'],
    );

    Storage::disk('private')->assertExists($pago->comprobante_ruta);

    expect(str_starts_with($pago->comprobante_ruta, 'pagos/'))->toBeTrue()
        ->and(str_contains($pago->comprobante_ruta, 'recibo'))->toBeFalse()     // ruta generada, no el nombre original
        ->and(str_contains($pago->comprobante_ruta, '.png'))->toBeTrue()        // extensión derivada del MIME real
        ->and($pago->comprobante_nombre_original)->toBe('recibo del alumno.png')
        ->and(file_exists(public_path($pago->comprobante_ruta)))->toBeFalse();  // jamás en public/
});

test('un fallo de BD posterior a escribir el comprobante elimina el archivo (sin huérfanos)', function () {
    Storage::fake('private');
    $escenario = escenarioRegistroF8();
    $this->actingAs($escenario['admisiones']);

    // Usuario fantasma: la FK `registrado_por → users` falla DESPUÉS de
    // escribir el archivo en disco (sin mocks, fallo real de MySQL).
    $fantasma = new User();
    $fantasma->forceFill(['id' => 99999999, 'name' => 'Fantasma', 'email' => 'fantasma-pago@uvz.local']);
    $fantasma->id = 99999999;

    expect(fn () => app(PagoService::class)->registrar(
        $escenario['concepto'],
        datosF8($escenario['concepto'], ['comprobante' => UploadedFile::fake()->createWithContent('recibo.png', pngF8())]),
        $fantasma,
    ))->toThrow(QueryException::class);

    // Compensación: el archivo nuevo se eliminó y la BD quedó intacta.
    Storage::disk('private')->assertDirectoryEmpty('pagos');
    expect($escenario['concepto']->pagos()->count())->toBe(0)
        ->and($escenario['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_PAYMENT)
        ->and(Pago::query()->count())->toBe(0);
});

test('solo puede existir UN intento PENDIENTE activo por concepto (lockForUpdate)', function () {
    $escenario = escenarioRegistroF8();
    $this->actingAs($escenario['admisiones']);
    $servicio = app(PagoService::class);

    $primero = $servicio->registrar($escenario['concepto'], datosF8($escenario['concepto']), $escenario['admisiones']);

    // Segundo intento concurrente: lo impide el lock del concepto en BD.
    expect(fn () => $servicio->registrar($escenario['concepto'], datosF8($escenario['concepto']), $escenario['admisiones']))
        ->toThrow(PagoInvalidoException::class, 'pendiente');

    expect($escenario['concepto']->pagos()->count())->toBe(1)
        ->and($primero->estatus)->toBe(App\Enums\EstatusPago::PENDIENTE);
});

test('concepto INSCRIPCION en $0 (PAGADO) no admite pagos', function () {
    $escenario = escenarioRegistroF8(['monto_inscripcion' => '0.00']);
    $this->actingAs($escenario['admisiones']);

    // La Fase 7 hace nacer el concepto $0 en PAGADO.
    expect($escenario['concepto']->refresh()->estatus)->toBe(EstatusConceptoPago::PAGADO);

    expect(fn () => app(PagoService::class)->registrar($escenario['concepto'], datosF8($escenario['concepto']), $escenario['admisiones']))
        ->toThrow(PagoInvalidoException::class, 'ya no está pendiente');

    expect(Pago::query()->count())->toBe(0)
        ->and($escenario['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_PAYMENT);
});

test('el concepto de INSCRIPCION solo admite pagos en PENDING_PAYMENT', function () {
    $escenario = escenarioRegistroF8();
    $this->actingAs($escenario['admisiones']);

    // Inscripción avanzada de forma inconsistente: el pago se bloquea.
    $escenario['inscripcion']->forceFill(['estatus' => InscripcionEstatus::PAYMENT_VALIDATED->value])->save();

    expect(fn () => app(PagoService::class)->registrar($escenario['concepto'], datosF8($escenario['concepto']), $escenario['admisiones']))
        ->toThrow(PagoInvalidoException::class, 'Pago pendiente');

    expect($escenario['concepto']->pagos()->count())->toBe(0);
});

test('no se registran pagos sobre una inscripcion CANCELLED', function () {
    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::CANCELLED)->create();
    $mensualidad = ConceptoPago::factory()->create(['inscripcion_id' => $inscripcion->id, 'tipo' => 'MENSUALIDAD']);

    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($admisiones);

    expect(fn () => app(PagoService::class)->registrar($mensualidad, datosF8($mensualidad), $admisiones))
        ->toThrow(PagoInvalidoException::class, 'cancelada');

    expect($mensualidad->pagos()->count())->toBe(0);
});

test('el comprobante privado requiere autenticacion', function () {
    Storage::fake('private');
    Storage::disk('private')->put('pagos/archivo-privado.png', pngF8());

    $pago = Pago::factory()->create([
        'comprobante_ruta' => 'pagos/archivo-privado.png',
        'comprobante_nombre_original' => 'recibo.png',
    ]);

    $this->get(route('pagos.comprobante', $pago))->assertRedirect(route('login'));
});

test('el comprobante se sirve con policy, sin exponer rutas arbitrarias', function () {
    Storage::fake('private');
    Storage::disk('private')->put('pagos/archivo-privado.png', pngF8());

    $pago = Pago::factory()->create([
        'comprobante_ruta' => 'pagos/archivo-privado.png',
        'comprobante_nombre_original' => 'recibo del alumno.png',
    ]);

    // Lectura para los 4 roles (matriz §8.2): ADMINISTRACION y CONTROL_ESCOLAR.
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMINISTRACION)->create());
    $this->get(route('pagos.comprobante', $pago))->assertOk()->assertDownload('recibo del alumno.png');

    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());
    $this->get(route('pagos.comprobante', $pago))->assertOk();

    // Pago sin comprobante ⇒ 404.
    $sinComprobante = Pago::factory()->create(['comprobante_ruta' => null]);
    $this->get(route('pagos.comprobante', $sinComprobante))->assertNotFound();

    // Ruta arbitraria inyectada en BD ⇒ jamás se sirve fuera del disco privado.
    $pago->forceFill(['comprobante_ruta' => '../../../.env'])->save();
    $this->get(route('pagos.comprobante', $pago))->assertNotFound();
});

test('ningun modal de pagos expone informacion medica', function () {
    $escenario = escenarioRegistroF8();

    $escenario['inscripcion']->alumno->informacionMedica()->create([
        'alergias' => 'ALERGIA-SECRETA-F8',
        'condiciones' => 'CONDICION-SECRETA-F8',
        'actualizado_por' => $escenario['admisiones']->id,
    ]);

    $this->actingAs($escenario['admisiones']);
    Livewire::test(ModalRegistrarPago::class, ['inscripcion' => $escenario['inscripcion']])
        ->call('abrir')
        ->assertDontSee('ALERGIA-SECRETA-F8')
        ->assertDontSee('CONDICION-SECRETA-F8');

    // ADMINISTRACION valida pagos y tampoco debe ver datos médicos (RB-18).
    $this->actingAs($escenario['administracion']);
    Livewire::test(ModalValidarPago::class, ['inscripcion' => $escenario['inscripcion']])
        ->call('abrir')
        ->assertDontSee('ALERGIA-SECRETA-F8')
        ->assertDontSee('CONDICION-SECRETA-F8');
});
