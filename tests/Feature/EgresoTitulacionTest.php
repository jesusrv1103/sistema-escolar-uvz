<?php

use App\Enums\EstatusInscripcionMateria;
use App\Enums\EstatusTitulacion;
use App\Enums\RolUsuario;
use App\Enums\TipoFormato;
use App\Exceptions\EgresoInvalidoException;
use App\Exceptions\TitulacionInvalidaException;
use App\Livewire\Academico\EgresoIndex;
use App\Livewire\Academico\FormatoAcademico;
use App\Livewire\Academico\TitulacionIndex;
use App\Models\Alumno;
use App\Models\ConceptoPago;
use App\Models\DocumentoGenerado;
use App\Models\Equivalencia;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\InscripcionMateria;
use App\Models\Materia;
use App\Models\MatriculaPeriodo;
use App\Models\Periodo;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\PracticaProfesional;
use App\Models\Programa;
use App\Models\ServicioSocial;
use App\Models\Titulacion;
use App\Models\User;
use App\Services\EgresoService;
use App\Services\FormatoService;
use App\Services\TitulacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class)->group('egreso', 'titulacion');

/**
 * Alumno con TODOS los requisitos de egreso cumplidos:
 * plan vigente, ambas materias aprobadas, servicio social y prácticas validados.
 */
function egreso_alumno_listo(): Alumno
{
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $plan = PlanEstudio::factory()->paraPrograma($programa)->create();
    $version = PlanVersion::factory()->forPlanEstudio($plan)->vigente()->create();
    $materias = Materia::factory()->count(2)
        ->paraPlanVersion($version)
        ->create(['activo' => true, 'creditos' => 4]);

    $matricula = MatriculaPeriodo::factory()
        ->activa()
        ->paraAlumno($alumno)
        ->paraPeriodo($periodo)
        ->paraPrograma($programa)
        ->create(['plan_version_id' => $version->id]);

    foreach ($materias as $materia) {
        $grupo = Grupo::factory()
            ->paraMateria($materia)
            ->paraPeriodo($periodo)
            ->abierto()
            ->create(['activo' => true, 'cupo' => 40]);

        $inscripcion = InscripcionMateria::factory()
            ->paraMatricula($matricula)
            ->paraGrupo($grupo)
            ->cursando()
            ->create();

        $inscripcion->update([
            'calificacion_final' => 80.00,
            'estatus' => EstatusInscripcionMateria::APROBADA,
        ]);
    }

    ServicioSocial::factory()->validado()->create(['alumno_id' => $alumno->id]);
    PracticaProfesional::factory()->validado()->create(['alumno_id' => $alumno->id]);

    return $alumno;
}

