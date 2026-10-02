<?php

namespace Database\Factories;

use App\Enums\InscripcionEstatus;
use App\Models\Alumno;
use App\Models\Inscripcion;
use App\Models\Periodo;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo datos sintéticos: alumnos de factory (CURP ficticia `ZZZZ…`),
 * programas/periodos sintéticos creados por primera vez en la BD de pruebas.
 *
 * @extends Factory<Inscripcion>
 */
class InscripcionFactory extends Factory
{
    protected $model = Inscripcion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'alumno_id' => Alumno::factory(),
            'periodo_id' => self::periodoPrueba()->getKey(),
            'programa_id' => self::programaPrueba()->getKey(),
            'estatus' => InscripcionEstatus::DRAFT->value,   // default de columna: DRAFT
            'monto_inscripcion' => '1500.00',
            'monto_mensualidad' => '1200.00',
            'monto_descuento' => '0.00',
            'creado_por' => User::factory(),
        ];
    }

    /** Estado inicial específico para recorrer la máquina de estados. */
    public function enEstatus(InscripcionEstatus $estatus): static
    {
        return $this->state(['estatus' => $estatus->value]);
    }

    /** @param array<string, mixed> $atributos */
    public function enPeriodo(Periodo $periodo): static
    {
        return $this->state(['periodo_id' => $periodo->getKey()]);
    }

    private static function programaPrueba(): Programa
    {
        return Programa::firstOrCreate(
            ['clave' => 'SINT'],
            ['nombre' => 'Licenciatura sintética (pruebas)', 'activo' => true],
        );
    }

    private static function periodoPrueba(): Periodo
    {
        return Periodo::firstOrCreate(
            ['clave' => 'SINT-2026'],
            [
                'nombre' => 'Periodo sintético de pruebas',
                'fecha_inicio' => '2026-08-01',
                'fecha_fin' => '2026-12-18',
                'activo' => true,
            ],
        );
    }
}
