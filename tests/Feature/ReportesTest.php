<?php

use App\Enums\EstatusInscripcionMateria;
use App\Enums\EstatusPago;
use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Enums\TipoAjusteFinanciero;
use App\Enums\TipoBaja;
use App\Livewire\Reportes\PanelReportes;
use App\Models\AjusteFinanciero;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Baja;
use App\Models\Beca;
use App\Models\BecaAlumno;
use App\Models\ConceptoPago;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\InscripcionMateria;
use App\Models\MatriculaPeriodo;
use App\Models\Pago;
use App\Models\Periodo;
use App\Models\Programa;
use App\Models\User;
use App\Services\EstadoCuentaService;
use App\Services\Reportes\CatalogoReportes;
use App\Services\Reportes\ReporteService;

/*
|--------------------------------------------------------------------------
| Fase 2.0-17 — Reportes operativos (pantalla `/reportes`)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| Cubren: la matriz de 6 roles (ruta + acciones Livewire + menú), filtros y
| lista blanca, paginación, estados vacíos, resúmenes de conteo, la igualdad
| de cartera contra `EstadoCuentaService` y la PII mínima.
| Sin mocks: todo sale de `ReporteService`/`CatalogoReportes` reales.
*/

/*|--------------------------------------------------------------------------
| Escenario sintético único (sufijo de fase para no colisionar)
|--------------------------------------------------------------------------*/

/**
 * Escenario mínimo con datos 100 % sintéticos y sin solapes con otros tests:
 *
 *  - 2 inscripciones en `periodo` (ENROLLED y DRAFT) con programas distintos;
 *  - 1 matrícula con 4 cargas (APROBADA, REPROBADA, BAJA, EXTRAORDINARIO);
 *  - 2 asistencias sobre la carga aprobada;
 *  - 1 baja registrada;
 *  - 1 concepto con 3 pagos (VALIDADO / PENDIENTE / RECHAZADO);
 *  - 1 ajuste de beca ligado a la beca del alumno.
 *
 * @return array<string, mixed>
 */
