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
        Schema::create('actas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')
                ->constrained('grupos')
                ->restrictOnDelete();
            $table->foreignId('periodo_id')
                ->constrained('periodos')
                ->restrictOnDelete();
            $table->string('folio', 50)->unique(); // ACT-YYYY-########
            $table->enum('estatus', ['ABIERTA', 'CERRADA'])->default('ABIERTA');
            $table->foreignId('cerrada_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['grupo_id', 'periodo_id']);
            $table->index(['periodo_id', 'estatus']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actas');
    }
};