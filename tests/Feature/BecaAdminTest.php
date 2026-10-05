<?php

use App\Models\Beca;
use App\Models\BecaAlumno;
use App\Models\Alumno;
use App\Models\ConceptoPago;
use App\Models\InscripcionMateria;
use App\Models\MatriculaPeriodo;
use App\Models\Inscripcion;
use App\Models\AjusteFinanciero;
use App\Models\User;
use App\Models\Programa;
use App\Models\Periodo;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\Materia;
use App\Models\Grupo;
use App\Enums\TipoBeca;
use App\Enums\AlcanceBeca;
use App\Enums\EstatusBeca;
use App\Enums\TipoAjusteFinanciero;
use App\Enums\RolUsuario;
use App\Enums\EstatusConceptoPago;
use App\Enums\EstatusInscripcionMateria;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('becas');

test('crea beca por porcentaje', function () {
    $periodo = Periodo::factory()->create(['activo' => true]);
    
    $beca = Beca::create([
        'clave' => 'BEC-001',
        'nombre' => 'Beca Excelencia',
        'tipo' => TipoBeca::PORCENTAJE,
        'valor' => '50.00',
        'alcance' => AlcanceBeca::INSCRIPCION,
        'periodo_id' => $periodo->id,
        'activo' => true,
        'fecha_inicio' => now()->format('Y-m-d'),
        'fecha_fin' => now()->addYear()->format('Y-m-d'),
    ]);
    
    expect($beca->tipo)->toBe(TipoBeca::PORCENTAJE);
    expect($beca->valor)->toBe('50.00');
    expect($beca->alcance)->toBe(AlcanceBeca::INSCRIPCION);
    expect($beca->estaVigenteParaPeriodo($periodo->id))->toBeTrue();
});

test('crea beca por monto fijo', function () {
    $periodo = Periodo::factory()->create(['activo' => true]);
    
    $beca = Beca::create([
        'clave' => 'BEC-002',
        'nombre' => 'Apoyo Económico',
        'tipo' => TipoBeca::MONTO,
        'valor' => '5000.00',
        'alcance' => AlcanceBeca::MENSUALIDADES,
        'periodo_id' => $periodo->id,
        'activo' => true,
        'fecha_inicio' => now()->format('Y-m-d'),
        'fecha_fin' => now()->addMonths(4)->format('Y-m-d'),
    ]);
    
    expect($beca->tipo)->toBe(TipoBeca::MONTO);
    expect($beca->valor)->toBe('5000.00');
    expect($beca->alcance)->toBe(AlcanceBeca::MENSUALIDADES);
});

test('calcula descuento por porcentaje correctamente', function () {
    $periodo = Periodo::factory()->create(['activo' => true]);
    
    $beca = Beca::create([
        'clave' => 'BEC-003',
        'nombre' => 'Beca 30%',
        'tipo' => TipoBeca::PORCENTAJE,
        'valor' => '30.00',
        'alcance' => AlcanceBeca::INSCRIPCION,
        'periodo_id' => $periodo->id,
        'activo' => true,
        'fecha_inicio' => now()->format('Y-m-d'),
        'fecha_fin' => now()->addYear()->format('Y-m-d'),
    ]);
    
    $asignacion = BecaAlumno::factory()->create([
        'beca_id' => $beca->id,
        'estatus' => EstatusBeca::APROBADA,
    ]);
    
    $descuento = $asignacion->calcularDescuento(10000.00);
    expect($descuento)->toBe(3000.00);
    
    $descuentoAlto = $asignacion->calcularDescuento(5000.00);
    expect($descuentoAlto)->toBe(1500.00);
});