function escenarioReportes2017(): array
{
    $registrante = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    $periodo = Periodo::factory()->create(['clave' => 'REP-2026', 'nombre' => 'Periodo reportes 2026']);
    $programa = Programa::factory()->create(['clave' => 'LREP', 'nombre' => 'Licenciatura Reportes']);
    $programaOtro = Programa::factory()->create(['clave' => 'LOTRA', 'nombre' => 'Licenciatura Otra']);

    // PII marcada a propósito: NO debe aparecer en pantalla ni en el CSV.
    $alumno = Alumno::factory()->create([
        'numero_control' => 'NCRP0001',
        'curp' => 'ZZZZ010101HNEAAA09',
        'correo' => 'sintetico.reportes@example.test',
        'telefono' => '5500000001',
        'calle' => 'Calle Sintetica Reportes 1',
    ]);
    $alumno2 = Alumno::factory()->create(['numero_control' => 'NCRP0002']);

    // R1 — Ingresos.
    $inscripcion1 = Inscripcion::factory()
        ->enEstatus(InscripcionEstatus::ENROLLED)
        ->enPeriodo($periodo)
        ->create(['programa_id' => $programa->id, 'alumno_id' => $alumno->id]);
    $inscripcion2 = Inscripcion::factory()
        ->enEstatus(InscripcionEstatus::DRAFT)
        ->enPeriodo($periodo)
        ->create(['programa_id' => $programaOtro->id, 'alumno_id' => $alumno2->id]);

    // R2/R3/R4 — matrícula, carga y asistencia.
    $matricula = MatriculaPeriodo::factory()
        ->paraAlumno($alumno)
        ->paraPeriodo($periodo)
        ->paraPrograma($programa)
        ->activa()
        ->create();

    $grupos = collect(range(1, 4))->map(fn () => Grupo::factory()->paraPeriodo($periodo)->create())->all();

    $cargaAprobada = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupos[0])->aprobada()->create();
    $cargaReprobada = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupos[1])->reprobada()->create();
    $cargaBaja = InscripcionMateria::factory()->paraMatricula($matricula)->paraGrupo($grupos[2])->baja()->create();
    $cargaExtraordinario = InscripcionMateria::factory()
        ->paraMatricula($matricula)
        ->paraGrupo($grupos[3])
        ->state([
            'estatus' => EstatusInscripcionMateria::EXTRAORDINARIO->value,
            'calificacion_final' => 75,
        ])
        ->create();

    foreach ([['2026-09-01', true], ['2026-09-02', false]] as [$fecha, $presente]) {
        Asistencia::create([
            'inscripcion_materia_id' => $cargaAprobada->id,
            'fecha' => $fecha,
            'presente' => $presente,
            'registrado_por' => $registrante->id,
        ]);
    }

    // R5 — movimientos (bajas).
    $baja = Baja::create([
        'alumno_id' => $alumno2->id,
        'inscripcion_materia_id' => $cargaBaja->id,
        'tipo' => TipoBaja::DEFINITIVA,
        'motivo' => 'Motivo sintetico del reporte de bajas',
        'fecha' => '2026-09-10',
        'registrado_por' => $registrante->id,
    ]);

    // R6 — financiero.
    $concepto = ConceptoPago::factory()->inscripcion()->create([
        'inscripcion_id' => $inscripcion1->id,
        'concepto' => 'Inscripcion sintetica reportes',
        'monto' => 1500,
        'descuento' => 0,
        'monto_total' => 1500,
        'fecha_vencimiento' => '2026-09-15',
    ]);

    $pagoValidado = Pago::factory()->create([
        'concepto_pago_id' => $concepto->id,
        'monto' => 900,
        'estatus' => EstatusPago::VALIDADO->value,
        'folio' => 'FOL-REP-0001',
    ]);
    $pagoPendiente = Pago::factory()->create([
        'concepto_pago_id' => $concepto->id,
        'monto' => 400,
        'estatus' => EstatusPago::PENDIENTE->value,
        'folio' => 'FOL-REP-0002',
    ]);
    $pagoRechazado = Pago::factory()->rechazado()->create([
        'concepto_pago_id' => $concepto->id,
        'monto' => 300,
        'folio' => 'FOL-REP-0003',
    ]);

    $beca = Beca::factory()->porcentaje()->paraPeriodo($periodo)->create();
    $becaAlumno = BecaAlumno::factory()->aprobada()->create([
        'beca_id' => $beca->id,
        'alumno_id' => $alumno->id,
        'periodo_id' => $periodo->id,
    ]);
    $ajuste = AjusteFinanciero::create([
        'alumno_id' => $alumno->id,
        'concepto_pago_id' => $concepto->id,
        'beca_alumno_id' => $becaAlumno->id,
        'tipo' => TipoAjusteFinanciero::DESCUENTO_BECA,
        'monto' => 200,
        'registrado_por' => $registrante->id,
        'registrado_at' => now(),
    ]);

    return compact(
        'registrante', 'periodo', 'programa', 'programaOtro', 'alumno', 'alumno2',
        'inscripcion1', 'inscripcion2', 'matricula', 'grupos', 'cargaAprobada',
        'cargaReprobada', 'cargaBaja', 'cargaExtraordinario', 'baja', 'concepto',
        'pagoValidado', 'pagoPendiente', 'pagoRechazado', 'beca', 'becaAlumno', 'ajuste',
    );
}

/** @return array<string, string> */
function chipsReportes2017(array $resumen): array
{
    return collect($resumen)->mapWithKeys(fn ($chip) => [$chip['label'] => $chip['valor']])->all();
}

/*|--------------------------------------------------------------------------
| Matriz de autorización
|--------------------------------------------------------------------------*/

