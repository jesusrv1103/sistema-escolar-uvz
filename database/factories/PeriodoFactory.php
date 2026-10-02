<?php

namespace Database\Factories;

use App\Models\Periodo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Periodo>
 */
class PeriodoFactory extends Factory
{
    protected $model = Periodo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('-1 year', '+1 year');
        $fin = (clone $inicio)->modify('+4 months');

        return [
            'clave' => $inicio->format('Y') . '-' . fake()->unique()->numberBetween(1, 2),
            'nombre' => $inicio->format('Y') . ' Periodo ' . fake()->numberBetween(1, 4),
            'fecha_inicio' => $inicio->format('Y-m-d'),
            'fecha_fin' => $fin->format('Y-m-d'),
            'activo' => true,
            'cerrado_at' => null,
        ];
    }

    public function cerrado(): static
    {
        return $this->state(fn (array $attributes) => [
            'cerrado_at' => now(),
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}