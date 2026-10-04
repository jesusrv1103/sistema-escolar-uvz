<?php

use App\Enums\EstatusObservacion;
use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Enums\TipoObservacion;
use App\Exceptions\ObservacionInvalidaException;
use App\Livewire\Observaciones\ListaObservaciones;
use App\Models\Auditoria;
use App\Models\Inscripcion;
use App\Models\Observacion;
use App\Models\User;
use App\Services\ObservacionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fase 11 — Observaciones: §4.13/§12.9/RB-8/RB-15 (APPEND-ONLY)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| Crear / listar-ver / resolver con ciclo de vida completo; solo FALTANTE +
| ABIERTA bloquea la finalización (guarda reutilizable para la Fase 12);
| sin edición de detalle/tipo, sin borrado, sin reapertura; autorización de
| los 4 roles según matriz §8.2, auditoría en la misma transacción y
| ROLLBACK TOTAL si la auditoría falla. Sin mocks: servicios reales,
| policies reales, máquina de estados intacta y FK de MySQL reales.
*/

/** Escenario: los 4 roles de la matriz §8.2 + inscripción sintética. */
function escenarioObservacionesF11(): array
{
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    return [
        'admin' => $admin,
        'admisiones' => $admisiones,
        'administracion' => $administracion,
        'control' => $control,
        'inscripcion' => Inscripcion::factory()->create(),
    ];
}

/** Crea una observación vía el servicio SIEMPRE con el usuario indicado. */
function crearObservacionF11(Inscripcion $inscripcion, User $usuario, TipoObservacion|string $tipo, string $detalle): Observacion
{
    auth()->setUser($usuario);

    try {
        return app(ObservacionService::class)->crear($inscripcion, $tipo, $detalle, $usuario);
    } finally {
        auth()->logout();   // la autenticación siempre es explícita en cada test
    }
}

/** Resuelve una observación vía el servicio SIEMPRE con el usuario indicado. */
function resolverObservacionF11(Observacion $observacion, User $usuario): Observacion
{
    auth()->setUser($usuario);

    try {
        return app(ObservacionService::class)->resolver($observacion, $usuario);
    } finally {
        auth()->logout();
    }
}

/*|--------------------------------------------------------------------------
| Crear (§12.9)
|--------------------------------------------------------------------------
*/

test('crear: los cuatro tipos admitidos nacen ABIERTA con su creador y sin resolucion', function () {
    $e = escenarioObservacionesF11();

    foreach (TipoObservacion::cases() as $tipo) {
        $o = crearObservacionF11(
            $e['inscripcion'],
            $e['admisiones'],
            $tipo,
            'Detalle operativo del tipo '.$tipo->value,
        );

        expect($o->tipo)->toBe($tipo)
            ->and($o->estatus)->toBe(EstatusObservacion::ABIERTA)
            ->and($o->creada_por)->toBe($e['admisiones']->id)
            ->and($o->inscripcion_id)->toBe($e['inscripcion']->id)
            ->and($o->resuelta_por)->toBeNull()
            ->and($o->resuelta_at)->toBeNull();
    }

    // FALTANTE, DOCUMENTO, PAGO y GENERAL persistidos.
    expect(Observacion::query()->count())->toBe(4);
});

test('crear: el creador proviene del usuario autenticado, no de la peticion', function () {
    $e = escenarioObservacionesF11();

    auth()->setUser($e['control']);

    try {
        // Sin parámetro de usuario explícito: el servicio lo toma del auth.
        $o = app(ObservacionService::class)->crear(
            $e['inscripcion'],
            TipoObservacion::GENERAL,
            'Observacion sin parametro de usuario explicito',
        );
    } finally {
        auth()->logout();
    }

    expect($o->creada_por)->toBe($e['control']->id)
        ->and(Auditoria::query()->where('accion', 'observacion_creada')->firstOrFail()->user_id)->toBe($e['control']->id);
});

