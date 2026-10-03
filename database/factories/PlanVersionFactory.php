<?php

namespace Database\Factories;

use App\Enums\EstatusPlanVersion;
use App\Models\PlanVersion;
use App\Models\PlanEstudio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanVersion>
 */
class PlanVersionFactory extends Factory
{
    protected $model = PlanVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $i = 0;
        return [
            'plan_id' => PlanEstudio::factory(),
            'version' => '1.' . ($i++ % 10),
            'fecha_vigencia' => fake()->optional(0.7)->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            'estatus' => fake()->randomElement(EstatusPlanVersion::cases()),
            'aprobado_por' => null,
            'aprobado_at' => null,
        ];
    }

    public function propuesta(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => EstatusPlanVersion::PROPUESTA,
            'aprobado_por' => null,
            'aprobado_at' => null,
        ]);
    }

    public function vigente(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => EstatusPlanVersion::VIGENTE,
            'aprobado_por' => \App\Models\User::factory()->deRol(\App\Enums\RolUsuario::ADMIN),
            'aprobado_at' => now(),
        ]);
    }

    public function superada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => EstatusPlanVersion::SUPERADA,
        ]);
    }
}