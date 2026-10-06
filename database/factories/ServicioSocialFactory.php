<?php

namespace Database\Factories;

use App\Enums\EstatusServicioSocial;
use App\Models\Alumno;
use App\Models\ServicioSocial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicioSocial>
 */
class ServicioSocialFactory extends Factory
{
    protected $model = ServicioSocial::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'alumno_id' => Alumno::factory(),
            'actividad' => 'Apoyo en archivo y control escolar',
            'dependencia' => 'Unidad de Servicios Escolares',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => now()->addMonths(3)->toDateString(),
            'avance' => fake()->numberBetween(0, 100),
            'estatus' => EstatusServicioSocial::EN_PROCESO,
        ];
    }

    public function validado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => EstatusServicioSocial::VALIDADO,
            'avance' => 100,
            'validado_at' => now(),
        ]);
    }
}
