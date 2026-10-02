<?php

use App\Enums\InscripcionEstatus;
use App\Models\Auditoria;
use App\Models\Inscripcion;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\InscripcionService;

/*
|--------------------------------------------------------------------------
| F21 — Auditoría de cada transición (§7.3.3, §4.14)
|--------------------------------------------------------------------------
*/

test('F21: cada transicion deja un registro completo de auditoria', function () {
    $usuario = User::factory()->create();
    $servicio = app(InscripcionService::class);
    $esperados = 0;

    // Recorre TODA la matriz: por cada arista, inscripción en `desde` y transición.
    foreach (InscripcionEstatus::TRANSICIONES as $desde => $destinos) {
        foreach ($destinos as $hasta) {
            $inscripcion = Inscripcion::factory()
                ->enEstatus(InscripcionEstatus::from($desde))
                ->create();

            $servicio->transicionar(
                $inscripcion,
                InscripcionEstatus::from($hasta),
                'Motivo sintetico de la prueba',
                $usuario,
            );

            $registro = $inscripcion->auditorias()->latest('id')->firstOrFail();
            $esperados++;

            expect($registro->accion)->toBe('estado_cambiado')
                ->and($registro->estado_anterior)->toBe($desde)
                ->and($registro->estado_nuevo)->toBe($hasta)
                ->and($registro->user_id)->toBe($usuario->id)
                ->and($registro->auditable_type)->toBe(Inscripcion::class)
                ->and($registro->auditable_id)->toBe($inscripcion->id)
                ->and($registro->created_at)->not->toBeNull()
                ->and($registro->ip)->not->toBeNull()
                ->and($registro->datos)->toBe(['motivo' => 'Motivo sintetico de la prueba'])
                ->and($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::from($hasta));
        }
    }

    expect(Auditoria::count())->toBe($esperados)      // 19 aristas de la matriz
        ->and($esperados)->toBe(19);
});

test('una transicion invalida no modifica estatus ni genera auditoria falsa', function () {
    $usuario = User::factory()->create();
    $inscripcion = Inscripcion::factory()->create();   // DRAFT
    $servicio = app(InscripcionService::class);

    expect(fn () => $servicio->transicionar($inscripcion, InscripcionEstatus::ENROLLED, null, $usuario))
        ->toThrow(App\Exceptions\TransicionInvalidaException::class);

    expect($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::DRAFT)
        ->and(Auditoria::count())->toBe(0)
        ->and($inscripcion->finalizada_at)->toBeNull();
});

test('si falla la auditoria la transaccion completa se revierte', function () {
    $usuario = User::factory()->create();
    $inscripcion = Inscripcion::factory()->create();   // DRAFT

    $this->mock(AuditoriaService::class, function ($mock): void {
        $mock->shouldReceive('registrar')
            ->once()
            ->andThrow(new RuntimeException('Fallo simulado de auditoria'));
    });

    expect(fn () => app(InscripcionService::class)
        ->transicionar($inscripcion, InscripcionEstatus::DATA_COMPLETE, null, $usuario))
        ->toThrow(RuntimeException::class);

    // Sin transición a medias: ni estatus nuevo, ni hito, ni auditoría.
    expect($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::DRAFT)
        ->and($inscripcion->datos_completados_at)->toBeNull()
        ->and(Auditoria::count())->toBe(0);
});

test('la auditoria es append-only a nivel de aplicacion', function () {
    $usuario = User::factory()->create();
    $inscripcion = Inscripcion::factory()->create();
    app(InscripcionService::class)
        ->transicionar($inscripcion, InscripcionEstatus::DATA_COMPLETE, null, $usuario);

    $registro = Auditoria::firstOrFail();

    expect(fn () => $registro->update(['accion' => 'alterada']))
        ->toThrow(LogicException::class)
        ->and(fn () => $registro->delete())
        ->toThrow(LogicException::class)
        ->and(fn () => $registro->forceDelete())
        ->toThrow(LogicException::class);

    expect(Auditoria::count())->toBe(1)
        ->and($registro->refresh()->accion)->toBe('estado_cambiado');
});

test('la auditoria no persiste datos sensiveles en su payload', function () {
    $inscripcion = Inscripcion::factory()->create();

    app(AuditoriaService::class)->registrar(
        $inscripcion,
        'prueba',
        [],
        [],
        [
            'motivo' => 'Motivo sintetico',
            'password' => 'no-debe-guardarse',
            'token' => 'no-debe-guardarse',
            'alergias' => 'dato medico que no debe guardarse',
            'anidado' => ['remember_token' => 'no-debe-guardarse', 'ok' => 'si'],
        ],
    );

    expect(Auditoria::firstOrFail()->datos)->toBe([
        'motivo' => 'Motivo sintetico',
        'anidado' => ['ok' => 'si'],
    ]);
});
