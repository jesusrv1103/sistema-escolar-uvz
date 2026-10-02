<?php

namespace Database\Factories;

use App\Enums\EstatusConceptoPago;
use App\Enums\TipoConcepto;
use App\Models\ConceptoPago;
use App\Models\Inscripcion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo datos sintéticos: los cargos reales los crea InscripcionService.
 * Las pruebas de generación usan el servicio; esta factory sirve para
 * preparar escenarios (p. ej. cargos previos) sin duplicar lógica.
 *
 * @extends Factory<ConceptoPago>
 */
class ConceptoPagoFactory extends Factory
{
    protected $model = ConceptoPago::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'inscripcion_id' => Inscripcion::factory(),
            'tipo' => TipoConcepto::MENSUALIDAD->value,
            'numero' => 1,
            'concepto' => 'Mensualidad 1 - septiembre 2026 (sintética)',
            'monto' => '1200.00',
            'descuento' => '0.00',
            'monto_total' => '1200.00',
            'fecha_vencimiento' => '2026-09-10',
            'estatus' => EstatusConceptoPago::PENDIENTE->value,
            'pagado_at' => null,
        ];
    }

    /** Concepto de INSCRIPCION (para escenarios con cargos previos). */
    public function inscripcion(): static
    {
        return $this->state([
            'tipo' => TipoConcepto::INSCRIPCION->value,
            'numero' => null,
            'concepto' => 'Inscripción (sintética)',
            'monto' => '1500.00',
            'monto_total' => '1500.00',
        ]);
    }
}
