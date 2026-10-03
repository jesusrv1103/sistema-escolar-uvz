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
        Schema::create('plan_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')
                ->constrained('planes_estudio')
                ->restrictOnDelete();
            $table->string('version', 20);
            $table->date('fecha_vigencia')->nullable();
            $table->string('estatus', 20)->default('PROPUESTA'); // PROPUESTA, VIGENTE, SUPERADA
            $table->boolean('activo')->default(true);
            $table->foreignId('aprobado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('aprobado_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plan_id', 'version']);
            $table->index('estatus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_versiones');
    }
};