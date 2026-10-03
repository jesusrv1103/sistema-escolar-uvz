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
        Schema::create('materia_prerrequisitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('materia_id')
                ->constrained('materias')
                ->cascadeOnDelete();
            $table->foreignId('prerrequisito_id')
                ->constrained('materias')
                ->restrictOnDelete();
            $table->string('tipo', 20); // OBLIGATORIO, RECOMENDADO
            $table->timestamps();

            $table->unique(['materia_id', 'prerrequisito_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materia_prerrequisitos');
    }
};