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
        Schema::create('becas', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 20)->unique();
            $table->string('nombre', 150);
            $table->string('tipo', 20); // TipoBeca: PORCENTAJE, MONTO
            $table->decimal('valor', 10, 2); // % (0-100) o monto absoluto
            $table->string('alcance', 20); // AlcanceBeca: INSCRIPCION, MENSUALIDADES, AMBOS
            $table->foreignId('periodo_id')
                ->constrained('periodos')
                ->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['periodo_id', 'activo']);
            $table->index('clave');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('becas');
    }
};