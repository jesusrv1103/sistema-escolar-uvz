<?php

namespace Database\Factories;

use App\Enums\EstatusPago;
use App\Enums\MetodoPago;
use App\Models\ConceptoPago;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo datos sintéticos: los pagos reales los crea `PagoService` (reglas de
 * negocio, four-eyes, folio). Esta factory sirve para preparar escenarios
 * de prueba (p. ej. un intento `PENDIENTE` previo) sin duplicar lógica.
 *
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    protected $model = Pago::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'concepto_pago_id' => ConceptoPago::factory(),
            'monto' => '1200.00',
            'fecha_pago' => now()->toDateString(),
            'metodo' => MetodoPago::EFECTIVO->value,
            'referencia' => null,
            'comprobante_ruta' => null,
            'comprobante_nombre_original' => null,
            'estatus' => EstatusPago::PENDIENTE->value,
            'folio' => null,
            'validado_por' => null,
            'validado_at' => null,
            'motivo_rechazo' => null,
            'registrado_por' => User::factory(),
            'notas' => null,
        ];
    }

    /** Intento rechazado (historial que jamás se elimina). */
    public function rechazado(string $motivo = 'Motivo sintetico de rechazo'): static
    {
        return $this->state([
            'estatus' => EstatusPago::RECHAZADO->value,
            'motivo_rechazo' => $motivo,
        ]);
    }
}
