<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prácticas Profesionales (2.0-13) — un registro por alumno.
     *
     * `calificacion` es texto libre: no existe fuente que defina una escala,
     * así que no se infiere ninguna (pendiente institucional).
     */
    public function up(): void
    {
        Schema::create('practicas_profesionales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')
                ->constrained('alumnos')
                ->restrictOnDelete();
            $table->string('empresa', 500);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('asesor', 500)->nullable();
            $table->string('calificacion', 100)->nullable(); // Texto, sin escala inferida
            $table->string('estatus', 20)->default('EN_PROCESO'); // EstatusPracticaProfesional
            $table->foreignId('validado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('validado_at')->nullable();
            $table->timestamps();

            $table->unique('alumno_id'); // Un solo proceso por alumno
            $table->index('estatus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practicas_profesionales');
    }
};