test('crear: detalle menor a 5 o mayor a 2000 caracteres se rechaza y los limites exactos pasan', function () {
    $e = escenarioObservacionesF11();

    foreach (['', '   ', 'abc', 'abcd', str_repeat('x', 2001)] as $detalle) {
        expect(fn () => crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::GENERAL, $detalle))
            ->toThrow(ObservacionInvalidaException::class, 'entre 5 y 2000');
    }

    expect(Observacion::query()->count())->toBe(0);

    // Límites exactos: 5 y 2000 caracteres (el mínimo pasa con trim).
    $minima = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::GENERAL, '  abcde  ');
    $maxima = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::GENERAL, str_repeat('x', 2000));

    expect($minima->detalle)->toBe('abcde')
        ->and($maxima->detalle)->toBe(str_repeat('x', 2000))
        ->and(Observacion::query()->count())->toBe(2);
});

test('crear: un tipo fuera del BackedEnum se rechaza', function () {
    $e = escenarioObservacionesF11();

    foreach (['NO_EXISTE', '', '   ', 'faltante', 'FALTANTEE', str_repeat('T', 30)] as $tipo) {
        expect(fn () => crearObservacionF11($e['inscripcion'], $e['admisiones'], $tipo, 'Detalle suficientemente largo'))
            ->toThrow(ObservacionInvalidaException::class, 'Faltante, Documento, Pago o General');
    }

    expect(Observacion::query()->count())->toBe(0)
        ->and(Auditoria::query()->count())->toBe(0);
});

test('el formulario del listado valida tipo y detalle server-side con trim', function () {
    $e = escenarioObservacionesF11();
    $this->actingAs($e['admisiones']);

    Livewire::test(ListaObservaciones::class, ['inscripcion' => $e['inscripcion']])
        ->call('crear')
        ->assertHasErrors(['tipo' => 'required', 'detalle' => 'required']);

    Livewire::test(ListaObservaciones::class, ['inscripcion' => $e['inscripcion']])
        ->set('tipo', 'NO_VALIDO')
        ->set('detalle', 'abc')
        ->call('crear')
        ->assertHasErrors(['tipo', 'detalle']);

    Livewire::test(ListaObservaciones::class, ['inscripcion' => $e['inscripcion']])
        ->set('tipo', 'GENERAL')
        ->set('detalle', str_repeat('x', 2001))
        ->call('crear')
        ->assertHasErrors(['detalle']);

    // Caso válido: el detalle se recorta antes de validar y de persistir.
    Livewire::test(ListaObservaciones::class, ['inscripcion' => $e['inscripcion']])
        ->set('tipo', 'GENERAL')
        ->set('detalle', '   detalle listo para guardar   ')
        ->call('crear')
        ->assertHasNoErrors();

    expect(Observacion::query()->count())->toBe(1)
        ->and(Observacion::firstOrFail()->detalle)->toBe('detalle listo para guardar');
});

/*|--------------------------------------------------------------------------
| Resolver (§12.9)
|--------------------------------------------------------------------------
*/

test('resolver: pasa a RESUELTA con responsable y fecha sin alterar detalle ni tipo', function () {
    $e = escenarioObservacionesF11();

    $o = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::FALTANTE, 'Falta acta de nacimiento original');
    $detalleOriginal = $o->detalle;

    resolverObservacionF11($o, $e['administracion']);
    $o->refresh();

    expect($o->estatus)->toBe(EstatusObservacion::RESUELTA)
        ->and($o->resuelta_por)->toBe($e['administracion']->id)
        ->and($o->resuelta_at)->not->toBeNull()
        ->and($o->creada_por)->toBe($e['admisiones']->id)
        ->and($o->detalle)->toBe($detalleOriginal)
        ->and($o->tipo)->toBe(TipoObservacion::FALTANTE);
});

