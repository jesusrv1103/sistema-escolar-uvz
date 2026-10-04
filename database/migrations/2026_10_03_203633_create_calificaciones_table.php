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
        Schema::create('calificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_materia_id')
                ->constrained('inscripciones_materias')
                ->restrictOnDelete();
            $table->foreignId('evaluacion_id')
                ->constrained('evaluaciones')
                ->restrictOnDelete();
            $table->decimal('valor', 5, 2); // 0.00 - 100.00
            $table->foreignId('capturada_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('capturada_at')->useCurrent();
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['inscripcion_materia_id', 'evaluacion_id']);
            $table->index(['inscripcion_materia_id', 'evaluacion_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calificaciones');
    }
};