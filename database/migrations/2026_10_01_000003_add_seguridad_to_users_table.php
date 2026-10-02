<?php

use App\Enums\RolUsuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 — extensión incremental de `users` (no reemplaza la migración original ya ejecutada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('rol', 30)->nullable()->after('password');
            $table->boolean('activo')->default(true)->after('rol');
            $table->timestamp('last_login_at')->nullable()->after('activo');
            $table->softDeletes()->after('last_login_at');
        });

        // Usuarios existentes (si los hubiera) quedan como ADMIN: es el rol de arranque del MVP.
        DB::table('users')->whereNull('rol')->update(['rol' => RolUsuario::ADMIN->value]);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('rol', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['rol', 'activo', 'last_login_at']);
        });
    }
};
