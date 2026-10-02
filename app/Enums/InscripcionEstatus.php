<?php

namespace App\Enums;

/**
 * Estados de la inscripción (§7.1). Única vía de modificación: máquina de estados.
 *
 * Contiene la ÚNICA matriz de transiciones aprobada (§7.2/§7.3, constante
 * TRANSICIONES). Las guardas de módulos aún inexistentes (pagos, documentos,
 * número de control) NO se simulan aquí: se incorporan en sus fases.
 */
enum InscripcionEstatus: string
{
    case DRAFT = 'DRAFT';
    case DATA_COMPLETE = 'DATA_COMPLETE';
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case PAYMENT_REVIEW = 'PAYMENT_REVIEW';
    case PAYMENT_VALIDATED = 'PAYMENT_VALIDATED';
    case PENDING_DOCUMENTS = 'PENDING_DOCUMENTS';
    case DOCUMENT_REVIEW = 'DOCUMENT_REVIEW';
    case FINAL_REVIEW = 'FINAL_REVIEW';
    case ENROLLED = 'ENROLLED';
    case CANCELLED = 'CANCELLED';

    /**
     * Matriz única `desde => [hasta]` (§7.2). Enunciado en orden §7.1: el
     * orden de las claves define el rango de cada estado para detectar
     * retrocesos. ENROLLED y CANCELLED son terminales (lista vacía).
     *
     * @var array<string, list<string>>
     */
    public const TRANSICIONES = [
        'DRAFT' => ['DATA_COMPLETE', 'CANCELLED'],
        'DATA_COMPLETE' => ['PENDING_PAYMENT', 'CANCELLED'],
        'PENDING_PAYMENT' => ['PAYMENT_REVIEW', 'CANCELLED'],
        'PAYMENT_REVIEW' => ['PAYMENT_VALIDATED', 'PENDING_PAYMENT', 'CANCELLED'],
        'PAYMENT_VALIDATED' => ['PENDING_DOCUMENTS', 'CANCELLED'],
        'PENDING_DOCUMENTS' => ['DOCUMENT_REVIEW', 'CANCELLED'],
        'DOCUMENT_REVIEW' => ['FINAL_REVIEW', 'PENDING_DOCUMENTS', 'CANCELLED'],
        'FINAL_REVIEW' => ['ENROLLED', 'PENDING_DOCUMENTS', 'CANCELLED'],
        'ENROLLED' => [],
        'CANCELLED' => [],
    ];

    public function esTerminal(): bool
    {
        return self::TRANSICIONES[$this->value] === [];
    }

    public function puedeIrA(self $hasta): bool
    {
        return in_array($hasta->value, self::TRANSICIONES[$this->value] ?? [], true);
    }

    /** Rango de la secuencia normal (§7.1) para detectar retrocesos. */
    public function rango(): int
    {
        return array_flip(array_keys(self::TRANSICIONES))[$this->value];
    }

    /** §7.3.2: todo retroceso requiere motivo. */
    public function esRetroceso(self $hasta): bool
    {
        return $hasta->rango() < $this->rango();
    }

    /**
     * Motivo obligatorio: retrocesos (§7.3.2) y toda cancelación (§7.2).
     */
    public function requiereMotivo(self $hasta): bool
    {
        return $this->esRetroceso($hasta) || $hasta === self::CANCELLED;
    }
}