test('resolver: la segunda resolucion se rechaza explicitamente y no hay reapertura', function () {
    $e = escenarioObservacionesF11();

    $o = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::PAGO, 'Pago con referencia por confirmar');
    resolverObservacionF11($o, $e['control']);

    expect(fn () => resolverObservacionF11($o, $e['admisiones']))
        ->toThrow(ObservacionInvalidaException::class, 'Solo se resuelven observaciones con estatus Abierta');

    $o->refresh();
    expect($o->estatus)->toBe(EstatusObservacion::RESUELTA)
        ->and($o->resuelta_por)->toBe($e['control']->id)      // sin cambio de responsable
        ->and(Auditoria::query()->where('accion', 'observacion_resuelta')->count())->toBe(1);
});

/*|--------------------------------------------------------------------------
| Append-only: sin borrado, sin edición (RB-15)
|--------------------------------------------------------------------------
*/

test('sin borrado: el modelo bloquea delete, forceDelete y restore y no hay rutas ni metodos de baja', function () {
    $e = escenarioObservacionesF11();
    $o = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::GENERAL, 'Observacion historica que no se borra');

    expect(fn () => $o->delete())->toThrow(LogicException::class)
        ->and(fn () => $o->forceDelete())->toThrow(LogicException::class)
        ->and(fn () => $o->restore())->toThrow(LogicException::class);

    expect(Observacion::query()->count())->toBe(1);

    // El servicio solo expone crear y resolver (sin métodos de baja/edición).
    $metodos = array_values(array_filter(
        get_class_methods(ObservacionService::class),
        fn (string $m): bool => ! str_starts_with($m, '__'),
    ));
    sort($metodos);
    expect($metodos)->toBe(['crear', 'resolver']);

    // Única ruta del módulo: el listado (GET); ninguna de edición/borrado.
    $nombres = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($ruta): string => (string) $ruta->getName())
        ->filter(fn (string $nombre): bool => str_contains($nombre, 'observacion'))
        ->values()
        ->all();
    expect($nombres)->toBe(['inscripciones.observaciones']);
});

test('sin edicion: detalle y tipo quedan inmutables y el modelo bloquea update', function () {
    $e = escenarioObservacionesF11();

    $o = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::DOCUMENTO, 'Detalle original inmutable del documento');
    $detalleOriginal = $o->detalle;

    expect(fn () => $o->update(['detalle' => 'Detalle alterado por la UI']))
        ->toThrow(LogicException::class)
        ->and(fn () => $o->update(['tipo' => TipoObservacion::PAGO->value]))
        ->toThrow(LogicException::class);

    $o->refresh();
    expect($o->detalle)->toBe($detalleOriginal)
        ->and($o->tipo)->toBe(TipoObservacion::DOCUMENTO);

    // Resolver tampoco altera el contenido: solo estatus y responsabilidad.
    resolverObservacionF11($o, $e['control']);
    $o->refresh();
    expect($o->detalle)->toBe($detalleOriginal)
        ->and($o->tipo)->toBe(TipoObservacion::DOCUMENTO)
        ->and($o->estatus)->toBe(EstatusObservacion::RESUELTA);
});

/*|--------------------------------------------------------------------------
| Regla crítica: solo FALTANTE + ABIERTA bloquea (RB-8 / Fase 12)
|--------------------------------------------------------------------------
*/

test('RB-8: el FALTANTE ABIERTA es bloqueante y sin observaciones no hay bloqueo', function () {
    $e = escenarioObservacionesF11();

    expect($e['inscripcion']->tieneFaltantesAbiertos())->toBeFalse();

    crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::FALTANTE, 'Falta comprobante de domicilio del alumno');

    expect($e['inscripcion']->tieneFaltantesAbiertos())->toBeTrue();
});

test('RB-8: el FALTANTE RESUELTA deja de ser bloqueante', function () {
    $e = escenarioObservacionesF11();

    $o = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::FALTANTE, 'Falta identificacion oficial vigente');
    expect($e['inscripcion']->tieneFaltantesAbiertos())->toBeTrue();

    resolverObservacionF11($o, $e['control']);

    expect($e['inscripcion']->tieneFaltantesAbiertos())->toBeFalse();
});

