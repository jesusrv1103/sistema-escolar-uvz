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
        Schema::create('becas_alumnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beca_id')
                ->constrained('becas')
                ->restrictOnDelete();
            $table->foreignId('alumno_id')
                ->constrained('alumnos')
                ->restrictOnDelete();
            $table->foreignId('periodo_id')
                ->constrained('periodos')
                ->restrictOnDelete();
            $table->string('estatus', 20); // EstatusBeca: PENDIENTE, APROBADA, RECHAZADA
            $table->foreignId('asignada_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('aprobada_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['beca_id', 'alumno_id', 'periodo_id']);
            $table->index(['alumno_id', 'periodo_id', 'estatus']);
            $table->index('aprobada_por');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('becas_alumnos');
    }
};