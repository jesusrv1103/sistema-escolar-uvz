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
        Schema::create('materias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_version_id')
                ->constrained('plan_versiones')
                ->restrictOnDelete();
            $table->string('clave', 20);
            $table->string('nombre', 150);
            $table->unsignedSmallInteger('creditos');
            $table->unsignedSmallInteger('horas_teoricas')->nullable();
            $table->unsignedSmallInteger('horas_practicas')->nullable();
            $table->unsignedSmallInteger('periodo_orden');
            $table->string('bloque', 30)->nullable();
            $table->boolean('obligatoria')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plan_version_id', 'clave']);
            $table->index('periodo_orden');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materias');
    }
};