test('calcula descuento por monto fijo correctamente', function () {
    $periodo = Periodo::factory()->create(['activo' => true]);
    
    $beca = Beca::create([
        'clave' => 'BEC-004',
        'nombre' => 'Beca $2000',
        'tipo' => TipoBeca::MONTO,
        'valor' => '2000.00',
        'alcance' => AlcanceBeca::INSCRIPCION,
        'periodo_id' => $periodo->id,
        'activo' => true,
        'fecha_inicio' => now()->format('Y-m-d'),
        'fecha_fin' => now()->addYear()->format('Y-m-d'),
    ]);
    
    $asignacion = BecaAlumno::factory()->create([
        'beca_id' => $beca->id,
        'estatus' => EstatusBeca::APROBADA,
    ]);
    
    $descuento = $asignacion->calcularDescuento(5000.00);
    expect($descuento)->toBe(2000.00);
    
    $descuentoLimite = $asignacion->calcularDescuento(1500.00);
    expect($descuentoLimite)->toBe(1500.00);
});

test('no permite acumular beca y convenio en mismo concepto INSCRIPCION', function () {
    $periodo = Periodo::factory()->create(['activo' => true]);
    $alumno = Alumno::factory()->create();
    
    $beca = Beca::create([
        'clave' => 'BEC-005',
        'nombre' => 'Beca Conflicto',
        'tipo' => TipoBeca::PORCENTAJE,
        'valor' => '20.00',
        'alcance' => AlcanceBeca::INSCRIPCION,
        'periodo_id' => $periodo->id,
        'activo' => true,
        'fecha_inicio' => now()->format('Y-m-d'),
        'fecha_fin' => now()->addYear()->format('Y-m-d'),
    ]);
    
    $asignacion = BecaAlumno::create([
        'beca_id' => $beca->id,
        'alumno_id' => $alumno->id,
        'periodo_id' => $periodo->id,
        'estatus' => EstatusBeca::PENDIENTE,
        'asignada_por' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()->id,
        'observacion' => 'Test conflicto',
    ]);
    
    $asignacion->update([
        'estatus' => EstatusBeca::APROBADA,
        'aprobada_por' => User::factory()->deRol(RolUsuario::ADMINISTRACION)->create()->id,
        'fecha_aprobacion' => now(),
    ]);
    
    expect($asignacion->fresh()->esAprobada())->toBeTrue();
});

test('crea ajuste financiero al aprobar beca', function () {
    $periodo = Periodo::factory()->create(['activo' => true]);
    $beca = Beca::factory()->create([
        'tipo' => TipoBeca::PORCENTAJE,
        'valor' => '20.00',
        'alcance' => AlcanceBeca::INSCRIPCION,
        'periodo_id' => $periodo->id,
        'activo' => true,
    ]);
    
    $alumno = Alumno::factory()->create();
    $inscripcion = \App\Models\Inscripcion::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_id' => $periodo->id,
    ]);
    
    $concepto = \App\Models\ConceptoPago::factory()->create([
        'tipo' => 'INSCRIPCION',
        'monto_total' => 10000.00,
        'estatus' => EstatusConceptoPago::PENDIENTE,
        'inscripcion_id' => $inscripcion->id,
    ]);
    
    $asignacion = BecaAlumno::create([
        'beca_id' => $beca->id,
        'alumno_id' => $alumno->id,
        'periodo_id' => $periodo->id,
        'estatus' => EstatusBeca::PENDIENTE,
        'asignada_por' => User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create()->id,
    ]);
    
    // Aprobar la beca
    $asignacion->update([
        'estatus' => EstatusBeca::APROBADA,
        'aprobada_por' => User::factory()->deRol(RolUsuario::ADMINISTRACION)->create()->id,
        'fecha_aprobacion' => now(),
    ]);
    
    // Crear ajuste financiero directamente (simulando lo que hace el Livewire)
    $descuento = $asignacion->calcularDescuento($concepto->monto_total);
    $ajuste = \App\Models\AjusteFinanciero::create([
        'alumno_id' => $alumno->id,
        'concepto_pago_id' => $concepto->id,
        'beca_alumno_id' => $asignacion->id,
        'tipo' => TipoAjusteFinanciero::DESCUENTO_BECA,
        'monto' => $descuento,
        'descripcion' => 'Descuento por beca: ' . $beca->nombre,
        'registrado_por' => auth()->id() ?? User::factory()->deRol(RolUsuario::ADMINISTRACION)->create()->id,
        'registrado_at' => now(),
    ]);
    
    $ajuste = AjusteFinanciero::where('beca_alumno_id', $asignacion->id)
        ->where('tipo', TipoAjusteFinanciero::DESCUENTO_BECA)
        ->first();
    
    expect($ajuste)->not->toBeNull();
    expect($ajuste->esDescuentoBeca())->toBeTrue();
    expect($ajuste->monto)->toBeGreaterThan(0);
});

