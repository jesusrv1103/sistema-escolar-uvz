<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('informacion_medicas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alumno_id')->unique()->constrained('alumnos')->cascadeOnDelete(); // 1:1
            $table->string('tipo_sangre', 5)->nullable();   // BackedEnum TipoSangre (RH)
            $table->text('alergias')->nullable();
            $table->text('condiciones')->nullable();
            $table->text('medicamentos')->nullable();
            $table->boolean('tiene_discapacidad')->default(false);
            $table->text('detalle_discapacidad')->nullable();
            $table->foreignId('actualizado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            // Sin soft delete; tabla sensible (RB-18): nunca en listados ni logs.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informacion_medicas');
    }
};
