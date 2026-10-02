<?php

namespace Database\Factories;

use App\Enums\Sexo;
use App\Models\Alumno;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo datos sintéticos: la CURP se genera con estructura `ZZZZ...` para que sea
 * evidentemente ficticia (nunca una CURP real de una persona).
 *
 * @extends Factory<Alumno>
 */
class AlumnoFactory extends Factory
{
    private static int $secuencia = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $sexo = fake()->randomElement([Sexo::MASCULINO, Sexo::FEMENINO]);

        // Secuencia determinista en el proceso + bloque aleatorio: CURP única
        // incluso entre procesos, y evidentemente ficticia (prefijo ZZZZ…).
        $secuencia = self::$secuencia++;
        $curp = sprintf(
            'ZZZZ%02d%02d%02d%s%s%s%d',
            60 + intdiv($secuencia, 336) % 40,   // YY
            1 + intdiv($secuencia, 28) % 12,      // MM
            1 + $secuencia % 28,                  // DD
            $sexo === Sexo::MASCULINO ? 'H' : 'M',
            strtoupper(fake()->randomLetter()
                .fake()->randomLetter()
                .fake()->randomLetter()
                .fake()->randomLetter()
                .fake()->randomLetter()),
            chr(65 + $secuencia % 26),
            $secuencia % 10,
        );

        return [
            'nombre' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'curp' => $curp,
            'fecha_nacimiento' => fake()->dateTimeBetween('-30 years', '-10 years')->format('Y-m-d'),
            'sexo' => $sexo,
            'nacionalidad' => 'Mexicana',
            'correo' => fake()->unique()->safeEmail(),
            'telefono' => '55'.fake()->numerify('########'),
            'calle' => 'Calle Ficticia '.fake()->numberBetween(1, 99),
            'colonia' => 'Colonia de Prueba',
            'cp' => fake()->numerify('#####'),
            'municipio' => 'Zacatecas (sintético)',
            'estado' => 'Zacatecas (sintético)',
            'pais' => 'México',
            'creado_por' => User::factory(),
            // numero_control permanece NULL (se asigna en la Fase 12).
        ];
    }
}
