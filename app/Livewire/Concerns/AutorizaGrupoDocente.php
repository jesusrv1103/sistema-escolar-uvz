<?php

namespace App\Livewire\Concerns;

use App\Enums\RolUsuario;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Aislamiento del portal de docente (2.0-15).
 *
 * Reutilizado por las pantallas de captura que el DOCENTE reutiliza (evaluaciones,
 * calificaciones, asistencia y actas): el DOCENTE sólo entra y lista sus propios
 * grupos (`GrupoPolicy::verComoDocente`); para el resto de roles el trait no
 * modifica nada. La escritura ya estaba acotada en los servicios
 * (`EvaluacionService`, `CalificacionService`, `AsistenciaService`).
 */
trait AutorizaGrupoDocente
{
    /** ¿El usuario actual tiene rol DOCENTE? */
    protected function esDocente(): bool
    {
        return auth()->user()?->rol === RolUsuario::DOCENTE;
    }

    /**
     * Cuando el usuario es DOCENTE exige un grupo asignado a él; para el resto
     * de roles no hace nada (la ability de la pantalla ya se validó antes).
     */
    protected function autorizarGrupoSiDocente(?Grupo $grupo): void
    {
        if (!$this->esDocente()) {
            return;
        }

        if ($grupo === null || !$grupo->exists) {
            abort(403, 'Selecciona un grupo propio para continuar.');
        }

        Gate::authorize('verComoDocente', $grupo);
    }

    /** Filtra un listado de grupos: propios para DOCENTE, todos para el resto. */
    protected function limitarGruposAlDocente(Builder $consulta): Builder
    {
        if ($this->esDocente()) {
            $consulta->where('docente_id', auth()->user()->docente?->id ?? 0);
        }

        return $consulta;
    }

    /** Id del grupo asignado al usuario DOCENTE (0 si no tiene perfil). */
    protected function grupoDelDocente(): int
    {
        return (int) (auth()->user()->docente?->id ?? 0);
    }
}
