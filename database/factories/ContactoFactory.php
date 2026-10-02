<?php

namespace Database\Factories;

use App\Enums\TipoContacto;
use App\Models\Alumno;
use App\Models\Contacto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo datos sintéticos para pruebas (nombres y teléfonos ficticios).
 *
 * @extends Factory<Contacto>
 */
class ContactoFactory extends Factory
{
    protected $model = Contacto::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'alumno_id' => Alumno::factory(),
            'tipo' => TipoContacto::TUTOR,
            'nombre' => fake()->name(),
            'parentesco' => fake()->randomElement(['Madre', 'Padre', 'Tía(o)', 'Hermano(a)', 'Abuelo(a)']),
            'telefono' => '55'.fake()->numerify('########'),
            'telefono_alt' => null,
            'correo' => fake()->safeEmail(),
            'notas' => null,
        ];
    }

    public function deTipo(TipoContacto $tipo): static
    {
        return $this->state(['tipo' => $tipo]);
    }
}
