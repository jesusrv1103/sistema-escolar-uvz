<?php

namespace Database\Factories;

use App\Models\BecaAlumno;
use App\Models\Beca;
use App\Models\Alumno;
use App\Models\Periodo;
use App\Models\User;
use App\Enums\EstatusBeca;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BecaAlumno>
 */
class BecaAlumnoFactory extends Factory
{
    protected $model = BecaAlumno::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'beca_id' => Beca::factory(),
            'alumno_id' => Alumno::factory(),
            'periodo_id' => Periodo::factory(),
            'estatus' => EstatusBeca::PENDIENTE,
            'asignada_por' => User::factory()->deRol(\App\Enums\RolUsuario::CONTROL_ESCOLAR),
        ];
    }

    public function aprobada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => EstatusBeca::APROBADA,
            'aprobada_por' => User::factory()->deRol(\App\Enums\RolUsuario::ADMINISTRACION),
            'fecha_aprobacion' => now(),
        ]);
    }

    public function rechazada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => EstatusBeca::RECHAZADA,
            'aprobada_por' => User::factory()->deRol(\App\Enums\RolUsuario::ADMINISTRACION),
            'fecha_aprobacion' => now(),
        ]);
    }
}