test('la matriz de seis roles abre solo sus categorias en /reportes', function () {
    // Invitado: siempre al login (antes de tocar cualquier `actingAs`).
    $this->get('/reportes')->assertRedirect(route('login'));

    $matriz = [
        RolUsuario::ADMIN->value => ['ingreso', 'academicos', 'financieros'],
        RolUsuario::ADMISIONES->value => ['ingreso'],
        RolUsuario::ADMINISTRACION->value => ['financieros'],
        RolUsuario::CONTROL_ESCOLAR->value => ['academicos'],
    ];

    foreach ($matriz as $rol => $categorias) {
        $usuario = User::factory()->deRol(RolUsuario::from($rol))->create();

        foreach ($categorias as $categoria) {
            $this->actingAs($usuario)->get('/reportes?categoria=' . $categoria)->assertOk();
        }

        foreach (array_keys(CatalogoReportes::CATEGORIAS) as $categoria) {
            if (in_array($categoria, $categorias, true)) {
                continue;
            }

            $this->actingAs($usuario)->get('/reportes?categoria=' . $categoria)->assertForbidden();
        }
    }

    foreach ([RolUsuario::DOCENTE, RolUsuario::ALUMNO] as $rol) {
        $this->actingAs(User::factory()->deRol($rol)->create())->get('/reportes')->assertForbidden();
    }

    // Categoría inexistente (manipulada) → 404, nunca una pantalla ajena.
    $this->actingAs(User::factory()->deRol(RolUsuario::ADMIN)->create())
        ->get('/reportes?categoria=inexistente')
        ->assertNotFound();
});

test('el grupo Reportes del menu solo se muestra con la capacidad', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $this->actingAs($admin)->get('/dashboard')->assertOk()
        ->assertSee('/reportes?categoria=ingreso', false)
        ->assertSee('/reportes?categoria=academicos', false)
        ->assertSee('/reportes?categoria=financieros', false);

    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $this->actingAs($control)->get('/dashboard')->assertOk()
        ->assertSee('/reportes?categoria=academicos', false)
        ->assertDontSee('/reportes?categoria=ingreso', false)
        ->assertDontSee('/reportes?categoria=financieros', false);

    $docente = User::factory()->deRol(RolUsuario::DOCENTE)->create();
    $this->actingAs($docente)->get(route('docente.inicio'))->assertOk()
        ->assertDontSee('reportes?categoria=', false)
        ->assertDontSee('Reportes', false);

    $alumno = User::factory()->deRol(RolUsuario::ALUMNO)->create();
    $this->actingAs($alumno)->get(route('alumno.inicio'))->assertOk()
        ->assertDontSee('reportes?categoria=', false);
});

test('las acciones del componente reautorizan su destino', function () {
    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();

    Livewire::actingAs($control)->test(PanelReportes::class)
        ->assertSet('categoria', 'academicos')
        ->call('seleccionarCategoria', 'financieros')
        ->assertForbidden();

    Livewire::actingAs($control)->test(PanelReportes::class)
        ->call('seleccionarCategoria', 'ingreso')
        ->assertForbidden();

    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();

    Livewire::actingAs($admisiones)->test(PanelReportes::class)
        ->call('seleccionarReporte', 'cartera')
        ->assertForbidden();

    Livewire::actingAs($admisiones)->test(PanelReportes::class)
        ->call('seleccionarTipo', 'bajas')
        ->assertForbidden();

    Livewire::actingAs(User::factory()->deRol(RolUsuario::DOCENTE)->create())
        ->test(PanelReportes::class)
        ->assertForbidden();

    // Un rol autorizado navega dentro de su categoría.
    Livewire::actingAs($control)->test(PanelReportes::class)
        ->call('seleccionarReporte', 'movimientos')
        ->assertSet('reporte', 'movimientos')
        ->assertSet('categoria', 'academicos');
});

/*|--------------------------------------------------------------------------
| Filtros, listas blancas y estados
|--------------------------------------------------------------------------*/

