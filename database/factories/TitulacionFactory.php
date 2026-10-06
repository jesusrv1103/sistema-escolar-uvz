<?php

namespace Database\Factories;

use App\Enums\EstatusTitulacion;
use App\Models\Alumno;
use App\Models\Egreso;
use App\Models\Titulacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Titulacion>
 */
class TitulacionFactory extends Factory
{
    protected $model = Titulacion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // El egreso define al alumno: se reutiliza su expediente para que
        // alumno y egreso siempre coincidan.
        return [
            'egreso_id' => Egreso::factory(),
            'alumno_id' => fn (array $attributes) => Egreso::query()->findOrFail($attributes['egreso_id'])->alumno_id,
            'modalidad' => null, // Texto libre: sin catálogo inferido
            'folio_interno' => null, // Captura manual opcional
            'estatus' => EstatusTitulacion::EN_TRAMITE,
            'registrado_por' => User::factory(),
            'registrado_at' => now(),
        ];
    }

    public function titulado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => EstatusTitulacion::TITULADO,
        ]);
    }
}
