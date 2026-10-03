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
        Schema::create('grupo_horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')
                ->constrained('grupos')
                ->cascadeOnDelete();
            $table->string('dia', 20); // LUNES, MARTES, MIERCOLES, JUEVES, VIERNES, SABADO
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();

            $table->unique(['grupo_id', 'dia', 'hora_inicio']);
            $table->index(['grupo_id', 'dia']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupo_horarios');
    }
};