test('los filtros recortan el conjunto sin ampliar el alcance', function () {
    $esc = escenarioReportes2017();
    $servicio = app(ReporteService::class);

    expect($servicio->pagina('ingreso', [])->total())->toBe(2)
        ->and($servicio->pagina('ingreso', ['periodo' => $esc['periodo']->id])->total())->toBe(2)
        ->and($servicio->pagina('ingreso', ['programa' => $esc['programa']->id])->total())->toBe(1)
        ->and($servicio->pagina('ingreso', ['programa' => $esc['programaOtro']->id])->total())->toBe(1)
        ->and($servicio->pagina('ingreso', ['estatus' => 'DRAFT'])->total())->toBe(1)
        ->and($servicio->pagina('ingreso', [
            'periodo' => $esc['periodo']->id,
            'programa' => $esc['programa']->id,
        ])->total())->toBe(1);

    // Claves fuera de la lista blanca se ignoran (nunca ensanchan la consulta).
    $normalizados = ReporteService::normalizar('ingreso', [
        'estatus' => 'ENROLLED',
        'alumno_id' => 999,
        'orden' => 'id desc',
    ]);

    expect($normalizados)->not->toHaveKey('alumno_id')
        ->and($normalizados)->not->toHaveKey('orden')
        ->and($normalizados['estatus'])->toBe('ENROLLED')
        // `''` («Todos») se normaliza a null: el filtro declarado sí viaja.
        ->and(ReporteService::normalizar('ingreso', ['programa' => '  '])['programa'])->toBeNull();
});

test('la busqueda solo admite nombre apellidos y numero de control', function () {
    $esc = escenarioReportes2017();
    $servicio = app(ReporteService::class);

    expect($servicio->pagina('ingreso', ['busqueda' => $esc['alumno']->numero_control])->total())->toBe(1)
        ->and($servicio->pagina('ingreso', ['busqueda' => $esc['alumno']->apellidos])->total())->toBe(1)
        // CURP, correo y teléfono NO son campos de búsqueda (PII).
        ->and($servicio->pagina('ingreso', ['busqueda' => $esc['alumno']->curp])->total())->toBe(0)
        ->and($servicio->pagina('ingreso', ['busqueda' => $esc['alumno']->correo])->total())->toBe(0)
        ->and($servicio->pagina('ingreso', ['busqueda' => $esc['alumno']->telefono])->total())->toBe(0);
});

test('un valor fuera de dominio se marca como error y devuelve cero filas', function () {
    $esc = escenarioReportes2017();

    Livewire::actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create())
        ->test(PanelReportes::class)
        ->set('estatus', 'HACK')
        ->assertHasErrors('estatus')
        ->assertSee('Sin resultados');

    // En el servicio, el mismo valor sólo puede recortar (nunca ampliar).
    expect(app(ReporteService::class)->pagina('ingreso', ['estatus' => 'HACK'])->total())->toBe(0)
        ->and($esc['inscripcion1']->exists)->toBeTrue();
});

test('la paginacion entrega 15 filas por pagina y respeta el limite', function () {
    $periodo = Periodo::factory()->create(['clave' => 'REP-PAG', 'nombre' => 'Periodo paginacion']);
    $programa = Programa::factory()->create(['clave' => 'LPAG', 'nombre' => 'Programa paginacion']);

    Inscripcion::factory()->count(20)
        ->enPeriodo($periodo)
        ->create(['programa_id' => $programa->id]);

    $servicio = app(ReporteService::class);
    $pagina1 = $servicio->pagina('ingreso', []);

    expect($pagina1->total())->toBe(20)
        ->and($pagina1->count())->toBe(15)
        ->and($pagina1->lastPage())->toBe(2)
        ->and($servicio->consulta('ingreso', [])->forPage(2, 15)->get()->count())->toBe(5);

    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    Livewire::actingAs($admin)->test(PanelReportes::class)
        ->call('gotoPage', 2)
        ->assertSet('paginators', ['page' => 2])
        ->assertSee('N. de control');

    // Cambiar un filtro vuelve la paginación a la primera página.
    Livewire::actingAs($admin)->test(PanelReportes::class)
        ->call('gotoPage', 2)
        ->set('busqueda', 'NCRP')
        ->assertSet('paginators', ['page' => 1]);
});

