<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programas', function (Blueprint $table): void {
            $table->id();
            $table->string('clave', 20)->unique();          // clave INTERNA del sistema (no es RVOE)
            $table->string('nombre', 150);
            $table->unsignedSmallInteger('duracion_periodos')->nullable(); // total en periodos académicos; se captura desde el admin
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programas');
    }
};
