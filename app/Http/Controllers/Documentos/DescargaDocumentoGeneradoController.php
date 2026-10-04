<?php

namespace App\Http\Controllers\Documentos;

use App\Models\DocumentoGenerado;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Controlador para descargar documentos generados (formatos académicos).
 */
class DescargaDocumentoGeneradoController extends Controller
{
    public function __invoke(Request $request, DocumentoGenerado $documento): \Illuminate\Http\Response
    {
        // Autorización server-side: el model binding ya validó existencia
        Gate::authorize('view', $documento);

        if (!Storage::disk('private')->exists($documento->path)) {
            abort(404, 'El archivo no existe en el almacenamiento.');
        }

        $nombreDescarga = basename($documento->path);

        return Storage::disk('private')->download($documento->path, $nombreDescarga, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nombreDescarga . '"',
        ]);
    }
}