test('la pantalla muestra estado vacio cuando no hay resultados', function () {
    $esc = escenarioReportes2017();

    Livewire::actingAs(User::factory()->deRol(RolUsuario::ADMISIONES)->create())
        ->test(PanelReportes::class)
        ->set('busqueda', 'Sin-coincidencia-para-el-reporte')
        ->assertSee('Sin resultados')
        ->assertSee('Ningún registro coincide con los filtros seleccionados.');
});

/*|--------------------------------------------------------------------------
| Resúmenes de conteo (sin promedios inventados)
|--------------------------------------------------------------------------*/

test('rendimiento usa los estatus canonicos y no inventa promedios', function () {
    $esc = escenarioReportes2017();
    $resumen = app(ReporteService::class)->resumen('rendimiento', ['periodo' => $esc['periodo']->id]);
    $chips = chipsReportes2017($resumen);

    expect($chips)->toHaveKeys(['Inscritos', 'Aprobados', 'Reprobados', 'Bajas', 'Extraordinarios'])
        ->and($chips['Inscritos'])->toBe('4')
        ->and($chips['Aprobados'])->toBe('1')
        ->and($chips['Reprobados'])->toBe('1')
        ->and($chips['Bajas'])->toBe('1')
        ->and($chips['Extraordinarios'])->toBe('1');

    // §D-N4: el único promedio aprobado es el por alumno de HistorialAcademicoService.
    expect(implode(' ', array_column($resumen, 'label')))->not->toContain('Promedio')
        ->and(json_encode($resumen))->not->toContain('indice');
});

test('asistencia cuenta sesiones del rango y muestra guion sin registros', function () {
    $esc = escenarioReportes2017();
    $servicio = app(ReporteService::class);

    $chips = chipsReportes2017($servicio->resumen('asistencia', ['grupo' => $esc['grupos'][0]->id]));

    expect($chips['Alumnos'])->toBe('1')
        ->and($chips['Registros'])->toBe('2')
        ->and($chips['Presentes'])->toBe('1')
        ->and($chips['Ausentes'])->toBe('1')
        ->and($chips['% de asistencia'])->toBe('50.0 %');

    // Rango fuera de fechas: la fila sigue existiendo con 0 sesiones («—»).
    $fueraDeRango = chipsReportes2017($servicio->resumen('asistencia', [
        'grupo' => $esc['grupos'][0]->id,
        'desde' => '2030-01-01',
        'hasta' => '2030-12-31',
    ]));

    expect($fueraDeRango['Alumnos'])->toBe('1')
        ->and($fueraDeRango['Registros'])->toBe('0')
        ->and($fueraDeRango['% de asistencia'])->toBe('—');
});

test('el reporte de movimientos nunca mezcla subtipos', function () {
    $esc = escenarioReportes2017();
    $servicio = app(ReporteService::class);

    expect($servicio->pagina('movimientos', ['tipo' => 'bajas'])->total())->toBe(1)
        ->and($servicio->pagina('movimientos', ['tipo' => 'titulacion'])->total())->toBe(0)
        ->and($servicio->pagina('movimientos', ['tipo' => 'servicio-social'])->total())->toBe(0)
        ->and($servicio->pagina('movimientos', ['tipo' => 'practicas'])->total())->toBe(0)
        ->and($servicio->pagina('movimientos', ['tipo' => 'egreso'])->total())->toBe(0)
        ->and($servicio->pagina('movimientos', ['tipo' => 'equivalencias'])->total())->toBe(0);

    $chips = chipsReportes2017($servicio->resumen('movimientos', ['tipo' => 'bajas']));
    expect($chips['Total'])->toBe('1');
});

/*|--------------------------------------------------------------------------
| Finanzas
|--------------------------------------------------------------------------*/

