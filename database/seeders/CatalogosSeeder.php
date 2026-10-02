<?php

namespace Database\Seeders;

use App\Models\Programa;
use Illuminate\Database\Seeder;

/**
 * Semilla inicial de catálogo (Fase 3).
 *
 * - Los tres programas publicados por el sitio institucional UVZ (§23.4).
 * - `clave` = identificador INTERNO del sistema (no es RVOE).
 * - `duracion_periodos` queda NULL: no existe confirmación institucional;
 *   el valor se captura desde el catálogo administrable.
 * - Idempotente: puede ejecutarse varias veces.
 */
class CatalogosSeeder extends Seeder
{
    /** @var list<array{clave: string, nombre: string}> */
    private const PROGRAMAS = [
        ['clave' => 'DER', 'nombre' => 'Licenciatura en Derecho'],
        ['clave' => 'SAI', 'nombre' => 'Licenciatura en Seguridad y Administración Industrial'],
        ['clave' => 'CF', 'nombre' => 'Licenciatura en Ciencia Forense'],
    ];

    public function run(): void
    {
        foreach (self::PROGRAMAS as $programa) {
            Programa::updateOrCreate(
                ['clave' => $programa['clave']],
                [
                    'nombre' => $programa['nombre'],
                    'duracion_periodos' => null,
                    'activo' => true,
                ],
            );
        }

        $this->command?->info('Programas UVZ sembrados (claves internas: DER, SAI, CF).');
    }
}
