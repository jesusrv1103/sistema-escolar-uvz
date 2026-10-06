<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use App\Models\DocumentoGenerado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controlador para descargar documentos generados (formatos académicos).
 *
 * El PDF vive en el disco `private` y su ruta se resuelve en el servidor a
 * partir del registro de trazabilidad (`snapshot_datos['archivo']`): nunca
 * llega desde el cliente, por lo que no hay path traversal posible.
 * Si la ruta no está registrada o el archivo ya no existe responde 404
 * (nunca un error 500 por un atributo inexistente).
 */
class DescargaDocumentoGeneradoController extends Controller
{
    /**
     * Descarga el PDF de un documento generado.
     *
     * Seguridad (riesgo R5): `auth` + policy `DocumentoGenerado::view`.
     *
     * @return BinaryFileResponse|\Illuminate\Http\Response
     */
    public function __invoke(Request $request, DocumentoGenerado $documento)
    {
        Gate::authorize('view', $documento);

        $ruta = $documento->rutaArchivo();

        if (
            $ruta === null
            || !str_starts_with($ruta, 'documentos_generados/')
            || !Storage::disk('private')->exists($ruta)
        ) {
            abort(404, 'El archivo PDF de este documento no está disponible.');
        }

        return Storage::disk('private')->download($ruta, basename($ruta));
    }
}
