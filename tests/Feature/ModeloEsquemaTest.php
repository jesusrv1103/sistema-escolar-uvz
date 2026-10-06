<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class)->group('modelos');

/*
|--------------------------------------------------------------------------
| Regresión WAVE UI/UX 2.0-12 — modelo contra esquema
|--------------------------------------------------------------------------
|
| `Asistencia` usaba `SoftDeletes` pero su migración no define `deleted_at`:
| el trait mete `asistencias`.`deleted_at is null` en TODA consulta, así que
| cada lectura de asistencias (cargar lista, guardar, servicios) moría con
|
|     SQLSTATE[42S22]: Unknown column 'asistencias.deleted_at'
|
| Ninguna prueba del proyecto consultaba la tabla — sólo se reflejaba sobre la
| clase —, y la revisión visual nunca llegaba a pulsar "Cargar lista", así que
| el defecto seguía oculto. La corrección es al modelo: añadir la columna
| exige migración nueva, que requiere aprobación.
|
*/

test('ningun modelo usa soft deletes sin la columna deleted_at en su tabla', function (): void {
    $violaciones = [];

    foreach (glob(app_path('Models/*.php')) ?: [] as $archivo) {
        $fqcn = 'App\\Models\\' . basename($archivo, '.php');

        if (! class_exists($fqcn)) {
            continue;
        }

        $reflexion = new ReflectionClass($fqcn);

        if (! $reflexion->isSubclassOf(Model::class)) {
            continue;
        }

        $modelo = new $fqcn;

        if (! in_array(SoftDeletes::class, class_uses_recursive($fqcn), true)) {
            continue;
        }

        $tabla = $modelo->getTable();

        if (! Schema::hasTable($tabla)) {
            $violaciones[] = sprintf('%s → tabla inexistente %s', $fqcn, $tabla);

            continue;
        }

        if (! Schema::hasColumn($tabla, 'deleted_at')) {
            $violaciones[] = sprintf('%s usa SoftDeletes pero %s no tiene deleted_at', $fqcn, $tabla);
        }
    }

    expect($violaciones)->toBe([]);
});
