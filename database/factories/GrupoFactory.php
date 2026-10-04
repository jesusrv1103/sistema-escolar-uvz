<?php

namespace Database\Factories;

use App\Enums\EstatusGrupo;
use App\Enums\TurnoGrupo;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Periodo;
use App\Models\Docente;
use App\Models\Aula;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grupo>
 */
class GrupoFactory extends Factory
{
    protected $model = Grupo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $i = 0;
        $claves = ['GRP', 'GRU', 'SECC', 'SEC', 'GR'];
        $clave = $claves[$i % count($claves)] . '_' . ($i++) . '_' . $this->faker->unique()->randomNumber(4);

        return [
            'materia_id' => Materia::factory(),
            'periodo_id' => Periodo::factory(),
            'docente_id' => Docente::factory(),
            'aula_id' => Aula::factory(),
            'clave' => $clave,
            'nombre' => null,
            'cupo' => $this->faker->numberBetween(20, 80),
            'turno' => $this->faker->randomElement(TurnoGrupo::cases()),
            'estatus' => $this->faker->randomElement(EstatusGrupo::cases()),
            'activo' => true,
        ];
    }

    public function creado(): static
    {
        return $this->state(fn (array $attributes) => ['estatus' => EstatusGrupo::CREADO]);
    }

    public function abierto(): static
    {
        return $this->state(fn (array $attributes) => ['estatus' => EstatusGrupo::ABIERTO]);
    }

    public function enCurso(): static
    {
        return $this->state(fn (array $attributes) => ['estatus' => EstatusGrupo::EN_CURSO]);
    }

    public function cerrado(): static
    {
        return $this->state(fn (array $attributes) => ['estatus' => EstatusGrupo::CERRADO]);
    }

    public function cancelado(): static
    {
        return $this->state(fn (array $attributes) => ['estatus' => EstatusGrupo::CANCELADO]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }

    public function matutino(): static
    {
        return $this->state(fn (array $attributes) => ['turno' => TurnoGrupo::MATUTINO]);
    }

    public function vespertino(): static
    {
        return $this->state(fn (array $attributes) => ['turno' => TurnoGrupo::VESPERTINO]);
    }

    public function nocturno(): static
    {
        return $this->state(fn (array $attributes) => ['turno' => TurnoGrupo::NOCTURNO]);
    }

    public function paraMateria(Materia $materia): static
    {
        return $this->state(fn (array $attributes) => ['materia_id' => $materia->id]);
    }

    public function paraPeriodo(Periodo $periodo): static
    {
        return $this->state(fn (array $attributes) => ['periodo_id' => $periodo->id]);
    }

    public function paraDocente(Docente $docente): static
    {
        return $this->state(fn (array $attributes) => ['docente_id' => $docente->id]);
    }

    public function paraAula(Aula $aula): static
    {
        return $this->state(fn (array $attributes) => ['aula_id' => $aula->id]);
    }
}