<?php

namespace Database\Factories;

use App\Enums\TipoPrerrequisito;
use App\Models\Materia;
use App\Models\MateriaPrerrequisito;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MateriaPrerrequisito>
 */
class MateriaPrerrequisitoFactory extends Factory
{
    protected $model = MateriaPrerrequisito::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'materia_id' => Materia::factory(),
            'prerrequisito_id' => Materia::factory(),
            'tipo' => fake()->randomElement(TipoPrerrequisito::cases()),
        ];
    }

    public function obligatorio(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => TipoPrerrequisito::OBLIGATORIO]);
    }

    public function recomendado(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => TipoPrerrequisito::RECOMENDADO]);
    }

    /**
     * Crea un prerrequisito válido (misma versión, sin autorreferencia, sin ciclos).
     * Nota: para tests complejos usar el servicio directamente.
     */
    public function valido(): static
    {
        return $this->state(function (array $attributes) {
            // Crear dos materias en la misma versión
            $version = \App\Models\PlanVersion::factory()->propuesta()->create();
            $materia1 = \App\Models\Materia::factory()->delPeriodo(2)->for($version)->create();
            $materia2 = \App\Models\Materia::factory()->delPeriodo(1)->for($version)->create();

            return [
                'materia_id' => $materia1->id,
                'prerrequisito_id' => $materia2->id,
            ];
        });
    }
}