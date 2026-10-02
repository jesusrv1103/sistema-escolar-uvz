<?php

namespace App\Models\Concerns;

use App\Models\Auditoria;
use App\Services\AuditoriaService;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Integra la auditoría en los modelos auditables (§10.1 HasAudit).
 * Aporta la relación morphMany y el acceso cómodo al servicio único de escritura.
 */
trait HasAudit
{
    public function auditorias(): MorphMany
    {
        return $this->morphMany(Auditoria::class, 'auditable');
    }

    /** @param array<string, mixed> $antes @param array<string, mixed> $despues @param array<string, mixed> $datos */
    public function registrarAuditoria(string $accion, array $antes = [], array $despues = [], array $datos = []): Auditoria
    {
        return app(AuditoriaService::class)->registrar($this, $accion, $antes, $despues, $datos);
    }
}
