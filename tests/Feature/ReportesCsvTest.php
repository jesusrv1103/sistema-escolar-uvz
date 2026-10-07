<?php

use App\Enums\EstatusPago;
use App\Enums\InscripcionEstatus;
use App\Enums\RolUsuario;
use App\Models\Alumno;
use App\Models\ConceptoPago;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Periodo;
use App\Models\Programa;
use App\Models\User;
use App\Services\Reportes\CatalogoReportes;
use App\Services\Reportes\CsvReporteService;

/*
|--------------------------------------------------------------------------
| Fase 2.0-17 — Exportación CSV de los reportes operativos
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
| Cubren: la MISMA autorización que la pantalla (403 en CSV directo), el
| formato aprobado (UTF-8 con BOM, coma, comilla, `fputcsv`), cabeceras y
| etiquetas en español, acentos, todas las filas, la defensa anti CSV
| injection (y el pase de números planos), los filtros y el 422 fuera de
| dominio. Sin mocks y sin archivos temporales en disco.
*/

/*|--------------------------------------------------------------------------
| Escenario sintético propio (sufijo de fase: no colisiona con otros tests)
|--------------------------------------------------------------------------*/

/**
 * @return array<string, mixed>
 */
function escenarioCsvReportes2017(): array
{
    $periodo = Periodo::factory()->create(['clave' => 'CSV-2026', 'nombre' => 'Periodo csv 2026']);
    $programa = Programa::factory()->create(['clave' => 'LCVS', 'nombre' => 'Licenciatura csv']);

    // Acentos: el CSV es UTF-8 con BOM, los acentos deben llegar intactos.
    $acento = Alumno::factory()->create([
        'numero_control' => 'CSV00001',
        'nombre' => 'José',
        'apellidos' => 'Muñóz Núñez',
        'curp' => 'ZZZZ990101HNEAAA01',
        'correo' => 'acento.csv@example.test',
        'telefono' => '5511112222',
        'calle' => 'Calle Csv Acentuada 1',
    ]);

    // Inyección: un valor de texto que Excel/LibreOffice interpretarían como
    // fórmula si no se neutraliza con el apóstrofo inicial.
    $inyeccion = Alumno::factory()->create([
        'numero_control' => 'CSV00002',
        'nombre' => '=SUM(A1:A2)',
        'apellidos' => '@cmd|calc &A',
    ]);

    $inscripcionAcento = Inscripcion::factory()
        ->enEstatus(InscripcionEstatus::ENROLLED)
        ->enPeriodo($periodo)
        ->create(['programa_id' => $programa->id, 'alumno_id' => $acento->id]);

    $inscripcionInyeccion = Inscripcion::factory()
        ->enEstatus(InscripcionEstatus::DRAFT)
        ->enPeriodo($periodo)
        ->create(['programa_id' => $programa->id, 'alumno_id' => $inyeccion->id]);

    // Cartera con saldo NEGATIVO: el signo menos es un número plano, no una
    // fórmula, así que viaja sin apóstrofo.
    $concepto = ConceptoPago::factory()->inscripcion()->create([
        'inscripcion_id' => $inscripcionAcento->id,
        'concepto' => 'Inscripcion csv sintetica',
        'monto' => 100,
        'descuento' => 0,
        'monto_total' => 100,
        'fecha_vencimiento' => '2026-09-15',
    ]);
    $pago = Pago::factory()->create([
        'concepto_pago_id' => $concepto->id,
        'monto' => 500,
        'estatus' => EstatusPago::VALIDADO->value,
        'folio' => 'FOL-CSV-0001',
    ]);

    return compact(
        'periodo', 'programa', 'acento', 'inyeccion', 'inscripcionAcento',
        'inscripcionInyeccion', 'concepto', 'pago',
    );
}

/** @return list<string> */
function lineasCsvReportes2017(string $contenido): array
{
    expect(str_starts_with($contenido, "\xEF\xBB\xBF"))->toBeTrue('El CSV debe llevar UTF-8 BOM');

    return explode("\n", rtrim(substr($contenido, 3), "\n"));
}

/** @return list<list<string>> */
function filasCsvReportes2017(string $contenido): array
{
    return array_map(
        fn (string $linea) => str_getcsv($linea, ',', '"', '\\'),
        array_slice(lineasCsvReportes2017($contenido), 1),
    );
}

