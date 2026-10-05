<?php

namespace Database\Factories;

use App\Enums\TipoAula;
use App\Models\Aula;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Aula>
 */
class AulaFactory extends Factory
{
    protected static int $counter = 0;

    protected $model = Aula::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $claves = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $edificios = ['Edificio A', 'Edificio B', 'Edificio C', 'Edificio Central', 'Edificio de Ingenierias', 'Edificio de Ciencias', 'Edificio de Humanidades'];
        $tipos = [TipoAula::TEORICA, TipoAula::PRACTICA, TipoAula::MIXTA, TipoAula::LABORATORIO];

        return [
            'clave' => $claves[self::$counter % count($claves)] . '-' . str_pad((self::$counter++ % 100) + 1, 3, '0', STR_PAD_LEFT),
            'nombre' => $this->faker->words(3, true),
            'edificio' => $this->faker->randomElement($edificios),
            'capacidad' => $this->faker->numberBetween(20, 100),
            'tipo' => $this->faker->randomElement($tipos),
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }

    public function laboratorio(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => TipoAula::LABORATORIO, 'capacidad' => $this->faker->numberBetween(15, 40)]);
    }

    public function teorica(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => TipoAula::TEORICA, 'capacidad' => $this->faker->numberBetween(30, 80)]);
    }

    public static function resetCounter(): void
    {
        self::$counter = 0;
    }
}