<?php

namespace Database\Factories;

use App\Models\Programa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Programa>
 */
class ProgramaFactory extends Factory
{
    protected $model = Programa::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $i = 0;
        $claves = ['DER', 'SAI', 'CF', 'LIN', 'ADM', 'PSI', 'DERE', 'CONT'];
        $clave = $claves[$i % count($claves)] . '_' . $i++;

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
}