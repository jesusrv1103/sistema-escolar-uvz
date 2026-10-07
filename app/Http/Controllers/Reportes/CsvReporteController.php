<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Services\Reportes\CatalogoReportes;
use App\Services\Reportes\CsvReporteService;
use App\Services\Reportes\ReporteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga CSV de un reporte operativo (2.0-17).
 *
 * MISMA autorización y MISMOS filtros que la pantalla `/reportes`:
 *
 *  1. capacidad de la categoría (`reporte_ingreso.ver` /
 *     `reporte_academico.ver` / `reporte_financiero.ver`) — ADMIN pasa por
 *     `Gate::before`;
 *  2. `reporte.exportar`;
 *  3. validación de los filtros (claves fuera de la lista blanca se ignoran,
 *     valores fuera de dominio → 422; ningún filtro declarado se omite).
 *
 * El CSV se emite en streaming: sin archivos temporales ni en disco.
 * Un usuario sin capacidad que llame a esta URL recibe 403, igual que en la
 * ruta de pantalla y en las acciones del componente Livewire.
 */
class CsvReporteController extends Controller
{
    public function __invoke(Request $request, string $reporte, CsvReporteService $csv): StreamedResponse
    {
        // 404 para slugs inexistentes (antes de cualquier otro chequeo).
        $capacidad = CatalogoReportes::capacidad($reporte);

        // 1) Autorización de la categoría + 2) autorización de exportación.
        Gate::authorize($capacidad);
        Gate::authorize('reporte.exportar');

        // 3) Filtros: misma validación que en pantalla.
        $entrada = $request->query();
        $validador = Validator::make($entrada, CatalogoReportes::reglas($reporte, $entrada));

        if ($validador->fails()) {
            abort(422, 'Filtros inválidos para el reporte.');
        }

        $filtros = ReporteService::normalizar($reporte, $entrada);

        return response()->streamDownload(
            fn () => $csv->escribir($reporte, $filtros, fopen('php://output', 'w')),
            $csv->nombreArchivo($reporte, $filtros),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
