<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2.0-16 — Portal de alumno.
 *
 * El portal nunca recibe `{alumno}` en la ruta: la identidad sale de
 * `auth()->user()->alumno`. Esa relación no existía (hoy no hay ninguna forma
 * de vincular una cuenta ALUMNO con su expediente), así que se agrega la
 * columna nullable `alumnos.user_id`.
 *
 * - Nullable: sólo las cuentas ALUMNO activas quedan vinculadas; el resto de
 *   roles y los alumnos sin cuenta conservan `NULL`.
 * - UNIQUE: una cuenta por alumno y un alumno por cuenta (evita que dos
 *   cuentas lean el mismo expediente).
 * - `restrictOnDelete`: no se borra un usuario vinculado sin desvincular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumnos', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->unique();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alumnos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
