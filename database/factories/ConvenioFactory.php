<?php

namespace Database\Factories;

use App\Enums\TipoConvenio;
use App\Models\Convenio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Convenio>
 */
class ConvenioFactory extends Factory
{
    protected $model = Convenio::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $i = 0;
        $claves = ['BEC_10', 'BEC_20', 'DESC_500', 'DESC_1000', 'PROMO_1', 'PROMO_2', 'CONV_1', 'CONV_2'];
        $clave = $claves[$i % count($claves)] . '_' . $i++;
        $tipo = fake()->randomElement(TipoConvenio::cases());

        return [
            'clave' => $clave,
            'nombre' => fake()->words(3, true),
            'tipo' => $tipo->value,
            'valor' => $tipo === TipoConvenio::PORCENTAJE 
                ? fake()->randomFloat(2, 1, 50)
                : fake()->randomFloat(2, 500, 5000),
            'fecha_inicio' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'fecha_fin' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'activo' => true,
            'notas' => fake()->optional()->sentence(),
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    public function fueraDeVigencia(): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha_inicio' => fake()->dateTimeBetween('-2 years', '-1 year')->format('Y-m-d'),
            'fecha_fin' => fake()->dateTimeBetween('-1 year', '-6 months')->format('Y-m-d'),
        ]);
    }
}