<?php

namespace Database\Factories;

use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoDocumento>
 */
class TipoDocumentoFactory extends Factory
{
    protected $model = TipoDocumento::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $i = 0;
        $claves = ['ACTA_NAC', 'CURP', 'CERT_EST', 'IDENTIFICACION', 'COMP_DOM', 'FOTO', 'CARTA_COND', 'OTRO'];
        $clave = $claves[$i % count($claves)] . '_' . $i++;

        return [
            'clave' => $clave,
            'nombre' => fake()->words(3, true),
            'obligatorio' => fake()->boolean(70),
            'activo' => true,
        ];
    }
}