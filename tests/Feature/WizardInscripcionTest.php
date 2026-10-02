<?php

use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Enums\TipoContacto;
use App\Livewire\Inscripciones\ListaInscripciones;
use App\Livewire\Inscripciones\WizardInscripcion;
use App\Models\Alumno;
use App\Models\Auditoria;
use App\Models\Contacto;
use App\Models\InformacionMedica;
use App\Models\Inscripcion;
use App\Models\Periodo;
use App\Models\User;
use Database\Factories\InscripcionFactory;

/*
|--------------------------------------------------------------------------
| Fase 6 — Wizard de inscripción (F4, F5) y listado con filtros
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| División de pasos aprobada: 1 selección/montos · 2 datos personales ·
| 3 contacto tutor · 4 contacto emergencia · 5 información médica ·
| 6 revisión → DATA_COMPLETE.
*/

/** Alumno con expediente completo: personales + tutor + emergencia + médica. */
function alumnoListoParaInscribir(): Alumno
{
    $alumno = Alumno::factory()->create();

    Contacto::factory()->create(['alumno_id' => $alumno->id, 'tipo' => TipoContacto::TUTOR]);
    Contacto::factory()->create(['alumno_id' => $alumno->id, 'tipo' => TipoContacto::EMERGENCIA]);
    InformacionMedica::factory()->create(['alumno_id' => $alumno->id]);

    return $alumno;
}

/** Código HTTP de la última petición Livewire (una denegación llega como 403). */
function codigoRespuestaWizard(object $testable): ?int
{
    $propiedad = new ReflectionProperty($testable, 'lastState');
    $propiedad->setAccessible(true);

    return $propiedad->getValue($testable)->getResponse()?->getStatusCode();
}

/** Paso 1 completo con valores válidos (programa y periodo sintéticos activos). */
function seleccionValida(Alumno $alumno): array
{
    return [
        'alumnoId' => $alumno->id,
        'programaId' => InscripcionFactory::programaPrueba()->id,
        'periodoId' => InscripcionFactory::periodoPrueba()->id,
        'monto_inscripcion' => '1500.00',
        'monto_mensualidad' => '1200.00',
    ];
}

/** Recorre el wizard hasta el paso 6 con un alumno completo. */
function wizardHastaRevision(Alumno $alumno): void
{
    Livewire::test(WizardInscripcion::class)
        ->set(seleccionValida($alumno))
        ->call('avanzar')->assertHasNoErrors()
        ->call('avanzar')->assertHasNoErrors()
        ->call('avanzar')->assertHasNoErrors()
        ->call('avanzar')->assertHasNoErrors()
        ->call('avanzar')->assertHasNoErrors()
        ->assertSet('paso', 6);
}

test('F4: completar los 6 pasos transiciona a DATA_COMPLETE', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    $alumno = alumnoListoParaInscribir();

    Livewire::test(WizardInscripcion::class)
        ->set(seleccionValida($alumno))
        ->call('avanzar')->assertHasNoErrors()->assertSet('paso', 2)
        ->call('avanzar')->assertHasNoErrors()->assertSet('paso', 3)
        ->call('avanzar')->assertHasNoErrors()->assertSet('paso', 4)
        ->call('avanzar')->assertHasNoErrors()->assertSet('paso', 5)
        ->call('avanzar')->assertHasNoErrors()->assertSet('paso', 6)
        ->call('completar')->assertHasNoErrors()->assertSet('paso', 6);

    $inscripcion = Inscripcion::firstOrFail();

    expect($inscripcion->estatus)->toBe(InscripcionEstatus::DATA_COMPLETE)
        ->and($inscripcion->datos_completados_at)->not->toBeNull()
        ->and($inscripcion->alumno_id)->toBe($alumno->id)
        ->and($inscripcion->periodo_id)->toBe(InscripcionFactory::periodoPrueba()->id)
        ->and($inscripcion->programa_id)->toBe(InscripcionFactory::programaPrueba()->id);
});

test('F5: un paso incompleto no avanza ni permite saltos', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    $alumno = alumnoListoParaInscribir();

    // Paso 1 incompleto (sin periodo ni montos): no avanza y no crea borrador.
    Livewire::test(WizardInscripcion::class)
        ->set('alumnoId', $alumno->id)
        ->set('programaId', InscripcionFactory::programaPrueba()->id)
        ->call('avanzar')
        ->assertHasErrors(['periodoId', 'monto_inscripcion', 'monto_mensualidad'])
        ->assertSet('paso', 1);

    expect(Inscripcion::count())->toBe(0);

    // Paso 3 sin tutor: no avanza.
    $sinTutor = Alumno::factory()->create();
    Contacto::factory()->create(['alumno_id' => $sinTutor->id, 'tipo' => TipoContacto::EMERGENCIA]);
    InformacionMedica::factory()->create(['alumno_id' => $sinTutor->id]);
    Inscripcion::factory()->create(['alumno_id' => $sinTutor->id]);
    $inscripcion = Inscripcion::firstOrFail();

    Livewire::test(WizardInscripcion::class, ['inscripcion' => $inscripcion])
        ->assertSet('paso', 3)
        ->call('avanzar')
        ->assertHasErrors(['paso'])
        ->assertSet('paso', 3);

    // No se saltan pasos pendientes.
    Livewire::test(WizardInscripcion::class, ['inscripcion' => $inscripcion])
        ->call('irAPaso', 6)
        ->assertHasErrors(['paso'])
        ->assertSet('paso', 3);
});

