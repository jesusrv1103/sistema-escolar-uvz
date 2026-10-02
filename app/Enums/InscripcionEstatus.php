<?php

namespace App\Enums;

/**
 * Estados de la inscripción (§7.1). Única vía de modificación: máquina de estados.
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
}
