<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos', function (Blueprint $table): void {
            $table->id();
            $table->string('clave', 20)->unique();
            $table->string('nombre', 100);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('activo')->default(true);
            // cerrado_at bloquea INSCRIPCIONES (RB-13); NO es un cierre académico
            // global de calificaciones/asistencia/grupos (futuro, ver roadmap R2).
            $table->timestamp('cerrado_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos');
    }
};