dataset('capacidades_2_0_14', [
    ['egreso.ver', RolUsuario::ADMIN->value, true],
    ['egreso.ver', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['egreso.ver', RolUsuario::ADMINISTRACION->value, true],
    ['egreso.ver', RolUsuario::ADMISIONES->value, false],
    ['egreso.ver', RolUsuario::DOCENTE->value, false],

    ['egreso.crear', RolUsuario::ADMIN->value, true],
    ['egreso.crear', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['egreso.crear', RolUsuario::ADMINISTRACION->value, false],
    ['egreso.crear', RolUsuario::ADMISIONES->value, false],

    ['titulacion.ver', RolUsuario::ADMIN->value, true],
    ['titulacion.ver', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['titulacion.ver', RolUsuario::ADMINISTRACION->value, true],
    ['titulacion.ver', RolUsuario::ADMISIONES->value, false],
    ['titulacion.ver', RolUsuario::DOCENTE->value, false],

    ['titulacion.crear', RolUsuario::ADMIN->value, true],
    ['titulacion.crear', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['titulacion.crear', RolUsuario::ADMINISTRACION->value, false],

    ['titulacion.actualizar', RolUsuario::CONTROL_ESCOLAR->value, true],
    ['titulacion.actualizar', RolUsuario::ADMINISTRACION->value, false],
    ['titulacion.actualizar', RolUsuario::ADMISIONES->value, false],
]);

test('capacidad 2.0-14: {capacidad} para {rol} → {esperado}', function (string $capacidad, string $rol, bool $esperado) {
    $usuario = User::factory()->deRol(RolUsuario::from($rol))->create();

    expect(Gate::forUser($usuario)->allows($capacidad))->toBe($esperado);
})->with('capacidades_2_0_14');

test('las pantallas de egreso y titulación responden según rol', function (string $rol, int $codigo) {
    $this->actingAs(User::factory()->deRol(RolUsuario::from($rol))->create());

    $this->get('/egreso')->assertStatus($codigo);
    $this->get('/titulacion')->assertStatus($codigo);
})->with([
    [RolUsuario::ADMIN->value, 200],
    [RolUsuario::CONTROL_ESCOLAR->value, 200],
    [RolUsuario::ADMINISTRACION->value, 200], // sólo lectura
    [RolUsuario::ADMISIONES->value, 403],
    [RolUsuario::DOCENTE->value, 403],
]);

test('la verificación de egreso explica exactamente qué falta', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $plan = PlanEstudio::factory()->paraPrograma($programa)->create();
    $version = PlanVersion::factory()->forPlanEstudio($plan)->vigente()->create();

    MatriculaPeriodo::factory()
        ->activa()
        ->paraAlumno($alumno)
        ->paraPeriodo($periodo)
        ->paraPrograma($programa)
        ->create(['plan_version_id' => $version->id]);

    $verificacion = app(EgresoService::class)->verificar($alumno);

    expect($verificacion['egresado'])->toBeFalse()
        ->and($verificacion['puede_registrar'])->toBeFalse()
        ->and($verificacion['faltantes'])->toContain('Materias del plan cubiertas')
        ->and($verificacion['faltantes'])->toContain('Créditos aprobados requeridos')
        ->and($verificacion['faltantes'])->toContain('Servicio Social validado')
        ->and($verificacion['faltantes'])->toContain('Prácticas profesionales validadas')
        ->and(collect($verificacion['requisitos'])->firstWhere('clave', 'plan')['cumplido'])->toBeTrue();
});

test('el egreso se registra sólo con todos los requisitos y deja auditoría', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());
    $alumno = egreso_alumno_listo();

    $egreso = app(EgresoService::class)->registrar($alumno);

    expect($egreso->alumno_id)->toBe($alumno->id)
        ->and($egreso->creditos_requeridos)->toBe(8)
        ->and($egreso->creditos_acumulados)->toBe(8)
        ->and($egreso->registrado_at)->not->toBeNull()
        ->and(collect($egreso->requisitos)->every(fn ($r) => $r['cumplido']))->toBeTrue()
        ->and($egreso->auditorias()->pluck('accion'))->toContain('egreso_registrado');

    // Un solo egreso por alumno
    expect(fn () => app(EgresoService::class)->registrar($alumno))
        ->toThrow(EgresoInvalidoException::class);
});

test('la pantalla de egreso muestra EGRESADO y NO EGRESADO con sus faltantes', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());

    $sinRequisitos = Alumno::factory()->create();

    Livewire::test(EgresoIndex::class)
        ->call('seleccionarAlumno', (string) $sinRequisitos->id)
        ->assertSee('No egresado')
        ->assertSee('Servicio Social validado')
        ->assertDontSee('Registrar egreso');

    $listo = egreso_alumno_listo();

    Livewire::test(EgresoIndex::class)
        ->call('seleccionarAlumno', (string) $listo->id)
        ->assertSee('No egresado')
        ->assertSee('Registrar egreso')
        ->call('registrarEgreso')
        ->assertHasNoErrors()
        ->assertSee('Egresado');

    expect($listo->egreso()->exists())->toBeTrue();
});

test('una baja definitiva impide registrar el egreso', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());
    $alumno = egreso_alumno_listo();

    app(\App\Services\BajaService::class)->registrar(
        $alumno,
        \App\Enums\TipoBaja::DEFINITIVA,
        'Cambio de institución',
        now()->format('Y-m-d'),
    );

    expect(fn () => app(EgresoService::class)->registrar($alumno))
        ->toThrow(EgresoInvalidoException::class);

    $verificacion = app(EgresoService::class)->verificar($alumno);

    expect($verificacion['puede_registrar'])->toBeFalse()
        ->and($verificacion['faltantes'])->toContain('Sin baja vigente o definitiva');
});

