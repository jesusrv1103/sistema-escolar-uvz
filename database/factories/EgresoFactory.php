<?php

namespace Database\Factories;

use App\Models\Alumno;
use App\Models\Egreso;
use App\Models\PlanVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Egreso>
 */
class EgresoFactory extends Factory
{
    protected $model = Egreso::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $plan = PlanVersion::factory()->vigente()->create();

        return [
            'alumno_id' => Alumno::factory(),
            'plan_version_id' => $plan->id,
            'creditos_requeridos' => fake()->numberBetween(100, 240),
            'creditos_acumulados' => fn (array $attributes) => $attributes['creditos_requeridos'],
            'requisitos' => [
                ['clave' => 'plan', 'cumplido' => true],
                ['clave' => 'materias', 'cumplido' => true],
                ['clave' => 'creditos', 'cumplido' => true],
                ['clave' => 'servicio_social', 'cumplido' => true],
                ['clave' => 'practicas', 'cumplido' => true],
                ['clave' => 'sin_baja', 'cumplido' => true],
            ],
            'registrado_por' => User::factory(),
            'registrado_at' => now(),
        ];
    }
}
