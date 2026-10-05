<?php

namespace Database\Factories;

use App\Models\Docente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Docente>
 */
class DocenteFactory extends Factory
{
    protected static int $counter = 0;

    protected $model = Docente::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $claves = ['DOC', 'PROF', 'MAES', 'LIC', 'DR'];
        $clave = $claves[self::$counter % count($claves)] . '_' . (self::$counter++);

        $hasRfc = $this->faker->boolean(50);
        $hasTelefono = $this->faker->boolean(70);
        $hasEspecialidad = $this->faker->boolean(70);
        $hasGrado = $this->faker->boolean(50);

        return [
            'user_id' => null,
            'clave' => $claves[self::$counter % count($claves)] . '_' . (self::$counter++),
            'nombre' => $this->faker->firstName(),
            'apellidos' => $this->faker->lastName(),
            'curp' => $this->faker->regexify('[A-Z]{4}[0-9]{6}[HM][A-Z]{2}[A-Z]{3}[0-9]{2}'),
            'rfc' => $this->faker->boolean(50) ? $this->faker->unique()->regexify('[A-Z]{3,4}[0-9]{6}[A-Z0-9]{3}') : null,
            'correo' => $this->faker->boolean(70) ? $this->faker->unique()->safeEmail() : null,
            'telefono' => $hasTelefono ? $this->faker->numerify('##########') : null,
            'especialidad' => $this->faker->boolean(70) ? $this->faker->words(2, true) : null,
            'grado_estudios' => $this->faker->boolean(50) ? $this->faker->randomElement(['Licenciatura', 'Maestría', 'Doctorado', 'Especialidad']) : null,
            'cedula_profesional' => $this->faker->optional(0.5)->numerify('########'),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }

    public function conUsuario(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => \App\Models\User::factory()->deRol(\App\Enums\RolUsuario::DOCENTE)->create(),
        ]);
    }

    public static function resetCounter(): void
    {
        self::$counter = 0;
    }
}