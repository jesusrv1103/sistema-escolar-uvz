<?php

namespace App\Http\Controllers\Pagos;

use App\Exceptions\PagoInvalidoException;
use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Services\ComprobantePagoService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descarga del comprobante institucional PDF (Fase 9, RB-19).
 *
 * Distinto del controlador de la Fase 8 (`ComprobantePagoController`), que
 * sirve el archivo de EVIDENCIA cargado por el usuario en
 * `GET /pagos/{pago}/comprobante`: aquí el PDF se genera BAJO DEMANDA por
 * `ComprobantePagoService`, nunca se guarda en disco ni en BD.
 *
 * Seguridad: `auth` (middleware) + policy `descargarComprobantePdf`
 * (solo los 4 roles con lectura del pago, matriz §8.2); sin URLs firmadas
 * públicas. Solo pagos `VALIDADO`; los demás ⇒ 404 (no existen comprobante).
 */
class ComprobantePdfController extends Controller
{
    public function __invoke(Pago $pago, ComprobantePagoService $comprobante): Response
    {
        Gate::authorize('descargarComprobantePdf', $pago);

        try {
            $pdf = $comprobante->generar($pago);
        } catch (PagoInvalidoException) {
            // PENDIENTE, RECHAZADO o integridad incompleta: sin comprobante.
            abort(404);
        }

        return $pdf->download($this->nombreArchivo($pago));
    }

    /**
     * Nombre determinista a partir del folio ya validado (`PAG-YYYYMMDD-########`),
     * sanitizado a caracteres seguros; nunca usa el nombre del alumno.
     */
    private function nombreArchivo(Pago $pago): string
    {
        $folio = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $pago->folio);

        return sprintf('comprobante-%s.pdf', $folio !== '' ? $folio : 'pago-'.$pago->getKey());
    }
}