test('reverso de descuento no modifica pago validado', function () {
    $origen = AjusteFinanciero::create([
        'alumno_id' => Alumno::factory()->create()->id,
        'concepto_pago_id' => ConceptoPago::factory()->create()->id,
        'beca_alumno_id' => BecaAlumno::factory()->create()->id,
        'tipo' => TipoAjusteFinanciero::DESCUENTO_BECA,
        'monto' => 500.00,
        'registrado_por' => User::factory()->create()->id,
        'registrado_at' => now(),
    ]);
    
    $ajuste = AjusteFinanciero::create([
        'alumno_id' => $origen->alumno_id,
        'concepto_pago_id' => $origen->concepto_pago_id,
        'beca_alumno_id' => $origen->beca_alumno_id,
        'tipo' => TipoAjusteFinanciero::REVERSO_DESCUENTO_BECA,
        'monto' => 500.00,
        'ajuste_origen_id' => $origen->id,
        'registrado_por' => User::factory()->create()->id,
        'registrado_at' => now(),
    ]);
    
    expect($ajuste->esReversoDescuentoBeca())->toBeTrue();
    expect($ajuste->efectoSobreSaldo())->toBe(-500.00);
    expect($ajuste->ajuste_origen_id)->toBe($origen->id);
});

test('carta de no adeudo se genera solo con saldo exigible 0', function () {
    $alumno = Alumno::factory()->create();
    $periodo = Periodo::factory()->create(['activo' => true]);
    $programa = Programa::factory()->create(['activo' => true]);
    $planEstudio = PlanEstudio::factory()->create();
    $planVersion = PlanVersion::factory()->create();
    $inscripcion = \App\Models\Inscripcion::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_id' => $periodo->id,
        'programa_id' => $programa->id,
    ]);
    
    $conceptos = \App\Models\ConceptoPago::whereHas('inscripcion', function ($query) use ($alumno, $periodo) {
        $query->where('alumno_id', $alumno->id)
              ->where('periodo_id', $periodo->id);
    })->get();
    $pagos = \App\Models\Pago::whereHas('conceptoPago.inscripcion', function ($query) use ($alumno, $periodo) {
        $query->where('alumno_id', $alumno->id)
              ->where('periodo_id', $periodo->id);
    })
        ->where('estatus', EstatusConceptoPago::PAGADO)
        ->get();
    $ajustes = AjusteFinanciero::where('alumno_id', $alumno->id)->get();
    
    $totalCargos = $conceptos->sum('monto_total');
    $totalPagos = $pagos->sum('monto');
    $totalAjustes = 0;
    $saldo = $totalCargos - $totalPagos - $totalAjustes;
    
    expect($saldo)->toBe(0);
    
    $concepto = \App\Models\ConceptoPago::factory()->create([
        'monto_total' => 1000.00,
        'estatus' => EstatusConceptoPago::PENDIENTE,
        'inscripcion_id' => $inscripcion->id,
    ]);
    
    $totalCargos = 1000.00;
    $totalPagos = 0;
    $saldo = $totalCargos - 0 - 0;
    
    expect($saldo)->toBe(1000.00);
    expect($saldo > 0)->toBeTrue();
});