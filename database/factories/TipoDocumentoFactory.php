<?php

namespace Database\Factories;

use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoDocumento>
 */
class TipoDocumentoFactory extends Factory
{
    protected static int $counter = 0;

    protected $model = TipoDocumento::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $claves = ['ACTA_NAC', 'CURP', 'CERT_EST', 'IDENTIFICACION', 'COMP_DOM', 'FOTO', 'CARTA_COND', 'OTRO'];
        $clave = $claves[self::$counter % count($claves)] . '_' . self::$counter++;

        return [
            'clave' => $clave,
            'nombre' => fake()->words(3, true),
            'obligatorio' => fake()->boolean(70),
            'activo' => true,
        ];
    }

    public static function resetCounter(): void
    {
        self::$counter = 0;
    }
}