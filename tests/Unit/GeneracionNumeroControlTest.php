<?php

use App\Enums\InscripcionEstatus;
use App\Enums\TipoConsecutivo;
use App\Exceptions\NumeroControlException;
use App\Models\Alumno;
use App\Models\Consecutivo;
use App\Models\Inscripcion;
use App\Models\Periodo;
use App\Services\NumeroControlService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Fase 12 — U7/U8 + concurrencia del número de control (RB-9, P2)
|--------------------------------------------------------------------------
| Corren EXCLUSIVAMENTE sobre `sistema_escolar_testing` (RefreshDatabase).
|
| LIMITACIÓN DEL RUNNER (documentada, sin falsear concurrencia): cada prueba
| corre DENTRO de una transacción envolvente (RefreshDatabase ⇒
| DatabaseTransactions en MySQL). Una segunda conexión real no vería los
| datos sin confirmar de la primera, por lo que una carrera paralela de dos
| procesos no es estable aquí. En lugar de simularla (lo cual sería falso),
| se prueba lo REAL que sostiene el P2:
|   1) `UNIQUE (tipo, anio)` de MySQL rechaza la fila duplicada;
|   2) el SELECT con `lockForUpdate()` emite FOR UPDATE real (capturado con
|      DB::listen sobre la conexión que ejecuta la asignación);
|   3) la inicialización de la fila ausente usa insert-or-ignore y una fila
|      ya inicializada por "otro proceso" se reutiliza sin duplicarla;
|   4) asignaciones secuenciales reales no pierden incrementos.
*/

/*|--------------------------------------------------------------------------
| Helpers U-F12
|--------------------------------------------------------------------------*/

/** Inscripción sintética en FINAL_REVIEW para invocar el servicio de NC. */
function inscripcionNCF12(array $opciones = []): Inscripcion
{
    $opciones += ['periodo' => null, 'alumno' => null];

    $factory = Inscripcion::factory()->enEstatus(InscripcionEstatus::FINAL_REVIEW);
    if ($opciones['periodo'] !== null) {
        $factory = $factory->enPeriodo($opciones['periodo']);
    }
    if ($opciones['alumno'] !== null) {
        $factory = $factory->state(['alumno_id' => $opciones['alumno']->getKey()]);
    }

    return $factory->create();
}

/** Asigna dentro de una transacción exterior, igual que `finalizar()` hace. */
function asignarNCF12(Inscripcion $inscripcion): string
{
    return DB::transaction(fn (): string => app(NumeroControlService::class)->asignar(
        $inscripcion->alumno,
        $inscripcion,
    ));
}

/*|--------------------------------------------------------------------------
| U7 — Formato YYYY####
|--------------------------------------------------------------------------*/

test('U7: el numero de control tiene formato YYYY#### de exactamente 8 digitos', function () {
    $inscripcion = inscripcionNCF12();
    $numero = asignarNCF12($inscripcion);

    expect($numero)->toMatch('/^\d{8}$/')
        ->and(substr($numero, 0, 4))->toBe('2026')
        ->and(substr($numero, 4))->toBe('0001')
        ->and($inscripcion->alumno->refresh()->numero_control)->toBe($numero);
});

test('U7: la secuencia anual arranca en 0001 y avanza sin perdidas (0001, 0002, 0003)', function () {
    $numeros = [
        asignarNCF12(inscripcionNCF12()),
        asignarNCF12(inscripcionNCF12()),
        asignarNCF12(inscripcionNCF12()),
    ];

    expect($numeros)->toBe(['20260001', '20260002', '20260003'])
        ->and(Consecutivo::query()->count())->toBe(1)
        ->and(Consecutivo::firstOrFail()->ultimo_valor)->toBe(3);
});

test('U7: el anio sale de periodo.fecha_inicio aunque el anio en curso sea distinto', function () {
    $periodo = Periodo::create([
        'clave' => 'NC-2027',
        'nombre' => 'Enero 2027 (numero de control)',
        'fecha_inicio' => '2027-01-11',
        'fecha_fin' => '2027-05-01',
        'activo' => true,
        'cerrado_at' => null,
    ]);

    $numero = asignarNCF12(inscripcionNCF12(['periodo' => $periodo]));

    expect($numero)->toBe('20270001')
        ->and(Consecutivo::query()->where('anio', 2027)->firstOrFail()->ultimo_valor)->toBe(1)
        ->and(Consecutivo::query()->where('anio', 2026)->exists())->toBeFalse();
});

/*|--------------------------------------------------------------------------
| U8 — Unicidad, conservación y límite
|--------------------------------------------------------------------------*/

