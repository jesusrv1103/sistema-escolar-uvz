<?php

namespace App\Http\Controllers\Pagos;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Descarga del comprobante CARGADO (evidencia privada, Fase 8).
 *
 * NO es el comprobante PDF institucional (Fase 9): aquí únicamente se sirve
 * el archivo que el usuario subió como evidencia del pago.
 *
 * Seguridad (riesgo R5, RB-18): `auth` + policy `descargarComprobante`; la
 * ruta se resuelve SIEMPRE desde BD (`pagos.comprobante_ruta`, generada por
 * el sistema), se verifica que resuelva dentro del disco privado (defensa
 * contra path traversal) y JAMÁS se acepta una ruta arbitraria desde query,
 * string o parámetro de usuario.
 */
class ComprobantePagoController extends Controller
{
    public function show(Pago $pago): BinaryFileResponse
    {
        Gate::authorize('descargarComprobante', $pago);

        if ($pago->comprobante_ruta === null) {
            abort(404);
        }

        $disco = Storage::disk('private');
        $raiz = realpath($disco->path(''));
        $archivo = realpath($disco->path($pago->comprobante_ruta));

        if ($raiz === false
            || $archivo === false
            || ! is_file($archivo)
            || ! str_starts_with($archivo, $raiz.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        return response()->download(
            $archivo,
            $pago->comprobante_nombre_original !== null && $pago->comprobante_nombre_original !== ''
                ? $pago->comprobante_nombre_original
                : 'comprobante',
        );
    }
}
