<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Secuenciales institucionales (§4.15, P2 aprobado): controla los
        // números de control ante concurrencia. Una fila por (tipo, año) con
        // `UNIQUE` como defensa; NO se calcula con MAX(numero_control) (RB-9).
        // `anio` = año de `periodos.fecha_inicio` del periodo de la inscripción
        // (decisión Fase 12, RB-9/P2). Sin soft delete: infraestructura viva.
        Schema::create('consecutivos', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo', 30);                    // TipoConsecutivo (BackedEnum)
            $table->smallInteger('anio');                  // año del periodo de ingreso
            $table->unsignedInteger('ultimo_valor')->default(0);   // último valor emitido
            $table->timestamps();
            $table->unique(['tipo', 'anio']);              // una fila por tipo y año
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consecutivos');
    }
};
