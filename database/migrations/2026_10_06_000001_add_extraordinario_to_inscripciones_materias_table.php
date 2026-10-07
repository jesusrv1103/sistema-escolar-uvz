<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Habilita `EXTRAORDINARIO` en `inscripciones_materias.estatus`.
     *
     * El enum PHP `EstatusInscripcionMateria::EXTRAORDINARIO` y
     * `ExtraordinarioService` (2.0-07, D-N4) ya existían, pero la columna era
     * un `ENUM` de MySQL que sólo admitía CURSANDO/APROBADA/REPROBADA/BAJA/
     * VALIDADA: `iniciar()` hacía un `UPDATE ... 'EXTRAORDINARIO'` y MySQL
     * lo rechazaba (`SQLSTATE 22001` / «Data truncated for column 'estatus'»),
     * es decir, el servicio no podía completar su transacción.
     *
     * Migración incremental y estrictamente aditativa:
     * - sólo amplía la lista de valores del `ENUM`;
     * - conserva `default('CURSANDO')`, los índices y las filas existentes;
     * - no edita ninguna migración ya ejecutada (§18/§20);
     * - `down()` vuelve a la lista original, como en cualquier rollback.
     */
    public function up(): void
    {
        Schema::table('inscripciones_materias', function (Blueprint $table) {
            $table->enum('estatus', [
                'CURSANDO',
                'APROBADA',
                'REPROBADA',
                'EXTRAORDINARIO',
                'BAJA',
                'VALIDADA',
            ])->default('CURSANDO')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inscripciones_materias', function (Blueprint $table) {
            $table->enum('estatus', [
                'CURSANDO',
                'APROBADA',
                'REPROBADA',
                'BAJA',
                'VALIDADA',
            ])->default('CURSANDO')->change();
        });
    }
};