test('RB-8: DOCUMENTO, PAGO y GENERAL abiertos no bloquean la finalizacion', function () {
    $e = escenarioObservacionesF11();

    foreach ([TipoObservacion::DOCUMENTO, TipoObservacion::PAGO, TipoObservacion::GENERAL] as $tipo) {
        crearObservacionF11($e['inscripcion'], $e['admisiones'], $tipo, 'Observacion abierta de tipo '.$tipo->value);

        expect($e['inscripcion']->tieneFaltantesAbiertos())->toBeFalse()
            ->and($e['inscripcion']->observaciones()->where('estatus', EstatusObservacion::ABIERTA->value)->count())->toBeGreaterThan(0);
    }
});

test('ninguna observacion modifica el estatus de la inscripcion', function () {
    $e = escenarioObservacionesF11();

    foreach ([InscripcionEstatus::DRAFT, InscripcionEstatus::PENDING_PAYMENT, InscripcionEstatus::FINAL_REVIEW, InscripcionEstatus::ENROLLED] as $estatus) {
        $inscripcion = Inscripcion::factory()->enEstatus($estatus)->create();

        $o = crearObservacionF11($inscripcion, $e['admisiones'], TipoObservacion::FALTANTE, 'Observacion sobre etapa '.$estatus->value);
        resolverObservacionF11($o, $e['control']);

        expect($inscripcion->refresh()->estatus)->toBe($estatus);
    }
});

test('ENROLLED: se crean y resuelven sin efectos sobre el proceso y el listado es legible', function () {
    $e = escenarioObservacionesF11();
    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::ENROLLED)->create();

    $o = crearObservacionF11($inscripcion, $e['admisiones'], TipoObservacion::PAGO, 'Observacion historica posterior al alta');

    expect($o->estatus)->toBe(EstatusObservacion::ABIERTA)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::ENROLLED)
        ->and($inscripcion->tieneFaltantesAbiertos())->toBeFalse();

    // Lectura histórica siempre disponible; resolución también.
    $this->actingAs($e['admisiones']);
    Livewire::test(ListaObservaciones::class, ['inscripcion' => $inscripcion])
        ->assertSee('Observacion historica posterior al alta')
        ->assertSee('Abierta')
        ->call('resolver', $o->id)
        ->assertHasNoErrors();

    expect($o->refresh()->estatus)->toBe(EstatusObservacion::RESUELTA)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::ENROLLED);
});

test('CANCELLED: no se crean observaciones nuevas pero las previas se leen y se resuelven', function () {
    $e = escenarioObservacionesF11();

    $previa = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::GENERAL, 'Observacion previa a la cancelacion');

    // Estado terminal (la cancelación real llega en la Fase 13).
    $e['inscripcion']->forceFill(['estatus' => InscripcionEstatus::CANCELLED->value])->save();

    expect(fn () => crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::GENERAL, 'Nueva observacion sobre archivo cerrado'))
        ->toThrow(ObservacionInvalidaException::class, 'cancelada');

    expect(Observacion::query()->count())->toBe(1);

    // Lectura y resolución de lo previo sí están permitidas (histórico).
    $this->actingAs($e['control']);
    Livewire::test(ListaObservaciones::class, ['inscripcion' => $e['inscripcion']])
        ->assertSee('Observacion previa a la cancelacion');

    resolverObservacionF11($previa, $e['control']);

    expect($previa->refresh()->estatus)->toBe(EstatusObservacion::RESUELTA)
        ->and($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::CANCELLED);
});

/*|--------------------------------------------------------------------------
| Autorización (matriz §8.2)
|--------------------------------------------------------------------------
*/

