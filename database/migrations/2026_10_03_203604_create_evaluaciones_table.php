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
        Schema::create('evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')
                ->constrained('grupos')
                ->restrictOnDelete();
            $table->string('nombre', 100);
            $table->enum('tipo', array_column(\App\Enums\TipoEvaluacion::cases(), 'value'));
            $table->unsignedTinyInteger('ponderacion'); // 1-100
            $table->date('fecha');
            $table->string('estatus', 20)->default('BORRADOR'); // BORRADOR, PUBLICADA, CERRADA (sin máquina de estados formal)
            $table->text('descripcion')->nullable();
            $table->foreignId('creada_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['grupo_id', 'nombre']);
            $table->index(['grupo_id', 'estatus']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluaciones');
    }
};