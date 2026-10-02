<?php

use App\Enums\RolUsuario;
use App\Enums\TipoContacto;
use App\Livewire\Alumnos\FormAlumno;
use App\Livewire\Alumnos\FormContactos;
use App\Livewire\Alumnos\FormInfoMedica;
use App\Livewire\Alumnos\ListaAlumnos;
use App\Models\Alumno;
use App\Models\Contacto;
use App\Models\InformacionMedica;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/** Código HTTP de la última petición Livewire (una denegación llega como 403). */
function codigoRespuestaLivewire(object $testable): ?int
{
    $propiedad = new ReflectionProperty($testable, 'lastState');
    $propiedad->setAccessible(true);

    return $propiedad->getValue($testable)->getResponse()?->getStatusCode();
}

function datosAlumnoBase(): array
{
    return [
        'nombre' => 'Prueba',
        'apellidos' => 'Sintetica Alumno',
        'curp' => 'ZZZZ010101MZZZZZ01',   // estructura válida, evidentemente ficticia
        'fecha_nacimiento' => '2005-04-15',
        'sexo' => 'FEMENINO',
        'nacionalidad' => 'Mexicana',
        'pais' => 'México',
    ];
}

test('F1: una CURP invalida es rechazada', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());

    Livewire::test(FormAlumno::class)
        ->set(array_merge(datosAlumnoBase(), ['curp' => 'CURP-CORTA']))
        ->call('guardar')
        ->assertHasErrors(['curp']);

    // Longitud correcta pero estructura inválida (mes 13).
    Livewire::test(FormAlumno::class)
        ->set(array_merge(datosAlumnoBase(), ['curp' => 'ZZZZ991301MZZZZZ01']))
        ->call('guardar')
        ->assertHasErrors(['curp']);

    expect(Alumno::count())->toBe(0);
});

test('F2: una CURP duplicada es rechazada', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    $existente = Alumno::factory()->create();

    Livewire::test(FormAlumno::class)
        ->set(array_merge(datosAlumnoBase(), ['curp' => $existente->curp]))
        ->call('guardar')
        ->assertHasErrors(['curp']);

    // Con una CURP distinta sí procede.
    Livewire::test(FormAlumno::class)
        ->set(array_merge(datosAlumnoBase(), ['curp' => 'ZZZZ020202MZZZZZ02']))
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Alumno::count())->toBe(2);
});

test('F3: se guarda alumno, contactos e informacion medica correctamente', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    $alumno = Alumno::factory()->create();

    Livewire::test(FormContactos::class, ['alumno' => $alumno])
        ->call('guardarTutor')
        ->assertHasErrors(['tutor.nombre', 'tutor.parentesco', 'tutor.telefono']);

    Livewire::test(FormContactos::class, ['alumno' => $alumno])
        ->set('tutor.nombre', ' Padre Sintetico ')
        ->set('tutor.parentesco', 'Padre')
        ->set('tutor.telefono', '5512345678')
        ->call('guardarTutor')
        ->assertHasNoErrors();

    Livewire::test(FormContactos::class, ['alumno' => $alumno])
        ->set('emergencia.nombre', 'Hermana Sintetica')
        ->set('emergencia.parentesco', 'Hermana')
        ->set('emergencia.telefono', '5587654321')
        ->call('guardarEmergencia')
        ->assertHasNoErrors();

    Livewire::test(FormInfoMedica::class, ['alumno' => $alumno])
        ->set('tipo_sangre', 'O+')
        ->set('alergias', 'Ninguna (dato sintetico)')
        ->set('tiene_discapacidad', false)
        ->call('guardar')
        ->assertHasNoErrors();

    expect($alumno->contactos()->count())->toBe(2)
        ->and($alumno->contactos()->where('tipo', TipoContacto::TUTOR->value)->count())->toBe(1)
        ->and($alumno->contactos()->where('tipo', TipoContacto::EMERGENCIA->value)->count())->toBe(1);

    $tutor = $alumno->contactos()->where('tipo', TipoContacto::TUTOR->value)->firstOrFail();
    expect($tutor->nombre)->toBe('Padre Sintetico')   // trim aplicado
        ->and($tutor->alumno_id)->toBe($alumno->id);

    $info = $alumno->informacionMedica()->firstOrFail();
    expect($info->tipo_sangre->value)->toBe('O+')
        ->and($info->actualizado_por)->toBe(auth()->id());
});

test('F27: ADMINISTRACION recibe 403 al acceder a informacion medica', function () {
    $alumno = Alumno::factory()->create();

    $this->actingAs(User::factory()->deRol(RolUsuario::ADMINISTRACION)->create())
        ->get(route('alumnos.info-medica', $alumno))
        ->assertForbidden();
});

test('CONTROL_ESCOLAR no tiene pantalla general de informacion medica en la Fase 4', function () {
    $alumno = Alumno::factory()->create();

    // Conserva el gate informacionMedica.view para la futura revisión final,
    // pero la pantalla actual exige informacionMedica.edit (§8 del encargo).
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create())
        ->get(route('alumnos.info-medica', $alumno))
        ->assertForbidden();
});

test('ADMIN y ADMISIONES abren la pantalla de informacion medica', function () {
    $alumno = Alumno::factory()->create();

    foreach ([RolUsuario::ADMIN, RolUsuario::ADMISIONES] as $rol) {
        $this->actingAs(User::factory()->deRol($rol)->create())
            ->get(route('alumnos.info-medica', $alumno))
            ->assertOk();
    }
});

