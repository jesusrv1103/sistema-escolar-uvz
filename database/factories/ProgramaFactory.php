<?php

namespace Database\Factories;

use App\Models\Programa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Programa>
 */
class ProgramaFactory extends Factory
{
    protected static int $counter = 0;

    protected $model = Programa::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $claves = ['DER', 'SAI', 'CF', 'LIN', 'ADM', 'PSI', 'DERE', 'CONT'];
        $clave = $claves[self::$counter % count($claves)] . '_' . self::$counter++;

        return [
            'clave' => $clave,
            'nombre' => fake()->words(4, true),
            'duracion_periodos' => fake()->optional(0.5)->numberBetween(4, 12),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    public static function resetCounter(): void
    {
        self::$counter = 0;
    }
}