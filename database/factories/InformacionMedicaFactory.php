<?php

namespace Database\Factories;

use App\Enums\TipoSangre;
use App\Models\Alumno;
use App\Models\InformacionMedica;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo datos sintéticos para pruebas: nunca datos médicos reales.
 *
 * @extends Factory<InformacionMedica>
 */
class InformacionMedicaFactory extends Factory
{
    protected $model = InformacionMedica::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'alumno_id' => Alumno::factory(),
            'tipo_sangre' => fake()->randomElement(TipoSangre::cases()),
            'alergias' => 'Ninguna (dato sintético)',
            'condiciones' => null,
            'medicamentos' => null,
            'tiene_discapacidad' => false,
            'detalle_discapacidad' => null,
            'actualizado_por' => User::factory(),
        ];
    }
}
