<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscripciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->restrictOnDelete();
            $table->foreignId('periodo_id')->constrained('periodos')->restrictOnDelete();
            $table->foreignId('programa_id')->constrained('programas')->restrictOnDelete();
            $table->string('estatus', 30)->default('DRAFT');   // BackedEnum InscripcionEstatus (§7)
            $table->decimal('monto_inscripcion', 10, 2);        // snapshot D8
            $table->decimal('monto_mensualidad', 10, 2);        // snapshot D8

            // Convenio (sub-proceso con aprobación, D6/RB-17): nunca es estado de la inscripción.
            $table->foreignId('convenio_id')->nullable()->constrained('convenios')->restrictOnDelete();
            $table->string('convenio_estatus', 25)->nullable(); // EstatusConvenio
            $table->foreignId('convenio_aplicado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('convenio_aplicado_at')->nullable();
            $table->foreignId('convenio_aprobado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('convenio_aprobado_at')->nullable();
            $table->text('convenio_motivo')->nullable();
            $table->decimal('monto_descuento', 10, 2)->default(0);

            // Hitos
            $table->timestamp('datos_completados_at')->nullable();
            $table->timestamp('pagos_validados_at')->nullable();
            $table->timestamp('documentos_validados_at')->nullable();
            $table->timestamp('finalizada_at')->nullable();

            // Responsables
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('validada_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelada_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelada_at')->nullable();
            $table->text('cancelacion_motivo')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // D2 aprobado: imposible tener 2 inscripciones del mismo alumno en el mismo periodo.
            $table->unique(['alumno_id', 'periodo_id']);
            $table->index('estatus');
            $table->index(['periodo_id', 'estatus']);
            $table->index('convenio_estatus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
