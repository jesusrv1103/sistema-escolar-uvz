<?php

namespace App\Services;

use App\Enums\EstatusDocumento;
use App\Enums\EstatusPago;
use App\Enums\InscripcionEstatus;
use App\Models\Documento;
use App\Models\Inscripcion;
use App\Models\Pago;

/**
 * Único punto de cálculo de las métricas operativas del dashboard
 * (FC-9 / RB-20, Fase 14).
 *
 * - Exactamente 4 contadores aprobados, sin estadísticas adicionales:
 *   `inscripciones_pendientes`, `pagos_por_validar`,
 *   `documentos_pendientes` e `inscripciones_completadas`.
 * - Solo LECTURA: consultas `count()` directas sobre Eloquent (sin cargar
 *   colecciones, sin N+1, sin cache/Redis/jobs) y respetando los scopes
 *   normales: los soft-deleted quedan fuera automáticamente (el modelo
 *   `Inscripcion` usa `SoftDeletes`).
 * - El estado de la inscripción manda: "pendiente" se define por
 *   `estatus` (todo lo distinto de `ENROLLED` y `CANCELLED`), nunca se
 *   infiere de pagos o documentos.
 * - No modifica pagos ni documentos; no usa información médica (RB-18).
 */
class DashboardService
{
    /**
     * Los 4 contadores operativos en el orden aprobado (FC-9).
     *
     * @return array{
     *     inscripciones_pendientes: int,
     *     pagos_por_validar: int,
     *     documentos_pendientes: int,
     *     inscripciones_completadas: int
     * }
     */
    public function contadores(): array
    {
        return [
            'inscripciones_pendientes' => $this->inscripcionesPendientes(),
            'pagos_por_validar' => $this->pagosPorValidar(),
            'documentos_pendientes' => $this->documentosPendientes(),
            'inscripciones_completadas' => $this->inscripcionesCompletadas(),
        ];
    }

    /**
     * Inscripciones cuyo estatus NO es `ENROLLED` ni `CANCELLED`
     * (el estado de la inscripción es la única fuente; sin inferencias).
     */
    public function inscripcionesPendientes(): int
    {
        return Inscripcion::query()
            ->whereNotIn('estatus', [
                InscripcionEstatus::ENROLLED->value,
                InscripcionEstatus::CANCELLED->value,
            ])
            ->count();
    }

    /** Pagos con estatus `PENDIENTE` (excluye `VALIDADO` y `RECHAZADO`). */
    public function pagosPorValidar(): int
    {
        return Pago::query()
            ->where('estatus', EstatusPago::PENDIENTE->value)
            ->count();
    }

    /**
     * Documentos YA cargados con estatus `PENDIENTE` (esperando validación);
     * no cuenta tipos obligatorios ausentes.
     */
    public function documentosPendientes(): int
    {
        return Documento::query()
            ->where('estatus', EstatusDocumento::PENDIENTE->value)
            ->count();
    }

    /** Inscripciones con estatus `ENROLLED` (excluye `CANCELLED`). */
    public function inscripcionesCompletadas(): int
    {
        return Inscripcion::query()
            ->where('estatus', InscripcionEstatus::ENROLLED->value)
            ->count();
    }
}