/*|--------------------------------------------------------------------------
| Autorización
|--------------------------------------------------------------------------*/

test('el CSV directo exige sesion y las mismas capacidades de la pantalla', function () {
    $this->get('/reportes/csv/ingreso')->assertRedirect(route('login'));

    foreach ([RolUsuario::DOCENTE, RolUsuario::ALUMNO] as $rol) {
        $this->actingAs(User::factory()->deRol($rol)->create())
            ->get('/reportes/csv/ingreso')
            ->assertForbidden();
    }

    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $this->actingAs($admisiones)->get('/reportes/csv/ingreso')->assertOk();
    $this->actingAs($admisiones)->get('/reportes/csv/rendimiento')->assertForbidden();
    $this->actingAs($admisiones)->get('/reportes/csv/cartera')->assertForbidden();

    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $this->actingAs($administracion)->get('/reportes/csv/cartera')->assertOk();
    $this->actingAs($administracion)->get('/reportes/csv/ingreso')->assertForbidden();
    $this->actingAs($administracion)->get('/reportes/csv/movimientos')->assertForbidden();

    $control = User::factory()->deRol(RolUsuario::CONTROL_ESCOLAR)->create();
    $this->actingAs($control)->get('/reportes/csv/rendimiento')->assertOk();
    $this->actingAs($control)->get('/reportes/csv/asistencia')->assertOk();
    $this->actingAs($control)->get('/reportes/csv/ingreso')->assertForbidden();

    // ADMIN pasa por Gate::before y puede exportar cualquier reporte…
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $this->actingAs($admin)->get('/reportes/csv/pagos')->assertOk();

    // …pero un slug inexistente sigue siendo 404.
    $this->actingAs($admin)->get('/reportes/csv/inexistente')->assertNotFound();
});

/*|--------------------------------------------------------------------------
| Formato
|--------------------------------------------------------------------------*/

test('el CSV entrega BOM cabeceras en español y todas las filas', function () {
    $esc = escenarioCsvReportes2017();
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $respuesta = $this->actingAs($admin)->get('/reportes/csv/ingreso')->assertOk();

    expect($respuesta->headers->get('content-type'))->toBe('text/csv; charset=UTF-8')
        ->and($respuesta->headers->get('content-disposition'))
        ->toContain('attachment;')
        ->and($respuesta->headers->get('content-disposition'))
        ->toContain('reporte-ingreso-' . now()->format('Y-m-d') . '.csv');

    $contenido = $respuesta->streamedContent();
    $lineas = lineasCsvReportes2017($contenido);

    // Cabecera: mismas etiquetas que la tabla (nada de claves técnicas).
    expect(str_getcsv($lineas[0], ',', '"', '\\'))->toBe([
        'N. de control', 'Alumno', 'Programa', 'Periodo', 'Estatus', 'Alta', 'Finalizada',
    ]);

    // Todas las filas (el CSV no depende de la paginación de pantalla).
    expect($lineas)->toHaveCount(3)
        ->and($esc['inscripcionAcento']->exists)->toBeTrue();

    // Acentos intactos y etiquetas de estado en español (§18), no `value`.
    expect($contenido)->toContain('José')
        ->and($contenido)->toContain('Muñóz Núñez')
        ->and($contenido)->toContain(InscripcionEstatus::ENROLLED->label())
        ->and($contenido)->not->toContain('ENROLLED')
        ->and($contenido)->not->toContain('N. de control,' . 'ENROLLED');

    // Nunca exporta PII fuera de la lista aprobada.
    expect($contenido)->not->toContain($esc['acento']->curp)
        ->and($contenido)->not->toContain($esc['acento']->correo)
        ->and($contenido)->not->toContain($esc['acento']->telefono)
        ->and($contenido)->not->toContain($esc['acento']->calle);
});