test('U8: alumnos distintos reciben numeros distintos y la secuencia es unica', function () {
    $primero = asignarNCF12(inscripcionNCF12());
    $segundo = asignarNCF12(inscripcionNCF12());

    expect($primero)->not->toBe($segundo)
        ->and([$primero, $segundo])->toBe(['20260001', '20260002'])
        ->and(Consecutivo::firstOrFail()->ultimo_valor)->toBe(2);
});

test('U8: el mismo alumno devuelve siempre su numero sin incrementar el consecutivo', function () {
    $inscripcion = inscripcionNCF12();

    $primero = asignarNCF12($inscripcion);
    $segundo = asignarNCF12($inscripcion);   // segunda invocación: idempotente

    expect($segundo)->toBe($primero)
        ->and(Consecutivo::firstOrFail()->ultimo_valor)->toBe(1);
});

test('U8: alumnos.numero_control UNIQUE es la ultima defensa contra duplicados', function () {
    $alumnoA = Alumno::factory()->create();
    $alumnoB = Alumno::factory()->create();

    $alumnoA->forceFill(['numero_control' => '20260001'])->save();

    expect(fn () => $alumnoB->forceFill(['numero_control' => '20260001'])->save())
        ->toThrow(QueryException::class);
    expect(Alumno::query()->whereNotNull('numero_control')->count())->toBe(1);
});

test('U8: agotar la secuencia en 9999 se rechaza explicitamente sin comportamiento silencioso', function () {
    $inscripcion = inscripcionNCF12();
    Consecutivo::create([
        'tipo' => TipoConsecutivo::NUMERO_CONTROL->value,
        'anio' => 2026,
        'ultimo_valor' => 9999,
    ]);

    expect(fn () => asignarNCF12($inscripcion))
        ->toThrow(NumeroControlException::class, 'agotada');

    expect($inscripcion->alumno->refresh()->numero_control)->toBeNull()
        ->and(Consecutivo::firstOrFail()->ultimo_valor)->toBe(9999);
});

test('U8: la secuencia no se deriva de MAX(numero_control) ni de los IDs', function () {
    $inscripcion = inscripcionNCF12();

    // Un NC alto "insertado a mano" no altera la secuencia: el consecutivo
    // manda, no MAX() sobre alumnos ni el último ID.
    Alumno::factory()->create()->forceFill(['numero_control' => '20269999'])->save();

    expect(asignarNCF12($inscripcion))->toBe('20260001');
});

/*|--------------------------------------------------------------------------
| Concurrencia P2 — defensas reales de BD
|--------------------------------------------------------------------------*/

test('concurrencia: UNIQUE(tipo, anio) impide duplicar la fila del anio', function () {
    Consecutivo::create([
        'tipo' => TipoConsecutivo::NUMERO_CONTROL->value,
        'anio' => 2026,
        'ultimo_valor' => 0,
    ]);

    expect(fn () => Consecutivo::create([
        'tipo' => TipoConsecutivo::NUMERO_CONTROL->value,
        'anio' => 2026,
        'ultimo_valor' => 0,
    ]))->toThrow(QueryException::class);

    expect(Consecutivo::query()->count())->toBe(1);
});

test('concurrencia: la asignacion bloquea la fila del anio con FOR UPDATE real', function () {
    $inscripcion = inscripcionNCF12();

    $consultas = [];
    DB::listen(function ($evento) use (&$consultas): void {
        $consultas[] = strtolower($evento->sql);
    });

    expect(asignarNCF12($inscripcion))->toBe('20260001');

    $bloqueos = array_values(array_filter(
        $consultas,
        fn (string $sql): bool => str_contains($sql, 'consecutivos') && str_contains($sql, 'for update'),
    ));
    expect($bloqueos)->not->toBeEmpty();
});

test('concurrencia: la fila ausente se crea una sola vez y una fila ajena se reutiliza', function () {
    // a) No existe: insert-or-ignore + UNIQUE la crea antes del lock.
    expect(asignarNCF12(inscripcionNCF12()))->toBe('20260001')
        ->and(Consecutivo::query()->count())->toBe(1);

    // b) "Otro proceso" ya inicializó el año con 50: se continúa desde 51
    //    sobre la MISMA fila (nunca `first()->ultimo_valor + 1` duplicado).
    Consecutivo::query()->where('anio', 2026)->update(['ultimo_valor' => 50]);

    expect(asignarNCF12(inscripcionNCF12()))->toBe('20260051')
        ->and(Consecutivo::query()->count())->toBe(1)
        ->and(Consecutivo::firstOrFail()->ultimo_valor)->toBe(51);
});
