<?php

namespace Database\Factories;

use App\Enums\EstatusMatriculaPeriodo;
use App\Enums\OrigenMatricula;
use App\Models\MatriculaPeriodo;
use App\Models\Alumno;
use App\Models\Periodo;
use App\Models\Programa;
use App\Models\PlanVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatriculaPeriodo>
 */
class MatriculaPeriodoFactory extends Factory
{
    protected $model = MatriculaPeriodo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'alumno_id' => Alumno::factory(),
            'periodo_id' => Periodo::factory(),
            'programa_id' => Programa::factory(),
            'plan_version_id' => PlanVersion::factory(),
            'origen' => $this->faker->randomElement(OrigenMatricula::cases()),
            'fecha_matricula' => $this->faker->date(),
            'estatus' => $this->faker->randomElement(EstatusMatriculaPeriodo::cases()),
            'observaciones' => $this->faker->optional()->sentence(),
        ];
    }

    public function activa(): static
    {
        return $this->state(fn (array $attributes) => ['estatus' => EstatusMatriculaPeriodo::ACTIVA]);
    }

    public function primeraVez(): static
    {
        return $this->state(fn (array $attributes) => ['origen' => OrigenMatricula::PRIMERA_VEZ]);
    }

    public function reingreso(): static
    {
        return $this->state(fn (array $attributes) => ['origen' => OrigenMatricula::REINGRESO]);
    }

    public function paraAlumno(Alumno $alumno): static
    {
        return $this->state(fn (array $attributes) => ['alumno_id' => $alumno->id]);
    }

    public function paraPeriodo(Periodo $periodo): static
    {
        return $this->state(fn (array $attributes) => ['periodo_id' => $periodo->id]);
    }

    public function paraPrograma(Programa $programa): static
    {
        return $this->state(fn (array $attributes) => ['programa_id' => $programa->id]);
    }
}