test('las equivalencias aprobadas cubren materias y aportan créditos', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());

    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $plan = PlanEstudio::factory()->paraPrograma($programa)->create();
    $version = PlanVersion::factory()->forPlanEstudio($plan)->vigente()->create();
    $materias = Materia::factory()->count(2)
        ->paraPlanVersion($version)
        ->create(['activo' => true, 'creditos' => 4]);

    $matricula = MatriculaPeriodo::factory()
        ->activa()
        ->paraAlumno($alumno)
        ->paraPeriodo($periodo)
        ->paraPrograma($programa)
        ->create(['plan_version_id' => $version->id]);

    // Materia 1 aprobada en el sistema
    $grupo = Grupo::factory()->paraMateria($materias->first())->paraPeriodo($periodo)->abierto()
        ->create(['activo' => true, 'cupo' => 40]);
    $inscripcion = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupo)->cursando()->create();
    $inscripcion->update(['calificacion_final' => 90.00, 'estatus' => EstatusInscripcionMateria::APROBADA]);

    // Materia 2 cubierta por equivalencia + créditos libres equivalentes
    Equivalencia::create([
        'alumno_id' => $alumno->id,
        'materia_id' => $materias->last()->id,
        'origen' => 'Universidad demo',
        'creditos' => 4,
        'estatus' => \App\Enums\EstatusEquivalencia::APROBADA,
        'autorizada_por' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()->id,
        'fecha_dictamen' => now()->format('Y-m-d'),
    ]);
    Equivalencia::create([
        'alumno_id' => $alumno->id,
        'materia_id' => null,
        'origen' => 'Universidad demo (crédito libre)',
        'creditos' => 3,
        'estatus' => \App\Enums\EstatusEquivalencia::APROBADA,
        'autorizada_por' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()->id,
        'fecha_dictamen' => now()->format('Y-m-d'),
    ]);

    ServicioSocial::factory()->validado()->create(['alumno_id' => $alumno->id]);
    PracticaProfesional::factory()->validado()->create(['alumno_id' => $alumno->id]);

    $verificacion = app(EgresoService::class)->verificar($alumno);

    expect($verificacion['faltantes'])->toBe([])
        ->and($verificacion['puede_registrar'])->toBeTrue()
        ->and($verificacion['creditos_requeridos'])->toBe(8)
        ->and($verificacion['creditos_acumulados'])->toBe(11) // 4 (histórico) + 4 (equiv. de materia) + 3 (libre)
        ->and(app(EgresoService::class)->registrar($alumno)->creditos_acumulados)->toBe(11);
});

test('la deuda no es requisito de egreso', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());
    $alumno = egreso_alumno_listo();

    $inscripcion = Inscripcion::factory()->create(['alumno_id' => $alumno->id]);
    ConceptoPago::factory()->create([
        'inscripcion_id' => $inscripcion->id,
        'monto_total' => '5000.00',
        'estatus' => 'PENDIENTE',
    ]);

    expect(ConceptoPago::where('inscripcion_id', $inscripcion->id)->count())->toBe(1);

    $egreso = app(EgresoService::class)->registrar($alumno);

    expect($egreso->exists)->toBeTrue();
});

test('la titulación exige egreso registrado', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());
    $sinEgreso = Alumno::factory()->create();

    expect(fn () => app(TitulacionService::class)->crear($sinEgreso, ['modalidad' => null]))
        ->toThrow(TitulacionInvalidaException::class);
});

