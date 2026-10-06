<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Titulación interna (2.0-14).
     *
     * - `modalidad`: texto libre (no existe catálogo oficial en el repo: no se
     *   infiere ninguna lista de modalidades).
     * - `folio_interno`: captura manual opcional con unicidad; NO se genera un
     *   folio automático (no se inventa formato TIT-YYYY-*).
     * - Exige egreso registrado (`egreso_id` NOT NULL).
     */
    public function up(): void
    {
        Schema::create('titulaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')
                ->constrained('alumnos')
                ->restrictOnDelete();
            $table->foreignId('egreso_id')
                ->constrained('egresos')
                ->restrictOnDelete();
            $table->string('modalidad', 200)->nullable();
            $table->string('folio_interno', 100)->nullable();
            $table->string('estatus', 20)->default('EN_TRAMITE'); // EstatusTitulacion
            $table->foreignId('registrado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('registrado_at')->nullable();
            $table->timestamps();

            $table->unique('alumno_id');   // Un solo proceso por alumno
            $table->unique('folio_interno'); // Captura manual sin duplicados
            $table->index('estatus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('titulaciones');
    }
};
