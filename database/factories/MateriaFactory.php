<?php

namespace Database\Factories;

use App\Models\Materia;
use App\Models\PlanVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Materia>
 */
class MateriaFactory extends Factory
{
    protected $model = Materia::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $i = 0;
        $claves = ['MAT', 'ALG', 'CAL', 'FIS', 'QUI', 'BIO', 'HIS', 'FIL', 'DER', 'ECO', 'ADM', 'CON', 'PSI', 'SOC', 'ANT', 'GEO', 'ART', 'MUS', 'EDU', 'DEP'];
        $clave = $claves[$i % count($claves)] . '-' . str_pad($i++, 3, '0', STR_PAD_LEFT);

        return [
            'plan_version_id' => PlanVersion::factory()->propuesta(),
            'clave' => $clave,
            'nombre' => fake()->words(3, true),
            'creditos' => fake()->numberBetween(2, 8),
            'horas_teoricas' => fake()->optional(0.7)->numberBetween(16, 48),
            'horas_practicas' => fake()->optional(0.5)->numberBetween(16, 48),
            'periodo_orden' => fake()->numberBetween(1, 12),
            'bloque' => fake()->optional(0.3)->randomElement(['TRONCO_COMUN', 'ESPECIALIDAD', 'OPTATIVA', 'PRACTICAS']),
            'obligatoria' => fake()->boolean(80),
            'activo' => true,
        ];
    }

    public function obligatoria(): static
    {
        return $this->state(fn (array $attributes) => ['obligatoria' => true]);
    }

    public function optativa(): static
    {
        return $this->state(fn (array $attributes) => ['obligatoria' => false]);
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }

    public function delPeriodo(int $orden): static
    {
        return $this->state(fn (array $attributes) => ['periodo_orden' => $orden]);
    }

    public function paraPlanVersion(PlanVersion $planVersion): static
    {
        return $this->state(fn (array $attributes) => ['plan_version_id' => $planVersion->id]);
    }
}