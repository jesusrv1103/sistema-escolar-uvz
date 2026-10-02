<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumnos', function (Blueprint $table): void {
            $table->id();
            // Atributo del alumno (D3): formato YYYY####; se asigna en la Fase 12
            // (transacción final), NO en esta fase. Único como última línea de defensa.
            $table->char('numero_control', 8)->unique()->nullable();
            $table->string('nombre', 100);
            $table->string('apellidos', 100);
            $table->char('curp', 18)->unique();     // sintaxis en app + unicidad local
            $table->date('fecha_nacimiento');
            $table->string('sexo', 10);             // BackedEnum Sexo (§4.2)
            $table->string('nacionalidad', 60)->default('Mexicana');
            $table->string('correo', 150)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('calle', 150)->nullable();
            $table->string('colonia', 120)->nullable();
            $table->string('cp', 10)->nullable();
            $table->string('municipio', 120)->nullable();
            $table->string('estado', 120)->nullable();
            $table->string('pais', 80)->default('México');
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};
