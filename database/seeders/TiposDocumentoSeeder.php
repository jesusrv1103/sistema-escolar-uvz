<?php

namespace Database\Seeders;

use App\Models\TipoDocumento;
use Illuminate\Database\Seeder;

/**
 * Tipos de documento ya definidos en la arquitectura aprobada (§4.11).
 *
 * Solo se siembra lo documentado: la documentación académica del roadmap
 * (constancias, kardex, etc.) se agregará en sus fases correspondientes.
 * Idempotente.
 */
class TiposDocumentoSeeder extends Seeder
{
    /** @var list<array{clave: string, nombre: string, obligatorio: bool}> */
    private const TIPOS = [
        ['clave' => 'ACTA_NAC', 'nombre' => 'Acta de nacimiento', 'obligatorio' => true],
    ];

    public function run(): void
    {
        foreach (self::TIPOS as $tipo) {
            TipoDocumento::updateOrCreate(
                ['clave' => $tipo['clave']],
                [
                    'nombre' => $tipo['nombre'],
                    'obligatorio' => $tipo['obligatorio'],
                    'activo' => true,
                ],
            );
        }

        $this->command?->info('Tipos de documento sembrados: '.count(self::TIPOS).'.');
    }
}
