<?php

namespace Database\Factories;

use App\Enums\EstatusPracticaProfesional;
use App\Models\Alumno;
use App\Models\PracticaProfesional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PracticaProfesional>
 */
class PracticaProfesionalFactory extends Factory
{
    protected $model = PracticaProfesional::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'alumno_id' => Alumno::factory(),
            'empresa' => 'Empresa demo S.A. de C.V.',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => now()->addMonths(3)->toDateString(),
            'asesor' => 'Ing. Demo Apellido',
            'calificacion' => null, // Texto libre: sin escala inferida
            'estatus' => EstatusPracticaProfesional::EN_PROCESO,
        ];
    }

    public function validado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => EstatusPracticaProfesional::VALIDADO,
            'validado_at' => now(),
        ]);
    }
}