test('el reporte de pagos distingue validado pendiente y rechazado', function () {
    $esc = escenarioReportes2017();
    $servicio = app(ReporteService::class);

    expect($servicio->pagina('pagos', [])->total())->toBe(3);

    $validados = chipsReportes2017($servicio->resumen('pagos', ['estatus' => EstatusPago::VALIDADO->value]));
    $pendientes = chipsReportes2017($servicio->resumen('pagos', ['estatus' => EstatusPago::PENDIENTE->value]));
    $rechazados = chipsReportes2017($servicio->resumen('pagos', ['estatus' => EstatusPago::RECHAZADO->value]));

    expect($validados['Movimientos'])->toBe('1')
        ->and($validados['Monto total'])->toBe('900.00')
        ->and($pendientes['Movimientos'])->toBe('1')
        ->and($pendientes['Monto total'])->toBe('400.00')
        ->and($rechazados['Movimientos'])->toBe('1')
        ->and($rechazados['Monto total'])->toBe('300.00');
});

test('cartera replica exactamente la formula de EstadoCuentaService', function () {
    $esc = escenarioReportes2017();

    $canonico = app(EstadoCuentaService::class)->resumen($esc['alumno']);
    $fila = app(ReporteService::class)
        ->consulta('cartera', [])
        ->get()
        ->first(fn ($alumno) => $alumno->id === $esc['alumno']->id);

    expect($fila)->not->toBeNull()
        ->and(round((float) $fila->cargos, 2))->toBe(round((float) $canonico['total_cargos'], 2))
        ->and(round((float) $fila->pagos, 2))->toBe(round((float) $canonico['total_pagos'], 2))
        ->and(round((float) $fila->ajustes, 2))->toBe(round((float) $canonico['total_ajustes'], 2))
        ->and(round((float) $fila->saldo, 2))->toBe(round((float) $canonico['saldo'], 2));

    // Cargos 1500 − pagos VALIDADO 900 − ajuste beca 200 = 400.
    expect(round((float) $fila->saldo, 2))->toBe(400.0)
        ->and($esc['pagoPendiente']->estatus)->toBe(EstatusPago::PENDIENTE)
        ->and($esc['pagoRechazado']->estatus)->toBe(EstatusPago::RECHAZADO);
});

test('el reporte de becas muestra el importe ya aplicado en el ledger', function () {
    $esc = escenarioReportes2017();
    $chips = chipsReportes2017(app(ReporteService::class)->resumen('becas', []));

    expect($chips['Becas'])->toBe('1')
        ->and($chips['Aplicado en ledger'])->toBe('200.00');
});

/*|--------------------------------------------------------------------------
| Privacidad
|--------------------------------------------------------------------------*/

test('el modulo no expone CURP correo telefono ni domicilio', function () {
    $esc = escenarioReportes2017();
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    foreach (array_keys(CatalogoReportes::CATEGORIAS) as $categoria) {
        $this->actingAs($admin)
            ->get('/reportes?categoria=' . $categoria)
            ->assertOk()
            ->assertDontSee($esc['alumno']->curp)
            ->assertDontSee($esc['alumno']->correo)
            ->assertDontSee($esc['alumno']->telefono)
            ->assertDontSee($esc['alumno']->calle);
    }

    // PII mínima permitida: número de control + nombre.
    $this->actingAs($admin)->get('/reportes?categoria=financieros')
        ->assertOk()
        ->assertSee($esc['alumno']->numero_control);

    // Ni contraseñas ni hashes en ninguna categoría.
    $this->actingAs($admin)->get('/reportes?categoria=ingreso')
        ->assertDontSee('password')
        ->assertDontSee('$2y$');
});

test('el formulario de reportes no depende de datos reales', function () {
    $esc = escenarioReportes2017();

    // Los datos del escenario son sintéticos: CURP ficticia ZZZZ… y correo de prueba.
    expect($esc['alumno']->curp)->toStartWith('ZZZZ')
        ->and($esc['alumno']->correo)->toContain('@example.test')
        ->and($esc['alumno']->nacionalidad)->toBe('Mexicana');
});
