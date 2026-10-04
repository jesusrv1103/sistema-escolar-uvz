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
        Schema::create('matriculas_periodo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')
                ->constrained('alumnos')
                ->restrictOnDelete();
            $table->foreignId('periodo_id')
                ->constrained('periodos')
                ->restrictOnDelete();
            $table->foreignId('programa_id')
                ->constrained('programas')
                ->restrictOnDelete();
            $table->foreignId('plan_version_id')
                ->nullable()
                ->constrained('plan_versiones')
                ->nullOnDelete();
            $table->enum('origen', ['PRIMERA_VEZ', 'REINGRESO', 'CAMBIO_PROGRAMA', 'TRASLADO_EXTERNO'])
                ->default('PRIMERA_VEZ');
            $table->date('fecha_matricula')->useCurrent();
            $table->enum('estatus', ['ACTIVA', 'BAJA_TEMPORAL', 'BAJA_DEFINITIVA', 'EGRESADO'])
                ->default('ACTIVA');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['alumno_id', 'periodo_id']);
            $table->index(['periodo_id', 'programa_id', 'estatus']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matriculas_periodo');
    }
};