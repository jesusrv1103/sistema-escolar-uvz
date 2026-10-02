<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contactos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->string('tipo', 20);            // TUTOR | EMERGENCIA (BackedEnum)
            $table->string('nombre', 150);
            $table->string('parentesco', 60);
            $table->string('telefono', 20);
            $table->string('telefono_alt', 20)->nullable();
            $table->string('correo', 150)->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
            // Sin soft delete: los contactos se gestionan con el alumno (§4.3).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos');
    }
};
