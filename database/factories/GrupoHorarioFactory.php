<?php

namespace Database\Factories;

use App\Enums\DiaSemana;
use App\Models\GrupoHorario;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrupoHorario>
 */
class GrupoHorarioFactory extends Factory
{
    protected $model = GrupoHorario::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $i = 0;
        $dias = [\App\Enums\DiaSemana::LUNES, \App\Enums\DiaSemana::MARTES, \App\Enums\DiaSemana::MIERCOLES, \App\Enums\DiaSemana::JUEVES, \App\Enums\DiaSemana::VIERNES, \App\Enums\DiaSemana::SABADO];
        $dia = $dias[$i % count($dias)];
        $horaInicio = $this->faker->time('H:i', '07:00:00', '12:00:00');
        $horaFin = $this->faker->time('H:i', '13:00:00', '18:00:00');

        // Asegurar que horaFin > horaInicio
        if ($horaFin <= $horaInicio) {
            $horaFin = date('H:i', strtotime($horaInicio . ' +2 hours'));
        }

        return [
            'grupo_id' => Grupo::factory(),
            'dia' => $dia,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
        ];
    }

    public function delDia(\App\Enums\DiaSemana $dia): static
    {
        return $this->state(fn (array $attributes) => ['dia' => $dia]);
    }

    public function paraGrupo(\App\Models\Grupo $grupo): static
    {
        return $this->state(fn (array $attributes) => ['grupo_id' => $grupo->id]);
    }
}