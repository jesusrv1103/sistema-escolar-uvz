<?php

namespace Database\Factories;

use App\Enums\TipoEvaluacion;
use App\Models\Evaluacion;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluacion>
 */
class EvaluacionFactory extends Factory
{
    protected $model = Evaluacion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $i = 0;
        $tipos = ['PARCIAL', 'EXAMEN', 'PROYECTO', 'PRACTICA', 'TAREA', 'EXPOSICION', 'OTRO'];
        $tipo = $tipos[$i % count($tipos)];
        $nombres = ['Parcial', 'Examen', 'Proyecto', 'Práctica', 'Tarea', 'Exposición', 'Otro'];
        $nombre = $nombres[$i++ % count($nombres)] . ' ' . ($i);

        return [
            'grupo_id' => Grupo::factory(),
            'nombre' => $nombre,
            'tipo' => $tipo,
            'ponderacion' => fake()->numberBetween(10, 40),
            'fecha' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'creada_por' => \App\Models\User::factory(),
        ];
    }

    public function borrador(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}