test('cada fila del CSV tiene tantas columnas como la cabecera', function () {
    escenarioCsvReportes2017();
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $contenido = $this->actingAs($admin)->get('/reportes/csv/cartera')->streamedContent();
    $lineas = lineasCsvReportes2017($contenido);
    $cabecera = str_getcsv($lineas[0], ',', '"', '\\');

    expect($cabecera)->toBe(['N. de control', 'Alumno', 'Cargos', 'Pagos validados', 'Ajustes', 'Saldo exigible']);

    foreach (array_slice($lineas, 1) as $indice => $linea) {
        expect(str_getcsv($linea, ',', '"', '\\'))
            ->toHaveCount(count($cabecera), "La fila {$indice} no tiene las mismas columnas");
    }

    // Round-trip: los datos se leen igual con `str_getcsv` (RFC 4180).
    $fila = collect(filasCsvReportes2017($contenido))
        ->first(fn ($celdas) => $celdas[0] === 'CSV00001');

    expect($fila)->not->toBeNull()
        ->and($fila[1])->toBe('José Muñóz Núñez')
        // 100 de cargo − 500 validados = −400 (número plano, sin apóstrofo).
        ->and($fila[5])->toBe('-400.00');
});

test('el CSV neutraliza formulas pero conserva los numeros planos', function () {
    escenarioCsvReportes2017();
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $contenido = $this->actingAs($admin)->get('/reportes/csv/ingreso')->streamedContent();
    $fila = collect(filasCsvReportes2017($contenido))
        ->first(fn ($celdas) => $celdas[0] === 'CSV00002');

    expect($fila)->not->toBeNull()
        // Una sola neutralización al inicio de la celda, sin perder el texto.
        ->and($fila[1])->toBe("'=SUM(A1:A2) @cmd|calc &A");

    // Neutralización unitaria: números y valores neutros no cambian.
    expect(CsvReporteService::neutralizar('-123.45'))->toBe('-123.45')
        ->and(CsvReporteService::neutralizar('+7'))->toBe('+7')
        ->and(CsvReporteService::neutralizar('=1+2'))->toBe("'=1+2")
        ->and(CsvReporteService::neutralizar('@SUM(A1)'))->toBe("'@SUM(A1)")
        ->and(CsvReporteService::neutralizar("\tTAB"))->toBe("'\tTAB")
        ->and(CsvReporteService::neutralizar('Alumno normal'))->toBe('Alumno normal')
        ->and(CsvReporteService::neutralizar(''))->toBe('');
});

/*|--------------------------------------------------------------------------
| Filtros
|--------------------------------------------------------------------------*/

test('el CSV respeta los mismos filtros que la pantalla', function () {
    escenarioCsvReportes2017();
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $sinFiltro = $this->actingAs($admin)->get('/reportes/csv/ingreso')->streamedContent();
    expect(filasCsvReportes2017($sinFiltro))->toHaveCount(2);

    $porEstatus = $this->actingAs($admin)->get('/reportes/csv/ingreso?estatus=DRAFT')->streamedContent();
    $filas = filasCsvReportes2017($porEstatus);

    expect($filas)->toHaveCount(1)
        ->and($filas[0][0])->toBe('CSV00002');

    $porBusqueda = $this->actingAs($admin)->get('/reportes/csv/ingreso?busqueda=CSV00001')->streamedContent();
    $filas = filasCsvReportes2017($porBusqueda);

    expect($filas)->toHaveCount(1)
        ->and($filas[0][0])->toBe('CSV00001');

    // Claves fuera de la lista blanca se ignoran (no ensanchan ni rompen).
    $this->actingAs($admin)
        ->get('/reportes/csv/ingreso?orden=id+desc&alumno_id=999')
        ->assertOk();
});

test('filtros fuera de dominio devuelven 422 en el CSV', function () {
    escenarioCsvReportes2017();
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();

    $fueraDeDominio = [
        '/reportes/csv/ingreso?estatus=HACK',
        '/reportes/csv/ingreso?periodo=abc',
        '/reportes/csv/ingreso?periodo=0',
        '/reportes/csv/movimientos?tipo=otra-cosa',
        '/reportes/csv/pagos?metodo=EFECTIVO_HACK',
        '/reportes/csv/pagos?desde=2026-13-45',
        '/reportes/csv/pagos?desde=2026-05-01&hasta=2026-01-01',
        '/reportes/csv/ingreso?busqueda=' . str_repeat('x', 101),
    ];

    foreach ($fueraDeDominio as $ruta) {
        $this->actingAs($admin)->get($ruta)->assertStatus(422);
    }

    // El subtipo válido sigue funcionando (los 6 subreportes de R5).
    foreach (array_keys(CatalogoReportes::TIPOS_MOVIMIENTO) as $tipo) {
        $this->actingAs($admin)->get('/reportes/csv/movimientos?tipo=' . $tipo)->assertOk();
    }
});
