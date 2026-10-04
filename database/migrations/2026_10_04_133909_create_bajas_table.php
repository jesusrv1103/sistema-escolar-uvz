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
        Schema::create('bajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')
                ->constrained('alumnos')
                ->restrictOnDelete();
            $table->foreignId('inscripcion_materia_id')
                ->nullable()
                ->constrained('inscripciones_materias')
                ->nullOnDelete();
            $table->string('tipo', 20); // TipoBaja: TEMPORAL, DEFINITIVA, MATERIA
            $table->text('motivo');
            $table->date('fecha');
            $table->timestamp('reingreso_at')->nullable();
            $table->foreignId('registrado_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['alumno_id', 'fecha']);
            $table->index('inscripcion_materia_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bajas');
    }
};