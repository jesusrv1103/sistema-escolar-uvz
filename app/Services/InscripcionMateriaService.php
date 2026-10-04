<?php

namespace App\Services;

use App\Enums\EstatusInscripcionMateria;
use App\Enums\EstatusMatriculaPeriodo;
use App\Enums\TipoPrerrequisito;
use App\Exceptions\InscripcionMateriaException;
use App\Models\Grupo;
use App\Models\InscripcionMateria;
use App\Models\MatriculaPeriodo;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Servicio para gestión de inscripciones a materias (carga académica).
 *
 * Reglas de negocio:
 * - UNIQUE(matricula_periodo_id, grupo_id): una matrícula solo puede inscribirse una vez a un grupo
 * - Validación de prerrequisitos: RECOMENDADO no bloquea, OBLIGATORIO solo si hay datos reales
 * - Validación de cupo en el grupo
 * - Validación de conflictos de horario
 * - Validación de estatus de matrícula (debe ser ACTIVA)
 * - Validación de estatus de grupo (debe ser ABIERTO)
 */
class InscripcionMateriaService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
    ) {
    }

    /**
     * Inscribe una matrícula a un grupo (carga de materia).
     *
     * @throws InscripcionMateriaException si fallan validaciones
     */
    public function inscribir(
        MatriculaPeriodo $matricula,
        Grupo $grupo,
        ?User $usuario = null,
    ): InscripcionMateria {
        $usuario ??= auth()->user();

        if ($usuario !== null) {
            Gate::forUser($usuario)->authorize('inscripcion_materia.create');
        } else {
            Gate::authorize('inscripcion_materia.create');
        }

        return DB::transaction(function () use ($matricula, $grupo, $usuario): InscripcionMateria {
            // 1. Validaciones previas
            $this->validarInscripcion($matricula, $grupo);

            // 2. Verificar prerrequisitos
            $validacionPrerrequisitos = $this->validarPrerrequisitos($matricula, $grupo->materia);

            // 3. Crear inscripción
            $inscripcion = InscripcionMateria::create([
                'matricula_periodo_id' => $matricula->id,
                'grupo_id' => $grupo->id,
                'materia_id' => $grupo->materia_id,
                'tipo_prerrequisito' => $validacionPrerrequisitos['tipo'],
                'prerrequisito_cumplido' => $validacionPrerrequisitos['cumplido'],
                'estatus' => EstatusInscripcionMateria::CURSANDO->value,
                'observaciones' => $validacionPrerrequisitos['observaciones'],
            ]);

            $this->auditoria->registrar(
                $inscripcion,
                'inscripcion_materia_creada',
                [],
                [
                    'matricula_periodo_id' => $matricula->id,
                    'grupo_id' => $grupo->id,
                    'materia_id' => $grupo->materia_id,
                    'tipo_prerrequisito' => $validacionPrerrequisitos['tipo'],
                    'prerrequisito_cumplido' => $validacionPrerrequisitos['cumplido'],
                    'estatus' => $inscripcion->estatus,
                ],
                [],
                $usuario,
            );

            return $inscripcion;
        });
    }

    /**
     * Inscribe múltiples grupos en lote (carga académica completa).
     *
     * @return list<InscripcionMateria>
     */
    public function inscribirLote(
        MatriculaPeriodo $matricula,
        array $grupoIds,
        ?User $usuario = null,
    ): array {
        $usuario ??= auth()->user();

        Gate::authorize('inscripcion_materia.create');

        $inscripciones = [];

        foreach ($grupoIds as $grupoId) {
            $grupo = Grupo::findOrFail($grupoId);
            $inscripciones[] = $this->inscribir($matricula, $grupo, $usuario);
        }

        return $inscripciones;
    }

    /**
     * Cambia el estatus de una inscripción a materia.
     */
    public function cambiarEstatus(
        InscripcionMateria $inscripcion,
        EstatusInscripcionMateria $nuevoEstatus,
        ?float $calificacion = null,
        ?string $observaciones = null,
        ?User $usuario = null,
    ): InscripcionMateria {
        $usuario ??= auth()->user();

        if ($usuario !== null) {
            Gate::forUser($usuario)->authorize('inscripcion_materia.update', $inscripcion);
        } else {
            Gate::authorize('inscripcion_materia.update', $inscripcion);
        }

        $estatusAnterior = $inscripcion->estatus;

        if ($estatusAnterior === $nuevoEstatus->value) {
            return $inscripcion;
        }

        // Validar transiciones permitidas
        $this->validarTransicion($estatusAnterior, $nuevoEstatus);

        // Si se aprueba, validar calificación
        if ($nuevoEstatus === EstatusInscripcionMateria::APROBADA && $calificacion === null) {
            throw new InscripcionMateriaException('La calificación es obligatoria para aprobar la materia.');
        }

        return DB::transaction(function () use ($inscripcion, $estatusAnterior, $nuevoEstatus, $calificacion, $observaciones, $usuario): InscripcionMateria {
            $datos = [
                'estatus' => $nuevoEstatus->value,
                'observaciones' => $observaciones ?? $inscripcion->observaciones,
            ];

            if ($calificacion !== null) {
                $datos['calificacion_final'] = $calificacion;
            }

            if ($nuevoEstatus === EstatusInscripcionMateria::BAJA) {
                $datos['fecha_baja'] = now();
            }

            $inscripcion->forceFill($datos)->save();

            $this->auditoria->registrar(
                $inscripcion,
                'estatus_cambiado',
                ['estatus' => $estatusAnterior, 'calificacion_final' => $inscripcion->getOriginal('calificacion_final')],
                ['estatus' => $nuevoEstatus->value, 'calificacion_final' => $calificacion],
                $observaciones !== null ? ['motivo' => $observaciones] : [],
                $usuario,
            );

            return $inscripcion->refresh();
        });
    }

    /**
     * Valida prerrequisitos de una materia para una matrícula.
     *
     * Reglas:
     * - RECOMENDADO: nunca bloquea, solo informa (tipo = RECOMENDADO, cumplido = false si falta)
     * - OBLIGATORIO: bloquea SOLO si hay datos reales de cursado (tipo = OBLIGATORIO, cumplido = true/false según datos)
     * - Si no hay datos de cursado previo, OBLIGATORIO se trata como RECOMENDADO (no bloquea)
     */
    public function validarPrerrequisitos(MatriculaPeriodo $matricula, Materia $materia): array
    {
        $prerrequisitosObligatorios = $materia->prerrequisitosObligatorios()->get();
        $prerrequisitosRecomendados = $materia->prerrequisitosRecomendados()->get();

        $tipo = null;
        $cumplido = true;
        $observaciones = [];

        // Verificar prerrequisitos OBLIGATORIOS
        if ($prerrequisitosObligatorios->isNotEmpty()) {
            $tipo = TipoPrerrequisito::OBLIGATORIO;

            foreach ($prerrequisitosObligatorios as $prerreq) {
                $cumplidoPrerreq = $this->verificarPrerrequisitoCursado($matricula, $prerreq);

                if (! $cumplidoPrerreq['tieneDatos']) {
                    // No hay datos de cursado previo → no bloquea, se trata como recomendado
                    $observaciones[] = "Prerrequisito obligatorio '{$prerreq->clave}' sin historial de cursado: no bloquea.";
                    $cumplido = false;
                } elseif (! $cumplidoPrerreq['aprobado']) {
                    // Tiene datos pero no aprobado → BLOQUEA
                    throw new InscripcionMateriaException("Prerrequisito obligatorio '{$prerreq->clave}' no aprobado: BLOQUEA inscripción.");
                }
            }
        }

        // Verificar prerrequisitos RECOMENDADOS
        if ($prerrequisitosRecomendados->isNotEmpty()) {
            if ($tipo === null) {
                $tipo = TipoPrerrequisito::RECOMENDADO;
            }

            foreach ($prerrequisitosRecomendados as $prerreq) {
                $cumplidoPrerreq = $this->verificarPrerrequisitoCursado($matricula, $prerreq);

                if (! $cumplidoPrerreq['tieneDatos']) {
                    $observaciones[] = "Prerrequisito recomendado '{$prerreq->clave}' sin historial de cursado.";
                    $cumplido = false;
                } elseif (! $cumplidoPrerreq['aprobado']) {
                    $observaciones[] = "Prerrequisito recomendado '{$prerreq->clave}' no aprobado.";
                    $cumplido = false;
                }
            }
        }

        if ($observaciones === []) {
            $observaciones[] = 'Todos los prerrequisitos cumplidos.';
        }

        return [
            'tipo' => $tipo?->value,
            'cumplido' => $cumplido,
            'observaciones' => implode(' ', $observaciones),
        ];
    }

    /**
     * Verifica si un prerrequisito específico está aprobado en el historial del alumno.
     */
    private function verificarPrerrequisitoCursado(MatriculaPeriodo $matricula, Materia $prerrequisito): array
    {
        $inscripcionPrevia = InscripcionMateria::where('matricula_periodo_id', $matricula->id)
            ->where('materia_id', $prerrequisito->id)
            ->whereIn('estatus', [EstatusInscripcionMateria::APROBADA->value, EstatusInscripcionMateria::REPROBADA->value, EstatusInscripcionMateria::VALIDADA->value])
            ->first();

        if (! $inscripcionPrevia) {
            return [
                'tieneDatos' => false,
                'aprobado' => false,
            ];
        }

        return [
            'tieneDatos' => true,
            'aprobado' => $inscripcionPrevia->estatus === EstatusInscripcionMateria::APROBADA
                || $inscripcionPrevia->estatus === EstatusInscripcionMateria::VALIDADA,
        ];
    }

    /**
     * Valida que la inscripción sea posible.
     */
    private function validarInscripcion(MatriculaPeriodo $matricula, Grupo $grupo): void
    {
        // 1. Matrícula debe estar activa
        if (! $matricula->isActiva()) {
            throw new InscripcionMateriaException('La matrícula no está activa.');
        }

        // 2. Grupo debe estar abierto para inscripciones
        if (! $grupo->estaAbierto()) {
            throw new InscripcionMateriaException('El grupo no está abierto para inscripciones.');
        }

        // 3. Grupo debe tener cupo disponible
        if (! $grupo->tieneCupo()) {
            throw new InscripcionMateriaException('El grupo no tiene cupo disponible.');
        }

        // 4. No estar ya inscrito en este grupo
        $yaInscrito = InscripcionMateria::where('matricula_periodo_id', $matricula->id)
            ->where('grupo_id', $grupo->id)
            ->exists();

        if ($yaInscrito) {
            throw new InscripcionMateriaException('La matrícula ya está inscrita en este grupo.');
        }

        // 5. No estar ya inscrito en otra grupo de la misma materia en el mismo periodo
        $mismoPeriodoMismoMateria = InscripcionMateria::where('matricula_periodo_id', $matricula->id)
            ->where('materia_id', $grupo->materia_id)
            ->whereHas('grupo', fn ($q) => $q->where('periodo_id', $grupo->periodo_id))
            ->whereIn('estatus', [EstatusInscripcionMateria::CURSANDO->value, EstatusInscripcionMateria::APROBADA->value])
            ->exists();

        if ($mismoPeriodoMismoMateria) {
            throw new InscripcionMateriaException('Ya está inscrito en esta materia para este periodo.');
        }

        // 6. Validar conflictos de horario con otras inscripciones CURSANDO
        $conflicto = $this->validarConflictoHorario($matricula, $grupo);
        if ($conflicto) {
            throw new InscripcionMateriaException("Conflicto de horario: {$conflicto}");
        }
    }

    /**
     * Valida conflictos de horario con otras inscripciones cursando.
     */
    private function validarConflictoHorario(MatriculaPeriodo $matricula, Grupo $nuevoGrupo): ?string
    {
        $inscripcionesCursando = $matricula->inscripcionesCursando()
            ->with('grupo.horarios')
            ->get();

        foreach ($inscripcionesCursando as $inscripcion) {
            $grupoExistente = $inscripcion->grupo;

            if (! $grupoExistente) {
                continue;
            }

            foreach ($nuevoGrupo->horarios as $nuevoHorario) {
                foreach ($grupoExistente->horarios as $horarioExistente) {
                    if ($this->horariosSeSolapan($nuevoHorario, $horarioExistente)) {
                        return sprintf(
                            'Conflicto con grupo %s (%s): %s %s-%s',
                            $grupoExistente->clave,
                            $grupoExistente->materia->clave,
                            $nuevoHorario->dia->label(),
                            $nuevoHorario->hora_inicio->format('H:i'),
                            $nuevoHorario->hora_fin->format('H:i'),
                        );
                    }
                }
            }
        }

        return null;
    }

    /**
     * Verifica si dos horarios se solapan.
     */
    private function horariosSeSolapan(\App\Models\GrupoHorario $h1, \App\Models\GrupoHorario $h2): bool
    {
        if ($h1->dia !== $h2->dia) {
            return false;
        }

        return $h1->hora_inicio < $h2->hora_fin && $h1->hora_fin > $h2->hora_inicio;
    }

    /**
     * Valida transiciones de estatus permitidas.
     */
    private function validarTransicion(EstatusInscripcionMateria $desde, EstatusInscripcionMateria $hasta): void
    {
        $transiciones = [
            EstatusInscripcionMateria::CURSANDO->value => [
                EstatusInscripcionMateria::APROBADA,
                EstatusInscripcionMateria::REPROBADA,
                EstatusInscripcionMateria::BAJA,
                EstatusInscripcionMateria::VALIDADA,
            ],
            EstatusInscripcionMateria::REPROBADA->value => [
                EstatusInscripcionMateria::CURSANDO, // reinscripción
            ],
            EstatusInscripcionMateria::BAJA->value => [
                EstatusInscripcionMateria::CURSANDO, // reactivación
            ],
            EstatusInscripcionMateria::APROBADA->value => [], // terminal
            EstatusInscripcionMateria::VALIDADA->value => [], // terminal
        ];

        $permitidas = $transiciones[$desde->value] ?? [];

        if (! in_array($hasta, $permitidas, true)) {
            throw new InscripcionMateriaException(sprintf(
                'Transición de estatus no permitida: %s → %s',
                $desde->value,
                $hasta->value,
            ));
        }
    }
}