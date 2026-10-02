<?php

use App\Enums\InscripcionEstatus;
use App\Exceptions\MotivoRequeridoException;
use App\Exceptions\TransicionInvalidaException;
use App\Models\Inscripcion;
use App\Models\User;
use App\Services\InscripcionService;

/*
|--------------------------------------------------------------------------
| U1–U3 — Máquina de estados (§7.2/§7.3)
|--------------------------------------------------------------------------
| Fuente única: InscripcionEstatus::TRANSICIONES. Sin debilitar la matriz.
*/

test('U1: la matriz acepta todas las transiciones permitidas', function () {
    $servicio = app(InscripcionService::class);

    // §7.2 completo: 11 aristas de avance + CANCELLED desde todo estado no final.
    $permitidas = [
        ['DRAFT', 'DATA_COMPLETE'], ['DRAFT', 'CANCELLED'],
        ['DATA_COMPLETE', 'PENDING_PAYMENT'], ['DATA_COMPLETE', 'CANCELLED'],
        ['PENDING_PAYMENT', 'PAYMENT_REVIEW'], ['PENDING_PAYMENT', 'CANCELLED'],
        ['PAYMENT_REVIEW', 'PAYMENT_VALIDATED'], ['PAYMENT_REVIEW', 'PENDING_PAYMENT'], ['PAYMENT_REVIEW', 'CANCELLED'],
        ['PAYMENT_VALIDATED', 'PENDING_DOCUMENTS'], ['PAYMENT_VALIDATED', 'CANCELLED'],
        ['PENDING_DOCUMENTS', 'DOCUMENT_REVIEW'], ['PENDING_DOCUMENTS', 'CANCELLED'],
        ['DOCUMENT_REVIEW', 'FINAL_REVIEW'], ['DOCUMENT_REVIEW', 'PENDING_DOCUMENTS'], ['DOCUMENT_REVIEW', 'CANCELLED'],
        ['FINAL_REVIEW', 'ENROLLED'], ['FINAL_REVIEW', 'PENDING_DOCUMENTS'], ['FINAL_REVIEW', 'CANCELLED'],
    ];

    foreach ($permitidas as [$desde, $hasta]) {
        $aceptada = $servicio->puedeTransicionar(
            InscripcionEstatus::from($desde),
            InscripcionEstatus::from($hasta),
        );
        expect("{$desde}→{$hasta}: ".($aceptada ? 'ok' : 'RECHAZADA'))->toContain('ok');
    }

    // Y la ejecución real completa el recorrido entero a ENROLLED.
    $usuario = User::factory()->create();
    $inscripcion = Inscripcion::factory()->create();
    $recorrido = [
        [InscripcionEstatus::DATA_COMPLETE, null],
        [InscripcionEstatus::PENDING_PAYMENT, null],
        [InscripcionEstatus::PAYMENT_REVIEW, null],
        [InscripcionEstatus::PENDING_PAYMENT, 'Pago de inscripcion rechazado (sintetico)'],
        [InscripcionEstatus::PAYMENT_REVIEW, null],
        [InscripcionEstatus::PAYMENT_VALIDATED, null],
        [InscripcionEstatus::PENDING_DOCUMENTS, null],
        [InscripcionEstatus::DOCUMENT_REVIEW, null],
        [InscripcionEstatus::FINAL_REVIEW, null],
        [InscripcionEstatus::PENDING_DOCUMENTS, 'Documentos rechazados (sintetico)'],
        [InscripcionEstatus::DOCUMENT_REVIEW, null],
        [InscripcionEstatus::FINAL_REVIEW, null],
        [InscripcionEstatus::ENROLLED, null],
    ];

    foreach ($recorrido as [$hasta, $motivo]) {
        $servicio->transicionar($inscripcion, $hasta, $motivo, $usuario);
        expect($inscripcion->refresh()->estatus)->toBe($hasta);
    }

    expect($inscripcion->finalizada_at)->not->toBeNull()
        ->and($inscripcion->validada_por)->toBe($usuario->id);
});

