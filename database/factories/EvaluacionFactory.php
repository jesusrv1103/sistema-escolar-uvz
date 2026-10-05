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
    protected static int $counter = 0;

    protected $model = Evaluacion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $tipos = ['PARCIAL', 'EXAMEN', 'PROYECTO', 'PRACTICA', 'TAREA', 'EXPOSICION', 'OTRO'];
        $tipo = $tipos[self::$counter % count($tipos)];
        $nombres = ['Parcial', 'Examen', 'Proyecto', 'Práctica', 'Tarea', 'Exposición', 'Otro'];
        $nombre = $nombres[self::$counter++ % count($nombres)] . ' ' . (self::$counter);

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

    public static function resetCounter(): void
    {
        self::$counter = 0;
    }
}