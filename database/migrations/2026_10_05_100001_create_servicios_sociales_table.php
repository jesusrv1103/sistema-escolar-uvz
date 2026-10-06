<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Servicio Social (2.0-13) — un registro por alumno.
     *
     * Sin horas mínimas ni porcentaje mínimo: sólo se registra la actividad,
     * la dependencia, las fechas y un avance 0–100 que NO auto-valida.
     */
    public function up(): void
    {
        Schema::create('servicios_sociales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')
                ->constrained('alumnos')
                ->restrictOnDelete();
            $table->string('actividad', 500);      // Actividad a realizar
            $table->string('dependencia', 500);    // Institución donde se realiza
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->unsignedTinyInteger('avance')->default(0); // 0-100, no auto-valida
            $table->string('estatus', 20)->default('EN_PROCESO'); // EstatusServicioSocial
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
        Schema::dropIfExists('servicios_sociales');
    }
};