test('ADMIN puede gestionar alumnos', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());

    Livewire::test(FormAlumno::class)
        ->set(datosAlumnoBase())
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect();

    $alumno = Alumno::where('curp', 'ZZZZ010101MZZZZZ01')->firstOrFail();

    Livewire::test(FormAlumno::class, ['alumno' => $alumno])
        ->set('nombre', 'Actualizado')
        ->call('guardar')
        ->assertHasNoErrors();

    expect($alumno->refresh()->nombre)->toBe('Actualizado')
        ->and($alumno->creado_por)->toBe(auth()->id());
});

test('ADMISIONES puede gestionar alumnos', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());

    Livewire::test(FormAlumno::class)
        ->set(datosAlumnoBase())
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Alumno::count())->toBe(1);
});

test('un rol sin permiso recibe 403 al intentar crear alumnos', function () {
    // Sin sesión → redirect a login.
    $this->get(route('alumnos.nuevo'))->assertRedirect('/login');

    $alumno = Alumno::factory()->create();

    foreach ([RolUsuario::CONTROL_ESCOLAR, RolUsuario::ADMINISTRACION] as $rol) {
        $this->actingAs(User::factory()->deRol($rol)->create())
            ->get(route('alumnos.nuevo'))
            ->assertForbidden();
    }

    expect($alumno)->not->toBeNull();
});

test('ADMINISTRACION solo lectura: no modifica el expediente', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMINISTRACION)->create());
    $alumno = Alumno::factory()->create(['nombre' => 'Original']);

    $componente = Livewire::test(FormAlumno::class, ['alumno' => $alumno])
        ->set('nombre', 'Intento')
        ->call('guardar');

    expect(codigoRespuestaLivewire($componente))->toBe(403)
        ->and($alumno->refresh()->nombre)->toBe('Original');
});

test('la relacion medica es 1:1 y no admite un segundo registro', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    $alumno = Alumno::factory()->create();
    $otroUsuario = User::factory()->create();

    Livewire::test(FormInfoMedica::class, ['alumno' => $alumno])
        ->set('tiene_discapacidad', false)
        ->call('guardar')
        ->assertHasNoErrors();

    Livewire::test(FormInfoMedica::class, ['alumno' => $alumno])
        ->set('tipo_sangre', 'A+')
        ->set('tiene_discapacidad', false)
        ->call('guardar')
        ->assertHasNoErrors();

    expect(InformacionMedica::where('alumno_id', $alumno->id)->count())->toBe(1);

    // La restricción 1:1 también existe a nivel de base de datos.
    expect(fn () => InformacionMedica::create([
        'alumno_id' => $alumno->id,
        'tiene_discapacidad' => false,
        'actualizado_por' => $otroUsuario->id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('detalle_discapacidad es obligatorio cuando hay discapacidad', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    $alumno = Alumno::factory()->create();

    Livewire::test(FormInfoMedica::class, ['alumno' => $alumno])
        ->set('tiene_discapacidad', true)
        ->call('guardar')
        ->assertHasErrors(['detalle_discapacidad']);
});

test('el soft delete del alumno funciona', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create());
    $alumno = Alumno::factory()->create();

    Livewire::test(FormAlumno::class, ['alumno' => $alumno])->assertOk();

    $alumno->delete();

    expect($alumno->refresh()->trashed())->toBeTrue()
        ->and(Alumno::count())->toBe(0)
        ->and(Alumno::withTrashed()->count())->toBe(1);

    // El listado ya no lo muestra.
    Livewire::test(ListaAlumnos::class)->assertDontSee($alumno->apellidos);
});

test('el numero de control permanece NULL en la Fase 4', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());

    Livewire::test(FormAlumno::class)
        ->set(datosAlumnoBase())
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Alumno::first()->numero_control)->toBeNull()
        ->and(Alumno::first()->getFillable())->not->toContain('numero_control');
});

test('el listado y la busqueda no exponen datos medicos', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create());
    $alumno = Alumno::factory()->create(['apellidos' => 'Sin Datos Medicos']);
    $alumno->informacionMedica()->create([
        'tipo_sangre' => 'AB-',
        'alergias' => 'ALERTA-ALERGIA-SINTETICA',
        'tiene_discapacidad' => false,
        'actualizado_por' => User::factory()->create()->id,
    ]);

    Livewire::test(ListaAlumnos::class)
        ->assertSee('Sin Datos Medicos')
        ->assertDontSee('ALERTA-ALERGIA-SINTETICA')
        ->assertDontSee('AB-');

    // Búsqueda acotada: no se filtra ni se muestra información médica.
    Livewire::test(ListaAlumnos::class)
        ->set('busqueda', 'Sin Datos Medicos')
        ->assertSee('Sin Datos Medicos');

    Livewire::test(ListaAlumnos::class)
        ->set('busqueda', 'ALERTA-ALERGIA-SINTETICA')
        ->assertDontSee('ALERTA-ALERGIA-SINTETICA')
        ->assertSee('Sin resultados');
});

test('los roles con lectura pueden abrir el listado y el expediente', function () {
    $alumno = Alumno::factory()->create();

    foreach ([RolUsuario::ADMIN, RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR] as $rol) {
        $this->actingAs(User::factory()->deRol($rol)->create())
            ->get(route('alumnos.lista'))
            ->assertOk();

        $this->get(route('alumnos.ver', $alumno))->assertOk();
    }
});
