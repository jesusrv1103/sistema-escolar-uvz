<?php

namespace App\Providers;

use App\Enums\RolUsuario;
use App\Models\Documento;
use App\Models\Observacion;
use App\Models\Pago;
use App\Models\User;
use App\Policies\DocumentoPolicy;
use App\Policies\ObservacionPolicy;
use App\Policies\PagoPolicy;
use App\Policies\UsuarioPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Única fuente de verdad de las capacidades del sistema (§8.2 de la arquitectura).
 *
 * - ADMIN pasa toda capacidad mediante Gate::before (D1 aprobado): no se
 *   dispersan excepciones de ADMIN en controladores, vistas ni policies.
 * - Las policies son delegadoras de estas capacidades (lógica única).
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Capacidad => roles permitidos (sin ADMIN: lo cubre Gate::before).
     * Fuente: matriz de capacidades §8.2.
     *
     * @var array<string, list<RolUsuario>>
     */
    private const CAPACIDADES = [
        'usuario.manage' => [],
        'alumno.create' => [RolUsuario::ADMISIONES],
        'alumno.update' => [RolUsuario::ADMISIONES],
        'alumno.delete' => [RolUsuario::ADMISIONES],
        'informacionMedica.view' => [RolUsuario::ADMISIONES, RolUsuario::CONTROL_ESCOLAR],
        'informacionMedica.edit' => [RolUsuario::ADMISIONES],
        'inscripcion.create' => [RolUsuario::ADMISIONES],
        'inscripcion.update' => [RolUsuario::ADMISIONES],
        'inscripcion.generarCargos' => [RolUsuario::ADMISIONES],
        'inscripcion.finalizar' => [RolUsuario::CONTROL_ESCOLAR],
        'inscripcion.cancelar' => [],
        'convenio.aplicar' => [RolUsuario::ADMISIONES],
        'convenio.aprobar' => [RolUsuario::ADMINISTRACION],
        'pago.registrar' => [RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION],
        'pago.validar' => [RolUsuario::ADMINISTRACION],
        'documento.cargar' => [RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR],
        'documento.validar' => [RolUsuario::CONTROL_ESCOLAR],
        'observacion.crear' => [RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR],
        'observacion.resolver' => [RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR],
        'auditoria.view' => [],
    ];

    public function boot(): void
    {
        // ADMIN pasa todo; cualquier cuenta inactiva lo niega todo (RB-21).
        Gate::before(function (User $user): ?bool {
            if (! $user->activo) {
                return false;
            }

            return $user->esAdmin() ? true : null;
        });

        foreach (self::CAPACIDADES as $capacidad => $roles) {
            Gate::define($capacidad, fn (User $user): bool => $user->activo
                && in_array($user->rol, $roles, true));
        }

        Gate::policy(User::class, UsuarioPolicy::class);
        // Fase 8: PagoPolicy conectada al modelo real (registro server-side,
        // validación/rechazo four-eyes y descarga del comprobante privado).
        Gate::policy(Pago::class, PagoPolicy::class);
        // Fase 10: DocumentoPolicy conectada al modelo real (carga, validación
        // four-eyes y descarga del archivo en disco privado).
        Gate::policy(Documento::class, DocumentoPolicy::class);
        // Fase 11: ObservacionPolicy conectada al modelo real (crear/resolver
        // por los 4 roles, §8.2; la UI y el servicio autorizan server-side).
        Gate::policy(Observacion::class, ObservacionPolicy::class);

        // Las policies de modelos de fases futuras (Alumno, InformacionMedica,
        // Inscripcion, Convenio) ya existen en App\Policies y Laravel las
        // resuelve por convención; aquí solo se registran las que tienen modelo.
    }
}
