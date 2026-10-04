<?php

namespace App\Services;

use App\Enums\TipoConsecutivo;
use App\Models\Consecutivo;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para generación de folios secuenciales usando la tabla `consecutivos`.
 *
 * Implementa concurrencia segura mediante `lockForUpdate()` y `UNIQUE (tipo, anio)`.
 * No expone CRUD ni UI; solo el método `generar()`.
 */
class ConsecutivoService
{
    /**
     * Genera el siguiente folio para un tipo de consecutivo.
     *
     * Formato: PREFIJO-YYYY-######## (8 dígitos con ceros a la izquierda).
     * El año YYYY se deriva de la fecha actual en el timezone de la aplicación.
     *
     * @throws \RuntimeException si se agota el consecutivo (límite 9999)
     */
    public function generar(TipoConsecutivo $tipo): string
    {
        $anio = now()->year;
        $prefijo = $this->obtenerPrefijo($tipo);

        return DB::transaction(function () use ($tipo, $anio, $prefijo): string {
            // Insert-or-ignore para crear la fila del año si no existe (seguro ante concurrencia)
            Consecutivo::firstOrCreate(
                ['tipo' => $tipo->value, 'anio' => now()->year],
                ['ultimo_valor' => 0]
            );

            // Lock pesimista sobre la fila del año
            $consecutivo = Consecutivo::where('tipo', $tipo->value)
                ->where('anio', now()->year)
                ->lockForUpdate()
                ->firstOrFail();

            // Verificar límite 9999
            if ($consecutivo->ultimo_valor >= 9999) {
                throw new \RuntimeException(
                    "Se agotó el consecutivo para {$tipo->value} en el año " . now()->year
                );
            }

            // Incrementar y guardar
            $consecutivo->increment('ultimo_valor');
            $consecutivo->refresh();

            $numero = str_pad($consecutivo->ultimo_valor, 8, '0', STR_PAD_LEFT);
            $anio = now()->year;

            return "{$this->obtenerPrefijo($tipo)}-{$anio}-{$numero}";
        });
    }

    /**
     * Obtiene el prefijo para un tipo de consecutivo.
     */
    private function obtenerPrefijo(TipoConsecutivo $tipo): string
    {
        return match ($tipo) {
            \App\Enums\TipoConsecutivo::NUMERO_CONTROL => 'NC',
            \App\Enums\TipoConsecutivo::CONSTANCIA_INSCRIPCION => 'CI',
            \App\Enums\TipoConsecutivo::ACTA_CALIFICACIONES => 'ACT',
            default => throw new \InvalidArgumentException("Prefijo no definido para {$tipo->value}"),
        };
    }
}