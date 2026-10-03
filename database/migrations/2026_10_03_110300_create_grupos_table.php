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
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('materia_id')
                ->constrained('materias')
                ->restrictOnDelete();
            $table->foreignId('periodo_id')
                ->constrained('periodos')
                ->restrictOnDelete();
            $table->foreignId('docente_id')
                ->nullable()
                ->constrained('docentes')
                ->nullOnDelete();
            $table->foreignId('aula_id')
                ->nullable()
                ->constrained('aulas')
                ->nullOnDelete();
            $table->string('clave', 20);
            $table->unsignedSmallInteger('cupo');
            $table->string('turno', 20); // MATUTINO, VESPERTINO, NOCTURNO
            $table->string('estatus', 20); // CREADO, ABIERTO, EN_CURSO, CERRADO, CANCELADO
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['materia_id', 'periodo_id', 'clave']);
            $table->index(['periodo_id', 'estatus']);
            $table->index(['docente_id', 'periodo_id']);
            $table->index(['aula_id', 'periodo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};