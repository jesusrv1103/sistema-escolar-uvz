<?php

namespace App\Services;

use App\Models\AjusteFinanciero;
use App\Models\Alumno;
use App\Models\ConceptoPago;
use App\Models\Pago;
use Illuminate\Support\Collection;

/**
 * Estado de cuenta del alumno.
 *
 * Fórmula de la Fase 2.0-12, extraída a servicio en 2.0-16 para que la vista
 * administrativa (`beca-estado-cuenta`) y el portal del alumno
 * (`/alumno/estado-cuenta`) muestren EXACTAMENTE lo mismo: una sola fuente y
 * ninguna regla nueva (sin cargos, intereses ni reglas de cobro inventadas):
 *
 *     saldo = total cargos − pagos VALIDADOS − ajustes/descuentos
 *
 * `BecaAdminTest` sigue siendo la red de la vista administrativa.
 */
class EstadoCuentaService
{
    /**
     * Extracción completa del estado de cuenta de un alumno.
     *
     * @return array{
     *     conceptos: Collection<int, ConceptoPago>,
     *     pagos: Collection<int, Pago>,
     *     ajustes: Collection<int, AjusteFinanciero>,
     *     filas: Collection<int, array{concepto: ConceptoPago, pagado: float, ajustes: float, saldo: float}>,
     *     total_cargos: float,
     *     total_pagos: float,
     *     total_ajustes: float,
     *     saldo: float,
     * }
     */
    public function resumen(Alumno $alumno): array
    {
        $conceptos = ConceptoPago::query()
            ->whereHas('inscripcion', fn ($q) => $q->where('alumno_id', $alumno->id))
            ->with('inscripcion.alumno')
            ->orderBy('fecha_vencimiento')
            ->get();

        $pagos = Pago::query()
            ->whereHas('conceptoPago.inscripcion', fn ($q) => $q->where('alumno_id', $alumno->id))
            ->with('conceptoPago.inscripcion')
            ->orderByDesc('fecha_pago')
            ->get();

        $ajustes = AjusteFinanciero::where('alumno_id', $alumno->id)
            ->with('conceptoPago', 'becaAlumno.beca')
            ->orderByDesc('registrado_at')
            ->get();

        $totalCargos = $conceptos->sum('monto_total');
        $totalPagos = $pagos->where('estatus', 'VALIDADO')->sum('monto');
        // `efectoSobreSaldo` es un método, no una columna: como clave de
        // `sum()` devolvía null y el total de ajustes salía siempre 0.
        $totalAjustes = $ajustes->sum(fn ($ajuste) => $ajuste->efectoSobreSaldo());
        $saldo = $totalCargos - $totalPagos - $totalAjustes;

        // Detalle por concepto: lo que ya se pagó y lo que queda por concepto.
        $filas = $conceptos->map(function (ConceptoPago $concepto) use ($pagos, $ajustes) {
            $pagado = $pagos->where('concepto_pago_id', $concepto->id)
                ->where('estatus', 'VALIDADO')
                ->sum('monto');
            $ajusteConcepto = $ajustes->where('concepto_pago_id', $concepto->id)
                ->sum(fn ($ajuste) => $ajuste->efectoSobreSaldo());

            return [
                'concepto' => $concepto,
                'pagado' => $pagado,
                'ajustes' => $ajusteConcepto,
                'saldo' => $concepto->monto_total - $pagado - $ajusteConcepto,
            ];
        })->values();

        return [
            'conceptos' => $conceptos,
            'pagos' => $pagos,
            'ajustes' => $ajustes,
            'filas' => $filas,
            'total_cargos' => $totalCargos,
            'total_pagos' => $totalPagos,
            'total_ajustes' => $totalAjustes,
            'saldo' => $saldo,
        ];
    }
}
