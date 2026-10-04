<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ajustes_financieros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')
                ->constrained('alumnos')
                ->restrictOnDelete();
            $table->foreignId('concepto_pago_id')
                ->constrained('conceptos_pago')
                ->restrictOnDelete();
            $table->foreignId('beca_alumno_id')
                ->nullable()
                ->constrained('becas_alumnos')
                ->nullOnDelete();
            $table->string('tipo', 30); // TipoAjusteFinanciero: DESCUENTO_BECA, REVERSO_DESCUENTO_BECA
            $table->decimal('monto', 10, 2);
            $table->foreignId('ajuste_origen_id')
                ->nullable()
                ->constrained('ajustes_financieros')
                ->nullOnDelete();
            $table->foreignId('registrado_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('registrado_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['alumno_id', 'concepto_pago_id']);
            $table->index(['beca_alumno_id', 'tipo']);
            $table->index('ajuste_origen_id');
            $table->index('registrado_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ajustes_financieros');
    }
};