test('la creacion inicial queda en DRAFT con snapshots y auditoria', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($usuario);
    $alumno = alumnoListoParaInscribir();

    Livewire::test(WizardInscripcion::class)
        ->set(seleccionValida($alumno))
        ->call('avanzar')
        ->assertHasNoErrors()
        ->assertSet('paso', 2);

    $inscripcion = Inscripcion::firstOrFail();

    expect($inscripcion->estatus)->toBe(InscripcionEstatus::DRAFT)
        ->and($inscripcion->datos_completados_at)->toBeNull()
        ->and($inscripcion->creado_por)->toBe($usuario->id)
        ->and((float) $inscripcion->monto_inscripcion)->toBe(1500.00)
        ->and((float) $inscripcion->monto_mensualidad)->toBe(1200.00)
        ->and((float) $inscripcion->monto_descuento)->toBe(0.00)
        ->and($inscripcion->alumno_id)->toBe($alumno->id);

    $creacion = Auditoria::where('accion', 'inscripcion_creada')->firstOrFail();

    expect($creacion->auditable_id)->toBe($inscripcion->id)
        ->and($creacion->auditable_type)->toBe(Inscripcion::class)
        ->and($creacion->user_id)->toBe($usuario->id)
        ->and($creacion->estado_nuevo)->toBe('DRAFT');
});

test('el borrador persiste en BD, se reanuda y el retroceso no borra datos', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    $alumno = alumnoListoParaInscribir();

    Livewire::test(WizardInscripcion::class)
        ->set(seleccionValida($alumno))
        ->call('avanzar')
        ->assertHasNoErrors();

    // "Recarga": componente nuevo que reanuda desde BD.
    // (Alumno completo → todos los pasos listos → aterriza en la revisión;
    //  la reanudación en el primer paso INCOMPLEto la cubre F5 con paso 3.)
    $inscripcion = Inscripcion::firstOrFail();

    Livewire::test(WizardInscripcion::class, ['inscripcion' => $inscripcion])
        ->assertSet('paso', 6)
        ->assertSet('alumnoId', $alumno->id)
        ->assertSet('programaId', $inscripcion->programa_id)
        ->assertSet('periodoId', $inscripcion->periodo_id)
        ->assertSet('monto_inscripcion', $inscripcion->monto_inscripcion)
        // Retroceder visualmente no borra datos ni duplica el borrador.
        ->call('atras')->assertSet('paso', 5)
        ->call('irAPaso', 1)->assertSet('paso', 1)
        ->call('avanzar')->assertHasNoErrors()->assertSet('paso', 2);

    expect(Inscripcion::count())->toBe(1)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::DRAFT);
});

test('un programa inactivo es rechazado en el paso 1', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    $alumno = alumnoListoParaInscribir();

    $programa = InscripcionFactory::programaPrueba();
    $programa->update(['activo' => false]);

    Livewire::test(WizardInscripcion::class)
        ->set(seleccionValida($alumno))
        ->call('avanzar')
        ->assertHasErrors(['programaId' => 'El programa seleccionado no está activo o no existe.'])
        ->assertSet('paso', 1);

    expect(Inscripcion::count())->toBe(0);
});

test('un periodo inactivo es rechazado en el paso 1', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    $alumno = alumnoListoParaInscribir();

    InscripcionFactory::periodoPrueba()->update(['activo' => false]);

    Livewire::test(WizardInscripcion::class)
        ->set(seleccionValida($alumno))
        ->call('avanzar')
        ->assertHasErrors(['periodoId'])
        ->assertSet('paso', 1);

    expect(Inscripcion::count())->toBe(0);
});

test('montos invalidos son rechazados (numeric, min:0, maximo 2 decimales)', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    $alumno = alumnoListoParaInscribir();

    Livewire::test(WizardInscripcion::class)
        ->set(array_merge(seleccionValida($alumno), ['monto_inscripcion' => 'abc']))
        ->call('avanzar')
        ->assertHasErrors(['monto_inscripcion'])
        ->assertSet('paso', 1);

    Livewire::test(WizardInscripcion::class)
        ->set(array_merge(seleccionValida($alumno), ['monto_inscripcion' => '-5']))
        ->call('avanzar')
        ->assertHasErrors(['monto_inscripcion'])
        ->assertSet('paso', 1);

    Livewire::test(WizardInscripcion::class)
        ->set(array_merge(seleccionValida($alumno), ['monto_mensualidad' => '10.999']))
        ->call('avanzar')
        ->assertHasErrors(['monto_mensualidad'])
        ->assertSet('paso', 1);

    // Compatibles con DECIMAL(10,2): enteros y con 1–2 decimales sí se aceptan.
    Livewire::test(WizardInscripcion::class)
        ->set(array_merge(seleccionValida($alumno), [
            'monto_inscripcion' => '1500',
            'monto_mensualidad' => '1200.5',
        ]))
        ->call('avanzar')
        ->assertHasNoErrors();

    expect(Inscripcion::count())->toBe(1);
});