test('control escolar crea y actualiza el expediente de titulación', function () {
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $this->actingAs($control);

    $alumno = egreso_alumno_listo();
    app(EgresoService::class)->registrar($alumno);

    $titulacion = app(TitulacionService::class)->crear($alumno, [
        'modalidad' => 'Tesis',
        'folio_interno' => null,
    ]);

    expect($titulacion->estatus)->toBe(EstatusTitulacion::EN_TRAMITE)
        ->and($titulacion->folio_interno)->toBeNull() // No se genera folio automático
        ->and($titulacion->egreso_id)->toBe($alumno->egreso->id)
        ->and($titulacion->auditorias()->pluck('accion'))->toContain('titulacion_registrada');

    app(TitulacionService::class)->actualizar($titulacion, [
        'modalidad' => 'Tesis',
        'folio_interno' => 'EXP-2026-001',
        'estatus' => EstatusTitulacion::TITULADO->value,
    ]);

    $titulacion->refresh();

    expect($titulacion->estatus)->toBe(EstatusTitulacion::TITULADO)
        ->and($titulacion->folio_interno)->toBe('EXP-2026-001')
        ->and($titulacion->esTitulado())->toBeTrue();
});

test('el folio interno de titulación es captura manual con unicidad', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());
    $alumno = egreso_alumno_listo();
    app(EgresoService::class)->registrar($alumno);

    app(TitulacionService::class)->crear($alumno, ['folio_interno' => 'EXP-2026-777']);

    $otroAlumno = egreso_alumno_listo();
    app(EgresoService::class)->registrar($otroAlumno);

    expect(fn () => app(TitulacionService::class)->crear($otroAlumno, ['folio_interno' => 'EXP-2026-777']))
        ->toThrow(TitulacionInvalidaException::class);

    expect(Titulacion::count())->toBe(1);
});

test('un alumno sin egreso no puede tener expediente de titulación desde la UI', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create());

    // La lista de alumnos del modal sólo incluye egresados.
    $noEgresado = Alumno::factory()->create();
    $egresado = egreso_alumno_listo();
    app(EgresoService::class)->registrar($egresado);

    Livewire::test(TitulacionIndex::class)
        ->call('abrirModalCrear')
        ->assertHasNoErrors()
        ->assertSee($egresado->curp)
        ->assertDontSee($noEgresado->curp)
        ->call('guardar')
        ->assertHasErrors(['alumno']);

    expect(Titulacion::count())->toBe(0);
});

test('administración sólo consulta egreso y titulación', function () {
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMINISTRACION)->create());
    $alumno = Alumno::factory()->create();

    Livewire::test(EgresoIndex::class)
        ->call('seleccionarAlumno', (string) $alumno->id)
        ->call('registrarEgreso')
        ->assertForbidden();

    Livewire::test(TitulacionIndex::class)
        ->call('abrirModalCrear')
        ->assertForbidden();
});

test('la constancia de terminación no tiene folio automático', function () {
    expect(TipoFormato::CONSTANCIA_TERMINACION->requiereFolio())->toBeFalse()
        ->and(TipoFormato::CONSTANCIA_TERMINACION->tipoConsecutivo())->toBeNull()
        ->and(TipoFormato::CONSTANCIA_TERMINACION->requiereAlumno())->toBeTrue()
        ->and(TipoFormato::CONSTANCIA_TERMINACION->label())->toBe('Constancia de Terminación');
});

test('la constancia de terminación sólo se emite para alumnos egresados', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $this->actingAs($usuario);
    $sinEgreso = Alumno::factory()->create();

    Livewire::test(FormatoAcademico::class)
        ->call('generarFormato', 'constancia-terminacion', $sinEgreso)
        ->assertHasErrors(['generar']);

    expect(DocumentoGenerado::count())->toBe(0);
});

test('la constancia de terminación se genera con los datos de egreso y sin folio', function () {
    $usuario = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $this->actingAs($usuario);
    $alumno = egreso_alumno_listo();
    app(EgresoService::class)->registrar($alumno);

    $pdfPath = app(FormatoService::class)->generar(
        TipoFormato::CONSTANCIA_TERMINACION,
        $alumno,
        null,
        null,
        [],
        $usuario,
    );

    expect(basename($pdfPath))->toStartWith('constancia_terminacion_')
        ->and(Storage::disk('private')->exists($pdfPath))->toBeTrue();

    $documento = DocumentoGenerado::where('alumno_id', $alumno->id)
        ->where('tipo', TipoFormato::CONSTANCIA_TERMINACION->value)
        ->first();

    expect($documento)->not->toBeNull()
        ->and($documento->folio)->toBeNull();
});