test('los cuatro roles crean observaciones y la policy esta conectada al modelo', function () {
    $e = escenarioObservacionesF11();

    foreach (['admin', 'admisiones', 'administracion', 'control'] as $clave) {
        $o = crearObservacionF11($e['inscripcion'], $e[$clave], TipoObservacion::GENERAL, 'Observacion creada por el rol '.$clave);

        expect($o->creada_por)->toBe($e[$clave]->id)
            ->and(Gate::forUser($e[$clave])->allows('crear', $o))->toBeTrue()
            ->and(Gate::forUser($e[$clave])->allows('resolver', $o))->toBeTrue();
    }

    expect(Observacion::query()->count())->toBe(4);
});

test('los cuatro roles resuelven observaciones', function () {
    $e = escenarioObservacionesF11();
    $creadas = [];

    foreach (['admin', 'admisiones', 'administracion', 'control'] as $clave) {
        $creadas[$clave] = crearObservacionF11($e['inscripcion'], $e[$clave], TipoObservacion::FALTANTE, 'Faltante del rol '.$clave.' para resolver');
    }

    foreach ($creadas as $clave => $o) {
        resolverObservacionF11($o, $e[$clave]);

        expect($o->refresh()->estatus)->toBe(EstatusObservacion::RESUELTA)
            ->and($o->resuelta_por)->toBe($e[$clave]->id);
    }

    expect(Auditoria::query()->where('accion', 'observacion_resuelta')->count())->toBe(4);
});

test('un usuario inactivo no crea ni resuelve ni abre el listado', function () {
    $e = escenarioObservacionesF11();
    $o = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::GENERAL, 'Observacion existente para probar inactivos');

    $inactivo = User::factory()->deRol(RolUsuario::ADMISIONES)->inactivo()->create();

    auth()->setUser($inactivo);
    try {
        expect(fn () => app(ObservacionService::class)->crear($e['inscripcion'], TipoObservacion::GENERAL, 'Intento de usuario inactivo', $inactivo))
            ->toThrow(AuthorizationException::class);
        expect(fn () => app(ObservacionService::class)->resolver($o, $inactivo))
            ->toThrow(AuthorizationException::class);
    } finally {
        auth()->logout();
    }

    expect(Observacion::query()->count())->toBe(1)
        ->and($o->refresh()->estatus)->toBe(EstatusObservacion::ABIERTA);

    $this->actingAs($inactivo);
    $this->get(route('inscripciones.observaciones', $e['inscripcion']))->assertForbidden();
});

test('el listado exige autenticacion y los cuatro roles lo abren', function () {
    $e = escenarioObservacionesF11();

    $this->get(route('inscripciones.observaciones', $e['inscripcion']))->assertRedirect(route('login'));

    foreach (['admin', 'admisiones', 'administracion', 'control'] as $clave) {
        $this->actingAs($e[$clave])
            ->get(route('inscripciones.observaciones', $e['inscripcion']))
            ->assertOk();
    }
});

/*|--------------------------------------------------------------------------
| ListaObservaciones (UI)
|--------------------------------------------------------------------------
*/

test('el listado crea observaciones via el servicio con autorizacion server-side', function () {
    $e = escenarioObservacionesF11();

    $this->actingAs($e['admisiones']);
    Livewire::test(ListaObservaciones::class, ['inscripcion' => $e['inscripcion']])
        ->set('tipo', 'FALTANTE')
        ->set('detalle', 'Falta identificacion oficial vigente')
        ->call('crear')
        ->assertHasNoErrors();

    $o = Observacion::firstOrFail();
    expect($o->tipo)->toBe(TipoObservacion::FALTANTE)
        ->and($o->estatus)->toBe(EstatusObservacion::ABIERTA)
        ->and($o->creada_por)->toBe($e['admisiones']->id)
        ->and(Auditoria::query()->where('accion', 'observacion_creada')->count())->toBe(1);
});

