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
        Schema::create('documentos_generados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')
                ->constrained('alumnos')
                ->restrictOnDelete();
            $table->foreignId('matricula_periodo_id')
                ->nullable()
                ->constrained('matriculas_periodo')
                ->nullOnDelete();
            $table->foreignId('grupo_id')
                ->nullable()
                ->constrained('grupos')
                ->nullOnDelete();
            $table->string('tipo', 50); // TipoFormato value
            $table->string('folio', 50)->nullable()->unique();
            $table->foreignId('emitido_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('emitido_at')->useCurrent();
            $table->json('snapshot_datos')->nullable(); // Snapshot de datos usados
            $table->timestamps();
            $table->softDeletes();

            $table->index(['alumno_id', 'tipo']);
            $table->index(['matricula_periodo_id', 'tipo']);
            $table->index(['grupo_id', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos_generados');
    }
};