<?php

namespace Database\Factories;

use App\Enums\AlcanceBeca;
use App\Enums\TipoBeca;
use App\Models\Beca;
use App\Models\Periodo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beca>
 */
class BecaFactory extends Factory
{
    protected $model = Beca::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'clave' => 'BEC-' . str_pad(random_int(1, 999), 3, '0', STR_PAD_LEFT),
            'nombre' => fake()->words(2, true) . ' Beca',
            'tipo' => fake()->randomElement(TipoBeca::cases()),
            'valor' => fake()->randomFloat(2, 5, 100),
            'alcance' => fake()->randomElement(AlcanceBeca::cases()),
            'periodo_id' => Periodo::factory(),
            'activo' => true,
            'fecha_inicio' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'fecha_fin' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
        ];
    }

    public function porcentaje(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => TipoBeca::PORCENTAJE,
            'valor' => fake()->numberBetween(5, 50),
        ]);
    }

    public function monto(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => TipoBeca::MONTO,
            'valor' => fake()->randomFloat(2, 100, 5000),
        ]);
    }

    public function inscripcion(): static
    {
        return $this->state(fn (array $attributes) => ['alcance' => AlcanceBeca::INSCRIPCION]);
    }

    public function mensualidades(): static
    {
        return $this->state(fn (array $attributes) => ['alcance' => AlcanceBeca::MENSUALIDADES]);
    }

    public function ambos(): static
    {
        return $this->state(fn (array $attributes) => ['alcance' => AlcanceBeca::AMBOS]);
    }

    public function paraPeriodo(\App\Models\Periodo $periodo): static
    {
        return $this->state(fn (array $attributes) => ['periodo_id' => $periodo->id]);
    }
}