test('U2: las transiciones prohibidas son rechazadas y no dejan rastro', function () {
    $usuario = User::factory()->create();
    $inscripcion = Inscripcion::factory()->create();   // DRAFT
    $servicio = app(InscripcionService::class);

    $prohibidas = [
        [InscripcionEstatus::DRAFT, InscripcionEstatus::ENROLLED],
        [InscripcionEstatus::DRAFT, InscripcionEstatus::PENDING_PAYMENT],
        [InscripcionEstatus::DATA_COMPLETE, InscripcionEstatus::FINAL_REVIEW],
        [InscripcionEstatus::PENDING_PAYMENT, InscripcionEstatus::PAYMENT_VALIDATED],
        [InscripcionEstatus::PAYMENT_VALIDATED, InscripcionEstatus::ENROLLED],
        [InscripcionEstatus::PAYMENT_REVIEW, InscripcionEstatus::FINAL_REVIEW],
    ];

    foreach ($prohibidas as [$desde, $hasta]) {
        expect($servicio->puedeTransicionar($desde, $hasta))->toBeFalse();
    }

    expect(fn () => $servicio->transicionar($inscripcion, InscripcionEstatus::ENROLLED, null, $usuario))
        ->toThrow(TransicionInvalidaException::class);

    expect($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::DRAFT)
        ->and($inscripcion->auditorias()->count())->toBe(0);
});

test('U3: ENROLLED y CANCELLED son terminales sin salida', function () {
    $usuario = User::factory()->create();
    $servicio = app(InscripcionService::class);

    // Llegada legítima a ENROLLED.
    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::FINAL_REVIEW)->create();
    $servicio->transicionar($inscripcion, InscripcionEstatus::ENROLLED, null, $usuario);
    expect($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::ENROLLED);

    foreach ([InscripcionEstatus::CANCELLED, InscripcionEstatus::DRAFT, InscripcionEstatus::FINAL_REVIEW] as $hasta) {
        expect($servicio->puedeTransicionar(InscripcionEstatus::ENROLLED, $hasta))->toBeFalse()
            ->and(fn () => $servicio->transicionar($inscripcion, $hasta, 'Motivo sintetico', $usuario))
            ->toThrow(TransicionInvalidaException::class);
    }

    // CANCELLED tampoco tiene salida.
    $cancelada = Inscripcion::factory()->create();
    $servicio->transicionar($cancelada, InscripcionEstatus::CANCELLED, 'Cancelacion sintetica', $usuario);
    expect($servicio->puedeTransicionar(InscripcionEstatus::CANCELLED, InscripcionEstatus::DRAFT))->toBeFalse()
        ->and(fn () => $servicio->transicionar($cancelada, InscripcionEstatus::DRAFT, null, $usuario))
        ->toThrow(TransicionInvalidaException::class);

    expect($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::ENROLLED)
        ->and($cancelada->refresh()->estatus)->toBe(InscripcionEstatus::CANCELLED);
});

test('U3: los retrocesos y toda cancelacion exigen motivo', function () {
    $usuario = User::factory()->create();
    $servicio = app(InscripcionService::class);

    // Retroceso PAYMENT_REVIEW → PENDING_PAYMENT (§7.2) sin motivo ⇒ rechazo.
    $inscripcion = Inscripcion::factory()->enEstatus(InscripcionEstatus::PAYMENT_REVIEW)->create();

    expect(fn () => $servicio->transicionar($inscripcion, InscripcionEstatus::PENDING_PAYMENT))
        ->toThrow(MotivoRequeridoException::class);

    $servicio->transicionar($inscripcion, InscripcionEstatus::PENDING_PAYMENT, '  Rechazo del pago (sintetico)  ', $usuario);

    expect($inscripcion->refresh()->estatus)->toBe(InscripcionEstatus::PENDING_PAYMENT)
        ->and($inscripcion->auditorias()->count())->toBe(1)
        ->and($inscripcion->auditorias()->first()->datos)->toBe(['motivo' => 'Rechazo del pago (sintetico)']);

    // Cancelación desde DRAFT sin motivo ⇒ rechazo; con motivo ⇒ aceptada.
    $otra = Inscripcion::factory()->create();

    expect(fn () => $servicio->transicionar($otra, InscripcionEstatus::CANCELLED))
        ->toThrow(MotivoRequeridoException::class);

    $servicio->transicionar($otra, InscripcionEstatus::CANCELLED, 'Motivo sintetico', $usuario);

    expect($otra->refresh()->estatus)->toBe(InscripcionEstatus::CANCELLED)
        ->and($otra->cancelacion_motivo)->toBe('Motivo sintetico')
        ->and($otra->cancelada_por)->toBe($usuario->id)
        ->and($otra->cancelada_at)->not->toBeNull();
});
