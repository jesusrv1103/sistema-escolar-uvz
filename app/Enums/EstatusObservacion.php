<?php

namespace App\Enums;

/**
 * Estatus de una observación (§4.13, Fase 11).
 *
 * APPEND-ONLY: nace `ABIERTA` y solo transita a `RESUELTA` (no hay
 * reapertura en el MVP). Única combinación bloqueante para la finalización:
 * `FALTANTE` + `ABIERTA` (RB-8).
 */
enum EstatusObservacion: string
{
    case ABIERTA = 'ABIERTA';
    case RESUELTA = 'RESUELTA';
}
