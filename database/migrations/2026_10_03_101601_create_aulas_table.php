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
        Schema::create('aulas', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 20)->unique();
            $table->string('nombre', 100);
            $table->string('edificio', 100);
            $table->unsignedSmallInteger('capacidad');
            $table->string('tipo', 30)->nullable(); // TEORICA, PRACTICA, MIXTA, LABORATORIO
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aulas');
    }
};