<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Documentos del expediente (§4.12): un registro POR TIPO por
        // inscripción (UNIQUE) — la recarga REEMPLAZA el archivo de esa misma
        // fila y la trazabilidad vive en `auditoria` (interpretación aprobada
        // RB-7/§12.8). Sin soft delete (supuesto 17): R15 no aplica a
        // expedientes, pero el histórico no se borra.
        Schema::create('documentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inscripcion_id')->constrained('inscripciones')->cascadeOnDelete();
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->restrictOnDelete();
            $table->string('ruta', 255);                 // ruta interna generada por el sistema (disco privado)
            $table->string('nombre_original', 255);      // SOLO metadato, nunca se usa como path
            $table->string('mime', 100);                 // MIME real verificado con finfo al subir
            $table->unsignedInteger('tamano_kb');        // máx. 10 MB = 10240 KB
            $table->string('estatus', 20)->default('PENDIENTE');   // EstatusDocumento
            $table->foreignId('validado_por')->nullable()->constrained('users');
            $table->timestamp('validado_at')->nullable();
            $table->text('motivo_rechazo')->nullable();  // obligatorio si RECHAZADO
            $table->foreignId('subido_por')->constrained('users');
            $table->timestamps();

            // Un solo registro por tipo por inscripción: se reemplaza el archivo.
            $table->unique(['inscripcion_id', 'tipo_documento_id'], 'documentos_inscripcion_tipo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
