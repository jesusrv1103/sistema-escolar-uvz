<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Egreso académico interno (2.0-14).
     *
     * Una fila existe SÓLO cuando la verificación de requisitos pasó completa:
     * la presencia del registro es el estado "EGRESÓ". No se evalúan
     * requisitos administrativos/documentales (no hay fuente que los defina)
     * ni la deuda (la deuda no es requisito de egreso).
     */
    public function up(): void
    {
        Schema::create('egresos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')
                ->constrained('alumnos')
                ->restrictOnDelete();
            $table->foreignId('plan_version_id')
                ->constrained('plan_versiones')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('creditos_requeridos');
            $table->unsignedSmallInteger('creditos_acumulados');
            $table->json('requisitos'); // Snapshot de la verificación al registrar
            $table->foreignId('registrado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('registrado_at')->nullable();
            $table->timestamps();

            $table->unique('alumno_id'); // Un solo egreso por alumno
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('egresos');
    }
};