test('el listado resuelve y rechaza la doble resolucion server-side', function () {
    $e = escenarioObservacionesF11();
    $o = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::FALTANTE, 'Falta comprobante de domicilio');

    $this->actingAs($e['administracion']);
    Livewire::test(ListaObservaciones::class, ['inscripcion' => $e['inscripcion']])
        ->call('resolver', $o->id)
        ->assertHasNoErrors();

    expect($o->refresh()->estatus)->toBe(EstatusObservacion::RESUELTA)
        ->and($o->resuelta_por)->toBe($e['administracion']->id);

    // Segundo intento desde la UI: error explícito, sin doble resolución.
    Livewire::test(ListaObservaciones::class, ['inscripcion' => $e['inscripcion']])
        ->call('resolver', $o->id)
        ->assertHasErrors(['panel']);

    expect($o->refresh()->resuelta_por)->toBe($e['administracion']->id)
        ->and(Auditoria::query()->where('accion', 'observacion_resuelta')->count())->toBe(1);
});

test('el listado jamas resuelve observaciones de otra inscripcion', function () {
    $e = escenarioObservacionesF11();

    $otra = Inscripcion::factory()->create();
    $ajena = crearObservacionF11($otra, $e['admisiones'], TipoObservacion::GENERAL, 'Observacion de otra inscripcion');

    $this->actingAs($e['admisiones']);

    try {
        Livewire::test(ListaObservaciones::class, ['inscripcion' => $e['inscripcion']])
            ->call('resolver', $ajena->id);
    } catch (Throwable) {
        // Binding horizontal: la observación ajena no pertenece a esta
        // inscripción (404/ModelNotFound en cualquier capa que lo resuelva).
    }

    // Invariante de seguridad: la observación ajena jamás se resolvió.
    expect($ajena->refresh()->estatus)->toBe(EstatusObservacion::ABIERTA)
        ->and($ajena->resuelta_por)->toBeNull()
        ->and(Auditoria::query()->where('accion', 'observacion_resuelta')->count())->toBe(0);
});

test('el listado muestra el ciclo completo con responsables y fechas sin datos sensibles', function () {
    $e = escenarioObservacionesF11();

    $o = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::FALTANTE, 'Falta acta de nacimiento original');
    resolverObservacionF11($o, $e['control']);
    $o->refresh();

    $e['inscripcion']->alumno->informacionMedica()->create([
        'tipo_sangre' => 'AB-',
        'alergias' => 'ALERTA-MEDICA-F11-SECRETA',
        'tiene_discapacidad' => false,
        'actualizado_por' => $e['administracion']->id,
    ]);

    // ADMINISTRACION: acceso al listado pero SIN información médica (RB-18).
    $this->actingAs($e['administracion']);
    Livewire::test(ListaObservaciones::class, ['inscripcion' => $e['inscripcion']])
        ->assertSee('Falta acta de nacimiento original')
        ->assertSee('Resuelta')
        ->assertSee($e['admisiones']->name)
        ->assertSee($e['control']->name)
        ->assertSee($o->created_at->format('d/m/Y'))
        ->assertSee($o->resuelta_at->format('d/m/Y'))
        ->assertDontSee('ALERTA-MEDICA-F11-SECRETA')
        ->assertDontSee($e['inscripcion']->alumno->curp)
        ->assertDontSee('Editar')
        ->assertDontSee('Eliminar');
});

test('el listado respeta los filtros de estatus y tipo y ignora valores basura', function () {
    $e = escenarioObservacionesF11();

    $general = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::GENERAL, 'general abierta sin resolver');
    $faltante = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::FALTANTE, 'faltante ya resuelto');
    crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::PAGO, 'pago pendiente de aclaracion');
    resolverObservacionF11($faltante, $e['control']);

    $this->actingAs($e['admisiones']);

    Livewire::test(ListaObservaciones::class, ['inscripcion' => $e['inscripcion']])
        ->assertSee('general abierta sin resolver')
        ->assertSee('faltante ya resuelto')
        ->assertSee('pago pendiente de aclaracion')
        ->set('filtroEstatus', 'ABIERTA')
        ->assertSee('general abierta sin resolver')
        ->assertSee('pago pendiente de aclaracion')
        ->assertDontSee('faltante ya resuelto')
        ->set('filtroEstatus', 'RESUELTA')
        ->assertDontSee('general abierta sin resolver')
        ->assertSee('faltante ya resuelto')
        ->set('filtroEstatus', '')
        ->set('filtroTipo', 'PAGO')
        ->assertSee('pago pendiente de aclaracion')
        ->assertDontSee('general abierta sin resolver')
        ->assertDontSee('faltante ya resuelto')
        ->set('filtroTipo', 'BASURA')   // valor inválido: se ignora, vuelve a todo
        ->assertSee('general abierta sin resolver')
        ->assertSee('faltante ya resuelto');

    expect($general->refresh()->estatus)->toBe(EstatusObservacion::ABIERTA);
});

