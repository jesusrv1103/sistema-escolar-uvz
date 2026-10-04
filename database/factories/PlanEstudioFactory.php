<?php

namespace Database\Factories;

use App\Enums\EstatusPlanVersion;
use App\Models\PlanEstudio;
use App\Models\Programa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanEstudio>
 */
class PlanEstudioFactory extends Factory
{
    protected $model = PlanEstudio::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $i = 0;
        $claves = ['DER', 'SAI', 'CF', 'LIN', 'ADM', 'PSI', 'DERE', 'CONT', 'ARQ', 'ING'];
        $clave = $claves[$i % count($claves)] . '_' . ($i++);

        return [
            'programa_id' => Programa::factory(),
            'clave' => $clave,
            'nombre' => fake()->words(4, true),
            'resolucion_rvoe' => fake()->optional(0.5)->regexify('RVOE-########'),
            'fecha_resolucion' => fake()->optional(0.5)->dateTimeBetween('-5 years', 'now')?->format('Y-m-d'),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }

    public function paraPrograma(Programa $programa): static
    {
        return $this->state(fn (array $attributes) => ['programa_id' => $programa->id]);
    }
}