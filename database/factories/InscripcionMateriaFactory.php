<?php

namespace Database\Factories;

use App\Enums\EstatusInscripcionMateria;
use App\Enums\TipoPrerrequisito;
use App\Models\InscripcionMateria;
use App\Models\MatriculaPeriodo;
use App\Models\Grupo;
use App\Models\Materia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InscripcionMateria>
 */
class InscripcionMateriaFactory extends Factory
{
    protected $model = InscripcionMateria::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'matricula_periodo_id' => MatriculaPeriodo::factory(),
            'grupo_id' => Grupo::factory(),
            'materia_id' => Materia::factory(),
            'tipo_prerrequisito' => $this->faker->randomElement(TipoPrerrequisito::cases()),
            'prerrequisito_cumplido' => $this->faker->boolean(80),
            'estatus' => $this->faker->randomElement(EstatusInscripcionMateria::cases()),
            'calificacion_final' => $this->faker->optional()->randomFloat(2, 60, 100),
            'fecha_baja' => $this->faker->optional()->date(),
            'observaciones' => $this->faker->optional()->sentence(),
        ];
    }

    public function cursando(): static
    {
        return $this->state(fn (array $attributes) => ['estatus' => EstatusInscripcionMateria::CURSANDO]);
    }

    public function aprobada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => EstatusInscripcionMateria::APROBADA,
            'calificacion_final' => $this->faker->randomFloat(2, 70, 100),
        ]);
    }

    public function reprobada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => EstatusInscripcionMateria::REPROBADA,
            'calificacion_final' => $this->faker->randomFloat(2, 0, 69),
        ]);
    }

    public function baja(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => EstatusInscripcionMateria::BAJA,
            'fecha_baja' => now(),
        ]);
    }

    public function validada(): static
    {
        return $this->state(fn (array $attributes) => ['estatus' => EstatusInscripcionMateria::VALIDADA]);
    }

    public function prerrequisitoObligatorio(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_prerrequisito' => TipoPrerrequisito::OBLIGATORIO,
        ]);
    }

    public function prerrequisitoRecomendado(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_prerrequisito' => TipoPrerrequisito::RECOMENDADO,
        ]);
    }

    public function paraMatricula(MatriculaPeriodo $matricula): static
    {
        return $this->state(fn (array $attributes) => ['matricula_periodo_id' => $matricula->id]);
    }

    public function paraGrupo(Grupo $grupo): static
    {
        return $this->state(fn (array $attributes) => [
            'grupo_id' => $grupo->id,
            'materia_id' => $grupo->materia_id,
        ]);
    }
}