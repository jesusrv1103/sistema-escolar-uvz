<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cargos generados automáticamente por InscripcionService (§4.9, RB-3).
        // Registros financieros: SIN soft delete (riesgo R15) y SIN UNIQUE
        // compuesto (los genera el servicio en transacción; §4.9).
        Schema::create('conceptos_pago', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inscripcion_id')->constrained('inscripciones')->cascadeOnDelete();
            $table->string('tipo', 20);                  // TipoConcepto: INSCRIPCION | MENSUALIDAD | OTRO
            $table->smallInteger('numero')->nullable();   // mes 1..N de las mensualidades
            $table->string('concepto', 150);              // ej. "Mensualidad 1 - septiembre 2026"
            $table->decimal('monto', 10, 2);
            $table->decimal('descuento', 10, 2)->default(0);  // solo convenio APROBADO, únicamente en INSCRIPCION
            $table->decimal('monto_total', 10, 2);        // max(monto - descuento, 0)
            $table->date('fecha_vencimiento');
            $table->string('estatus', 20)->default('PENDIENTE'); // EstatusConceptoPago
            $table->timestamp('pagado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conceptos_pago');
    }
};
