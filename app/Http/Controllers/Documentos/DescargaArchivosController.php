<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Descarga del archivo de un documento del expediente (Fase 10, riesgo R5).
 *
 * Seguridad: `auth` (middleware) + policy `DocumentoPolicy::view` (los 4
 * roles, matriz §8.2); la ruta se resuelve SIEMPRE desde BD (`documentos.ruta`,
 * generada por el sistema), se verifica que resuelva dentro del disco PRIVADO
 * (defensa contra path traversal) y jamás se acepta una ruta desde query,
 * string o parámetro de usuario. NO toca las rutas de pagos de las fases 8/9.
 */
class DescargaArchivosController extends Controller
{
    public function __invoke(Documento $documento): BinaryFileResponse
    {
        Gate::authorize('view', $documento);

        $disco = Storage::disk('private');
        $raiz = realpath($disco->path(''));
        $archivo = realpath($disco->path($documento->ruta));

        if ($raiz === false
            || $archivo === false
            || ! is_file($archivo)
            || ! str_starts_with($archivo, $raiz.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        return response()->download($archivo, $this->nombreSeguro($documento->nombre_original));
    }

    /** Nombre de descarga basado en el nombre original, sanitizado (sin path ni controles). */
    private function nombreSeguro(string $nombre): string
    {
        $nombre = str_replace(['\\', '/'], '_', basename($nombre));
        $nombre = preg_replace('/[\x00-\x1F\x7F"]/', '', $nombre) ?? '';
        $nombre = trim($nombre);

        return $nombre !== '' ? $nombre : 'documento';
    }
}
