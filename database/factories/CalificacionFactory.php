<?php

namespace Database\Factories;

use App\Models\Calificacion;
use App\Models\Evaluacion;
use App\Models\InscripcionMateria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Calificacion>
 */
class CalificacionFactory extends Factory
{
    protected $model = Calificacion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'inscripcion_materia_id' => InscripcionMateria::factory(),
            'evaluacion_id' => Evaluacion::factory(),
            'valor' => fake()->randomFloat(2, 0, 100),
            'capturada_por' => \App\Models\User::factory(),
        ];
    }

    public function cursando(): static
    {
        return $this->state(fn (array $attributes) => ['estatus' => 'CURSANDO']);
    }

    public function aprobada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => 'APROBADA',
            'calificacion_final' => fake()->randomFloat(2, 70, 100),
        ]);
    }

    public function reprobada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => 'REPROBADA',
            'calificacion_final' => fake()->randomFloat(2, 0, 69),
        ]);
    }

    public function baja(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => 'BAJA',
            'fecha_baja' => now(),
        ]);
    }

    public function validada(): static
    {
        return $this->state(fn (array $attributes) => ['estatus' => 'VALIDADA']);
    }

    public function prerrequisitoObligatorio(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_prerrequisito' => \App\Enums\TipoPrerrequisito::OBLIGATORIO,
        ]);
    }

    public function prerrequisitoRecomendado(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_prerrequisito' => \App\Enums\TipoPrerrequisito::RECOMENDADO,
        ]);
    }

    public function prerrequisitoNinguno(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_prerrequisito' => \App\Enums\TipoPrerrequisito::NINGUNO,
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