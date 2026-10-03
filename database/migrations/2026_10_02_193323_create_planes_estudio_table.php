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
        Schema::create('planes_estudio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')
                ->constrained('programas')
                ->restrictOnDelete();
            $table->string('clave', 20);
            $table->string('nombre', 150);
            $table->string('resolucion_rvoe', 100)->nullable();
            $table->date('fecha_resolucion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['programa_id', 'clave']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planes_estudio');
    }
};