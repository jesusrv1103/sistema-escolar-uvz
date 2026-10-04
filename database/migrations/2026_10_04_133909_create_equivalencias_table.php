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
        Schema::create('equivalencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')
                ->constrained('alumnos')
                ->restrictOnDelete();
            $table->foreignId('materia_id')
                ->nullable()
                ->constrained('materias')
                ->nullOnDelete();
            $table->string('origen', 500); // Institución, país, programa, etc.
            $table->unsignedTinyInteger('creditos'); // 0-20
            $table->text('dictamen')->nullable(); // Fundamento técnico, normativa
            $table->string('estatus', 20); // EstatusEquivalencia: PENDIENTE, APROBADA, RECHAZADA
            $table->foreignId('autorizada_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->date('fecha_dictamen')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['alumno_id', 'estatus']);
            $table->index('materia_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equivalencias');
    }
};