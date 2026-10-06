<?php

namespace App\Providers;

use App\Enums\RolUsuario;
use App\Models\Calificacion;
use App\Models\Documento;
use App\Models\DocumentoGenerado;
use App\Models\Egreso;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\GrupoHorario;
use App\Models\InscripcionMateria;
use App\Models\MatriculaPeriodo;
use App\Models\Materia;
use App\Models\Observacion;
use App\Models\Pago;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\PracticaProfesional;
use App\Models\ServicioSocial;
use App\Models\Titulacion;
use App\Models\User;
use App\Policies\CalificacionPolicy;
use App\Policies\DocumentoGeneradoPolicy;
use App\Policies\DocumentoPolicy;
use App\Policies\EgresoPolicy;
use App\Policies\EvaluacionPolicy;
use App\Policies\GrupoHorarioPolicy;
use App\Policies\GrupoPolicy;
use App\Policies\InscripcionMateriaPolicy;
use App\Policies\MatriculaPeriodoPolicy;
use App\Policies\MateriaPolicy;
use App\Policies\ObservacionPolicy;
use App\Policies\PagoPolicy;
use App\Policies\PlanEstudioPolicy;
use App\Policies\PlanVersionPolicy;
use App\Policies\PracticaProfesionalPolicy;
use App\Policies\ServicioSocialPolicy;
use App\Policies\TitulacionPolicy;
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
        // 2.0-01 — Estructura académica (admin-only, patrón usuario.manage)
        'plan_estudio.create' => [],
        'plan_estudio.update' => [],
        'plan_estudio.delete' => [],
        'plan_version.create' => [],
        'plan_version.aprobar' => [],
        'materia.create' => [],
        'materia.update' => [],
        'materia.delete' => [],
        'prerrequisito.gestionar' => [],
        // 2.0-02 — Docentes y Aulas (admin-only, patrón usuario.manage)
        'docente.create' => [],
        'docente.update' => [],
        'docente.delete' => [],
        'aula.create' => [],
        'aula.update' => [],
        'aula.delete' => [],
        // 2.0-03 — Grupos y Horarios (admin-only, patrón usuario.manage)
        'grupo.create' => [],
        'grupo.update' => [],
        'grupo.delete' => [],
        'grupo_horario.create' => [],
        'grupo_horario.update' => [],
        'grupo_horario.delete' => [],
        // 2.0-04 — Matrícula y Carga Académica (admin-only, patrón usuario.manage)
        'matricula.create' => [],
        'matricula.update' => [],
        'matricula.delete' => [],
        'inscripcion_materia.create' => [RolUsuario::CONTROL_ESCOLAR],
        'inscripcion_materia.update' => [],
        'inscripcion_materia.delete' => [],
        'prerrequisito.validar' => [],
        // 2.0-05 — Motor de formatos + formatos académicos
        'documento_generado.crear' => [],
        'documento_generado.ver' => [],
        // 2.0-06 — Evaluaciones y Calificaciones + Rol DOCENTE
        'evaluacion.crear' => [RolUsuario::DOCENTE],
        'evaluacion.actualizar' => [RolUsuario::DOCENTE],
        'evaluacion.eliminar' => [RolUsuario::CONTROL_ESCOLAR],
        'calificacion.crear' => [RolUsuario::DOCENTE, RolUsuario::CONTROL_ESCOLAR],
        'calificacion.actualizar' => [RolUsuario::DOCENTE, RolUsuario::CONTROL_ESCOLAR],
        'calificacion.consolidar' => [RolUsuario::CONTROL_ESCOLAR],
        // 2.0-07 — Asistencia + Extraordinarios
        'asistencia.crear' => [RolUsuario::DOCENTE, RolUsuario::CONTROL_ESCOLAR],
        'asistencia.actualizar' => [RolUsuario::DOCENTE, RolUsuario::CONTROL_ESCOLAR],
        'asistencia.ver' => [RolUsuario::DOCENTE, RolUsuario::CONTROL_ESCOLAR],
        'extraordinario.iniciar' => [RolUsuario::CONTROL_ESCOLAR],
        'extraordinario.capturar' => [RolUsuario::CONTROL_ESCOLAR],
        // 2.0-08 — Actas de calificaciones
        'acta.crear' => [RolUsuario::DOCENTE, RolUsuario::CONTROL_ESCOLAR],
        'acta.cerrar' => [RolUsuario::CONTROL_ESCOLAR],
        'acta.ver' => [RolUsuario::DOCENTE, RolUsuario::CONTROL_ESCOLAR],
        // 2.0-09 — Historiales y Avance
        'historial.ver' => [RolUsuario::CONTROL_ESCOLAR, RolUsuario::ADMINISTRACION],
        // 2.0-10 — Formatos académicos II
        'formato_academico.generar' => [RolUsuario::CONTROL_ESCOLAR, RolUsuario::ADMINISTRACION],
        // 2.0-11 — Bajas y Equivalencias
        'baja.crear' => [RolUsuario::CONTROL_ESCOLAR],
        'baja.reingreso' => [RolUsuario::CONTROL_ESCOLAR],
        'baja.ver' => [RolUsuario::CONTROL_ESCOLAR, RolUsuario::ADMINISTRACION],
        'equivalencia.crear' => [RolUsuario::CONTROL_ESCOLAR],
        'equivalencia.actualizar' => [RolUsuario::CONTROL_ESCOLAR],
        'equivalencia.ver' => [RolUsuario::CONTROL_ESCOLAR, RolUsuario::ADMINISTRACION],
        // 2.0-12 — Becas y Descuentos (D-N3)
        'beca.crear' => [RolUsuario::CONTROL_ESCOLAR],
        'beca.actualizar' => [RolUsuario::CONTROL_ESCOLAR],
        'beca.ver' => [RolUsuario::CONTROL_ESCOLAR, RolUsuario::ADMINISTRACION],
        'beca.asignar' => [RolUsuario::CONTROL_ESCOLAR],
        'beca.aprobar' => [RolUsuario::ADMINISTRACION],
        'beca.rechazar' => [RolUsuario::ADMINISTRACION],
        'estado_cuenta.ver' => [RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR],
        'carta_no_adeudo.generar' => [RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR],
        // 2.0-13 — Servicio social y prácticas profesionales
        // (CONTROL_ESCOLAR es protagonista; ADMISIONES, ADMINISTRACION y DOCENTE
        // no obtienen permisos de escritura en estos procesos)
        'servicio_social.ver' => [RolUsuario::CONTROL_ESCOLAR, RolUsuario::ADMINISTRACION],
        'servicio_social.crear' => [RolUsuario::CONTROL_ESCOLAR],
        'servicio_social.actualizar' => [RolUsuario::CONTROL_ESCOLAR],
        'servicio_social.validar' => [RolUsuario::CONTROL_ESCOLAR],
        'practica.ver' => [RolUsuario::CONTROL_ESCOLAR, RolUsuario::ADMINISTRACION],
        'practica.crear' => [RolUsuario::CONTROL_ESCOLAR],
        'practica.actualizar' => [RolUsuario::CONTROL_ESCOLAR],
        'practica.validar' => [RolUsuario::CONTROL_ESCOLAR],
        // 2.0-14 — Egreso y titulación internos (procesos internos de
        // CONTROL_ESCOLAR; sin permisos nuevos para ADMISIONES/DOCENTE)
        'egreso.ver' => [RolUsuario::CONTROL_ESCOLAR, RolUsuario::ADMINISTRACION],
        'egreso.crear' => [RolUsuario::CONTROL_ESCOLAR],
        'titulacion.ver' => [RolUsuario::CONTROL_ESCOLAR, RolUsuario::ADMINISTRACION],
        'titulacion.crear' => [RolUsuario::CONTROL_ESCOLAR],
        'titulacion.actualizar' => [RolUsuario::CONTROL_ESCOLAR],
        // 2.0-15 — Portal de docente (sólo DOCENTE) + lectura de portales
        // endurecida según §8.2: "Lectura de alumnos / inscripciones / pagos /
        // documentos" = ADMISIONES, ADMINISTRACION y CONTROL_ESCOLAR. No es un
        // permiso nuevo: antes valía `$user->activo` (cualquier rol autenticado,
        // incl. DOCENTE). ADMIN pasa por Gate::before.
        'portal_docente.acceder' => [RolUsuario::DOCENTE],
        'docente.grupo.ver' => [RolUsuario::DOCENTE],
        'alumno.ver' => [RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR],
        'inscripcion.ver' => [RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR],
        'pago.ver' => [RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR],
        'documento.ver' => [RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR],
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
        Gate::policy(Pago::class, PagoPolicy::class);
        Gate::policy(Documento::class, DocumentoPolicy::class);
        Gate::policy(DocumentoGenerado::class, DocumentoGeneradoPolicy::class);
        Gate::policy(Observacion::class, ObservacionPolicy::class);
        Gate::policy(PlanEstudio::class, PlanEstudioPolicy::class);
        Gate::policy(PlanVersion::class, PlanVersionPolicy::class);
        Gate::policy(Materia::class, MateriaPolicy::class);
        Gate::policy(Grupo::class, GrupoPolicy::class);
        Gate::policy(GrupoHorario::class, GrupoHorarioPolicy::class);
        Gate::policy(MatriculaPeriodo::class, MatriculaPeriodoPolicy::class);
        Gate::policy(InscripcionMateria::class, InscripcionMateriaPolicy::class);
        Gate::policy(Evaluacion::class, EvaluacionPolicy::class);
        Gate::policy(Calificacion::class, CalificacionPolicy::class);
        Gate::policy(Asistencia::class, AsistenciaPolicy::class);
        Gate::policy(Acta::class, ActaPolicy::class);
        Gate::policy(Baja::class, BajaPolicy::class);
        Gate::policy(Equivalencia::class, EquivalenciaPolicy::class);
        Gate::policy(Beca::class, BecaPolicy::class);
        Gate::policy(BecaAlumno::class, BecaAlumnoPolicy::class);
        Gate::policy(AjusteFinanciero::class, AjusteFinancieroPolicy::class);
        Gate::policy(ServicioSocial::class, ServicioSocialPolicy::class);
        Gate::policy(PracticaProfesional::class, PracticaProfesionalPolicy::class);
        Gate::policy(Egreso::class, EgresoPolicy::class);
        Gate::policy(Titulacion::class, TitulacionPolicy::class);

        // Las policies de modelos de fases futuras ya existen en App\Policies
        // y Laravel las resuelve por convención; aquí solo se registran las que tienen modelo.
    }
}