test('la transicion final usa InscripcionService y genera auditoria', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($usuario);
    $alumno = alumnoListoParaInscribir();

    wizardHastaRevision($alumno);

    Livewire::test(WizardInscripcion::class, ['inscripcion' => Inscripcion::firstOrFail()])
        ->call('completar')
        ->assertHasNoErrors();

    $inscripcion = Inscripcion::firstOrFail();
    $registro = $inscripcion->auditorias()->where('accion', 'estado_cambiado')->firstOrFail();

    expect($registro->estado_anterior)->toBe('DRAFT')
        ->and($registro->estado_nuevo)->toBe('DATA_COMPLETE')
        ->and($registro->user_id)->toBe($usuario->id)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::DATA_COMPLETE)
        ->and($inscripcion->datos_completados_at)->not->toBeNull();
});

test('roles sin captura reciben 403 en el wizard y el listado abre en lectura', function () {
    // Sin sesión → redirect a login.
    $this->get(route('inscripciones.nueva'))->assertRedirect('/login');

    foreach ([RolUsuario::CONTROL_ESCOLAR, RolUsuario::ADMINISTRACION] as $rol) {
        $this->actingAs(User::factory()->deRol($rol)->create())
            ->get(route('inscripciones.nueva'))
            ->assertForbidden();
    }

    // El listado es lectura para los roles (matriz §8.2).
    foreach ([RolUsuario::ADMISIONES, RolUsuario::CONTROL_ESCOLAR, RolUsuario::ADMINISTRACION] as $rol) {
        $this->actingAs(User::factory()->deRol($rol)->create())
            ->get(route('inscripciones.lista'))
            ->assertOk();
    }
});

test('las acciones sensibles del wizard exigen autorizacion server-side', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    $alumno = alumnoListoParaInscribir();
    wizardHastaRevision($alumno);
    $inscripcion = Inscripcion::firstOrFail();

    // ADMINISTRACION ve el listado y el borrador, pero no puede completarlo.
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMINISTRACION)->create());

    $componente = Livewire::test(WizardInscripcion::class, ['inscripcion' => $inscripcion])
        ->call('completar');

    expect(codigoRespuestaWizard($componente))->toBe(403)
        ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::DRAFT);

    // Tampoco puede editar la selección del paso 1.
    $componente = Livewire::test(WizardInscripcion::class, ['inscripcion' => $inscripcion])
        ->call('irAPaso', 1)
        ->call('avanzar');

    expect(codigoRespuestaWizard($componente))->toBe(403)
        ->and($inscripcion->refresh()->monto_inscripcion)->toBe($inscripcion->monto_inscripcion);
});

test('el listado no expone informacion medica', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    $alumno = Alumno::factory()->create();
    InformacionMedica::factory()->create([
        'alumno_id' => $alumno->id,
        'alergias' => 'ALERGIA-SINTETICA-NO-VISIBLE',
    ]);
    Inscripcion::factory()->create(['alumno_id' => $alumno->id]);

    Livewire::test(ListaInscripciones::class)
        ->assertSee($alumno->curp)
        ->assertDontSee('ALERGIA-SINTETICA-NO-VISIBLE');
});

test('el listado filtra por alumno, periodo y estatus', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());

    $alumnoA = Alumno::factory()->create(['apellidos' => 'Filtro Azul', 'nombre' => 'Ana']);
    $alumnoB = Alumno::factory()->create(['apellidos' => 'Filtro Verde', 'nombre' => 'Bruno']);

    Inscripcion::factory()->create(['alumno_id' => $alumnoA->id]);

    $otroPeriodo = Periodo::create([
        'clave' => 'OTRO-2026',
        'nombre' => 'Otro periodo sintetico',
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-05-31',
        'activo' => true,
    ]);

    Inscripcion::factory()
        ->enEstatus(InscripcionEstatus::DATA_COMPLETE)
        ->create(['alumno_id' => $alumnoB->id, 'periodo_id' => $otroPeriodo->id]);

    // por alumno
    Livewire::test(ListaInscripciones::class)
        ->set('busqueda', 'Filtro Azul')
        ->assertSee('Filtro Azul')
        ->assertDontSee('Filtro Verde');

    // por periodo
    Livewire::test(ListaInscripciones::class)
        ->set('filtroPeriodo', $otroPeriodo->id)
        ->assertSee('Filtro Verde')
        ->assertDontSee('Filtro Azul');

    // por estatus
    Livewire::test(ListaInscripciones::class)
        ->set('filtroEstatus', 'DATA_COMPLETE')
        ->assertSee('Filtro Verde')
        ->assertDontSee('Filtro Azul');
});
