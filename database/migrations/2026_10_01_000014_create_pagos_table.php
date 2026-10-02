<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pagos (§4.10): registro, validación, folio y comprobante cargado.
        // Registros financieros: SIN soft delete (riesgo R15). `folio` es
        // UNIQUE en BD (defensa final): la unicidad lógica la garantiza el
        // ID del pago (Fase 8, decisión aprobada).
        Schema::create('pagos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('concepto_pago_id')->constrained('conceptos_pago')->restrictOnDelete();
            $table->decimal('monto', 10, 2);            // un pago completo por concepto (D5): sin parciales
            $table->date('fecha_pago');                 // fecha del hecho, no del sistema
            $table->string('metodo', 20);               // MetodoPago: EFECTIVO | TRANSFERENCIA | TARJETA | OTRO
            $table->string('referencia', 100)->nullable();  // nº de operación/transferencia
            $table->string('comprobante_ruta', 255)->nullable();       // ruta interna, disco privado
            $table->string('comprobante_nombre_original', 255)->nullable();
            $table->string('estatus', 20)->default('PENDIENTE');       // EstatusPago
            $table->string('folio', 30)->nullable()->unique();         // PAG-YYYYMMDD-########, solo al validar
            $table->foreignId('validado_por')->nullable()->constrained('users');
            $table->timestamp('validado_at')->nullable();
            $table->text('motivo_rechazo')->nullable();    // obligatorio si RECHAZADO
            $table->foreignId('registrado_por')->constrained('users');
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
