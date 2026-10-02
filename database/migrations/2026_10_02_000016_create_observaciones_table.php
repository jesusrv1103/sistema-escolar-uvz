<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Observaciones / faltantes (§4.13): APPEND-ONLY — se crean y se
        // RESUELVEN (RB-15): sin soft delete y sin borrado; una observación
        // incorrecta se conserva como histórico y puede resolverse.
        // `estatus` nace ABIERTA; `resuelta_*` solo se llenan al resolver
        // (§12.9). Solo FALTANTE + ABIERTA bloquea la finalización (RB-8).
        Schema::create('observaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inscripcion_id')->constrained('inscripciones')->cascadeOnDelete();
            $table->string('tipo', 20);                              // TipoObservacion (BackedEnum)
            $table->text('detalle');                                 // inmutable tras crear
            $table->string('estatus', 20)->default('ABIERTA');       // EstatusObservacion
            $table->foreignId('creada_por')->constrained('users');   // usuario autenticado server-side
            $table->foreignId('resuelta_por')->nullable()->constrained('users');
            $table->timestamp('resuelta_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observaciones');
    }
};
