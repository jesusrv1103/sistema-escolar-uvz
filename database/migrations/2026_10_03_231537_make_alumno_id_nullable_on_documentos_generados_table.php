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
        Schema::table('documentos_generados', function (Blueprint $table) {
            $table->dropForeign('documentos_generados_alumno_id_foreign');
            $table->foreignId('alumno_id')
                ->nullable()
                ->change()
                ->constrained('alumnos')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos_generados', function (Blueprint $table) {
            $table->dropForeign('documentos_generados_alumno_id_foreign');
            $table->foreignId('alumno_id')
                ->nullable(false)
                ->change()
                ->constrained('alumnos')
                ->restrictOnDelete();
        });
    }
};