<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convenios', function (Blueprint $table): void {
            $table->id();
            $table->string('clave', 20)->unique();
            $table->string('nombre', 150);
            $table->string('tipo', 15);                 // PORCENTAJE | MONTO (BackedEnum)
            $table->decimal('valor', 10, 2);
            $table->date('fecha_inicio')->nullable();   // vigencia
            $table->date('fecha_fin')->nullable();      // vigencia
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();
            // La aprobación de un convenio vive en inscripciones (§4.8), no aquí.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenios');
    }
};
