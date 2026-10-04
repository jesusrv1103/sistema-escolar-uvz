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
        Schema::create('inscripciones_materias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_periodo_id')
                ->constrained('matriculas_periodo')
                ->restrictOnDelete();
            $table->foreignId('grupo_id')
                ->constrained('grupos')
                ->restrictOnDelete();
            $table->foreignId('materia_id')
                ->constrained('materias')
                ->restrictOnDelete();
            $table->enum('tipo_prerrequisito', ['RECOMENDADO', 'OBLIGATORIO'])
                ->nullable();
            $table->boolean('prerrequisito_cumplido')->default(true);
            $table->enum('estatus', ['CURSANDO', 'APROBADA', 'REPROBADA', 'BAJA', 'VALIDADA'])
                ->default('CURSANDO');
            $table->decimal('calificacion_final', 5, 2)->nullable();
            $table->date('fecha_baja')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['matricula_periodo_id', 'grupo_id']);
            $table->index(['matricula_periodo_id', 'estatus']);
            $table->index(['grupo_id', 'estatus']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscripciones_materias');
    }
};