/*|--------------------------------------------------------------------------
| Auditoría (RB-12) y atomicidad
|--------------------------------------------------------------------------
*/

test('auditoria al crear: observacion_creada con usuario, tipo e inscripcion', function () {
    $e = escenarioObservacionesF11();

    $o = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::PAGO, 'Observacion de pago para auditar');

    $a = Auditoria::query()->where('accion', 'observacion_creada')->firstOrFail();
    expect($a->auditable_type)->toBe(Observacion::class)
        ->and($a->auditable_id)->toBe($o->id)
        ->and($a->user_id)->toBe($e['admisiones']->id)
        ->and($a->estado_anterior)->toBeNull()
        ->and($a->estado_nuevo)->toBe('ABIERTA')
        ->and($a->datos['tipo'])->toBe('PAGO')
        ->and($a->datos['inscripcion_id'])->toBe($e['inscripcion']->id);
});

test('auditoria al resolver: observacion_resuelta con transicion ABIERTA a RESUELTA', function () {
    $e = escenarioObservacionesF11();

    $o = crearObservacionF11($e['inscripcion'], $e['admisiones'], TipoObservacion::FALTANTE, 'Faltante con auditoria de resolucion');
    resolverObservacionF11($o, $e['control']);

    $a = Auditoria::query()->where('accion', 'observacion_resuelta')->firstOrFail();
    expect($a->auditable_type)->toBe(Observacion::class)
        ->and($a->auditable_id)->toBe($o->id)
        ->and($a->user_id)->toBe($e['control']->id)
        ->and($a->estado_anterior)->toBe('ABIERTA')
        ->and($a->estado_nuevo)->toBe('RESUELTA')
        ->and($a->datos['tipo'])->toBe('FALTANTE')
        ->and($a->datos['inscripcion_id'])->toBe($e['inscripcion']->id);
    // El texto libre del detalle NO se copia al payload de auditoría (solo
    // tipo/estatus/inscripción); MySQL JSON reordena claves por longitud.
    $claves = array_keys($a->datos);
    sort($claves);
    expect($claves)->toBe(['inscripcion_id', 'tipo']);
});

test('rollback: si falla la escritura de la auditoria no se persiste la observacion', function () {
    $e = escenarioObservacionesF11();

    // Fallo REAL en la escritura de `auditoria` (evento del modelo real):
    // la INSERT de la observación ya ocurrió dentro de la transacción.
    Auditoria::creating(function (): void {
        throw new RuntimeException('Fallo real al escribir la auditoria');
    });

    try {
        auth()->setUser($e['admisiones']);
        expect(fn () => app(ObservacionService::class)->crear(
            $e['inscripcion'],
            TipoObservacion::GENERAL,
            'Observacion que no debe persistir',
            $e['admisiones'],
        ))->toThrow(RuntimeException::class);
    } finally {
        auth()->logout();
        Auditoria::flushEventListeners();   // se limpia el gancho para el resto de la suite
    }

    expect(Observacion::query()->count())->toBe(0)
        ->and(Auditoria::query()->count())->toBe(0)
        ->and($e['inscripcion']->refresh()->estatus)->toBe(InscripcionEstatus::DRAFT);
});
