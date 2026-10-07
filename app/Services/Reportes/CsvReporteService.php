<?php

namespace App\Services\Reportes;

use Carbon\CarbonInterface;

/**
 * Exportación CSV de los reportes operativos (2.0-17, D-N6 RESUELTA).
 *
 * Reglas de la fase:
 * - **CSV aprobado**, XLSX fuera de alcance: sin Laravel Excel,
 *   PhpSpreadsheet ni ningún paquete nuevo (dependencias = 0).
 * - UTF-8 (con BOM, para que Excel lea los acentos), separador coma y
 *   enclosure comilla doble (RFC 4180). Escritura SIEMPRE con `fputcsv`:
 *   jamás se concatenan strings para armar el archivo.
 * - Streaming con `streamDownload` + `lazy()`: sin archivos permanentes
 *   y sin cargar todas las filas en memoria.
 * - Exporta EXACTAMENTE las columnas del reporte (los mismos filtros y la
 *   misma autorización que la pantalla): nunca campos ocultos o sensibles.
 * - Defensa anti CSV injection: celda que empieza con `=`, `+`, `-`, `@`,
 *   TAB o CR se neutraliza con apóstrofo inicial (Excel/LibreOffice), salvo
 *   cuando la celda es un número plano (que no es ejecutable).
 */
class CsvReporteService
{
    /** UTF-8 BOM: mejora la lectura de acentos en Excel. */
    public const BOM = "\xEF\xBB\xBF";

    /** Decisión única de formato: coma, comilla doble, escape barra. */
    public const SEPARADOR = ',';
    public const ENCLOSURE = '"';
    public const ESCAPE = '\\';

    public function __construct(private readonly ReporteService $reportes)
    {
    }

    /**
     * Nombre del archivo: `reporte-<slug>[-<subtipo>]-<AAAA-MM-DD>.csv`.
     * Sin datos personales ni sensibles en el filename.
     */
    public function nombreArchivo(string $slug, array $filtros, ?CarbonInterface $fecha = null): string
    {
        $normalizados = ReporteService::normalizar($slug, $filtros);
        $partes = ['reporte', $slug];

        if ($slug === 'movimientos') {
            $partes[] = $normalizados['tipo'];
        }

        $partes[] = ($fecha ?? now())->format('Y-m-d');

        return implode('-', $partes) . '.csv';
    }

    /** Cabeceras en español (mismas etiquetas que la tabla en pantalla). */
    public function encabezados(array $columnas): array
    {
        return array_map(fn (array $columna) => $columna['label'], $columnas);
    }

    /**
     * Una fila de datos: valor crudo → formato plano → anti CSV injection.
     *
     * @return list<string>
     */
    public function fila(array $columnas, mixed $registro): array
    {
        $valores = [];

        foreach ($columnas as $columna) {
            $valor = CatalogoReportes::paraCsv($columna, $columna['get']($registro));
            $valores[] = self::neutralizar($valor);
        }

        return $valores;
    }

    /**
     * Neutraliza fórmulas inyectadas por un valor de texto (CSV injection).
     *
     * Un número plano (`-123.45`, `+7`) NO es ejecutable: se conserva tal
     * cual para que Excel siga recibiendo un valor numérico.
     */
    public static function neutralizar(string $valor): string
    {
        if ($valor === '') {
            return $valor;
        }

        if (! in_array($valor[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return $valor;
        }

        if (preg_match('/^[+\-]?\d+(?:\.\d+)?$/', $valor) === 1) {
            return $valor;
        }

        return "'" . $valor;
    }

    /**
     * Escribe el CSV completo al stream actual (BOM + cabecera + filas).
     *
     * Se consume por bloques (`lazy`) y NO crea archivos temporales.
     *
     * @param  resource  $stream
     */
    public function escribir(string $slug, array $filtros, $stream): void
    {
        $normalizados = ReporteService::normalizar($slug, $filtros);
        $columnas = CatalogoReportes::columnas($slug, $normalizados);

        fwrite($stream, self::BOM);
        $this->csv($stream, $this->encabezados($columnas));

        $consulta = $this->reportes->consulta($slug, $normalizados);

        foreach ($consulta->lazy(500) as $registro) {
            $this->csv($stream, $this->fila($columnas, $registro));
        }

        fflush($stream);
    }

    /**
     * @param  resource  $stream
     * @param  list<string>  $valores
     */
    private function csv($stream, array $valores): void
    {
        fputcsv($stream, $valores, self::SEPARADOR, self::ENCLOSURE, self::ESCAPE);
    }
}
