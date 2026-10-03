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
        Schema::create('docentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('clave', 20)->unique();
            $table->string('nombre', 150);
            $table->string('apellidos', 150);
            $table->string('curp', 18)->unique();
            $table->string('rfc', 13)->nullable()->unique();
            $table->string('correo', 150)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('especialidad', 150)->nullable();
            $table->string('grado_estudios', 100)->nullable();
            $table->string('cedula_profesional', 50)->nullable();
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
        Schema::dropIfExists('docentes');
    }
};