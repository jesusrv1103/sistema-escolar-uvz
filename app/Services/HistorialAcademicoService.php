<?php

namespace App\Services;

use App\Enums\EstatusEquivalencia;
use App\Enums\EstatusInscripcionMateria;
use App\Models\Alumno;
use App\Models\Equivalencia;
use App\Models\InscripcionMateria;
use App\Models\Materia;
use App\Models\PlanVersion;
use App\Models\MatriculaPeriodo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Servicio único para cálculos académicos derivados.
 *
 * Centraliza:
 * - Historial académico (kardex lógico)
 * - Promedio ponderado por créditos (RB-A4)
 * - Avance académico % (RB-A4)
 * - Créditos cursados/aprobados/pendientes (RB-A5)
 *
 * No persiste snapshots: calcula bajo demanda desde datos firmes.
 * Fuente de verdad: InscripcionMateria con calificacion_final + Acta CERRADA.
 */
class HistorialAcademicoService
{
    /**
     * Obtiene el historial académico completo de un alumno.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function obtenerHistorial(Alumno $alumno): Collection
    {
        $matriculas = MatriculaPeriodo::where('alumno_id', $alumno->id)
            ->where('estatus', '!=', 'EGRESADO')
            ->with([
                'periodo',
                'programa',
                'planVersion',
                'inscripciones.grupo.materia',
                'inscripciones.grupo.periodo',
                'inscripciones.calificaciones.evaluacion',
            ])
            ->orderBy('periodo_id')
            ->get();

        return $matriculas->flatMap(function ($matricula) {
            return $matricula->inscripciones
                ->where('estatus', '!=', 'BAJA')
                ->map(function ($inscripcion) use ($matricula) {
                    $grupo = $inscripcion->grupo;
                    $materia = $grupo?->materia;

                    $calificacionFinal = $inscripcion->calificacion_final;
                    $esAprobada = $inscripcion->isAprobada();
                    $esReprobada = $inscripcion->isReprobada();
                    $esCursando = $inscripcion->isCursando();
                    $esExtraordinario = $inscripcion->estatus === EstatusInscripcionMateria::EXTRAORDINARIO;

                    return [
                        'matricula_periodo_id' => $matricula->id,
                        'periodo' => [
                            'id' => $matricula->periodo->id,
                            'clave' => $matricula->periodo->clave,
                            'nombre' => $matricula->periodo->nombre,
                        ],
                        'programa' => [
                            'id' => $matricula->programa->id,
                            'clave' => $matricula->programa->clave,
                            'nombre' => $matricula->programa->nombre,
                        ],
                        'plan_version' => [
                            'id' => $matricula->planVersion->id,
                            'version' => $matricula->planVersion->version,
                        ],
                        'materia' => $materia ? [
                            'id' => $materia->id,
                            'clave' => $materia->clave,
                            'nombre' => $materia->nombre,
                            'creditos' => $materia->creditos,
                        ] : null,
                        'grupo' => $grupo ? [
                            'id' => $grupo->id,
                            'clave' => $grupo->clave,
                        ] : null,
                        'estatus' => $inscripcion->estatus->value ?? $inscripcion->estatus,
                        'calificacion_final' => $calificacionFinal,
                        'es_aprobada' => $esAprobada,
                        'es_reprobada' => $esReprobada,
                        'es_cursando' => $esCursando,
                        'es_extraordinario' => $esExtraordinario,
                        'es_equivalencia' => false,
                        'fecha_baja' => $inscripcion->fecha_baja,
                    ];
                });
        })->values();
    }

    /**
     * Obtiene historial incluyendo equivalencias/revalidaciones.
     */
    public function obtenerHistorialCompleto(Alumno $alumno): Collection
    {
        $historial = $this->obtenerHistorial($alumno);

        // Agregar equivalencias (se cargan desde tabla equivalencias)
        // Se implementará cuando exista la tabla equivalencias en 2.0-11
        // Por ahora retorna solo historial ordinario + extraordinarios

        return $historial;
    }

    /**
     * Calcula el promedio ponderado por créditos (RB-A4).
     *
     * Incluye: APROBADAS + REPROBADAS + EXTRAORDINARIO (que sustituye ordinaria).
     * Excluye: BAJA, CURSANDO, equivalencias.
     *
     * Fórmula: SUM(calificacion_final * creditos) / SUM(creditos)
     */
    public function calcularPromedio(Alumno $alumno): ?float
    {
        $historial = $this->obtenerHistorial($alumno);

        $conResultado = $historial->filter(fn ($m) => $m['calificacion_final'] !== null);

        if ($conResultado->isEmpty()) {
            return null;
        }

        $sumaPonderada = $conResultado->sum(fn ($m) =>
            ($m['calificacion_final'] ?? 0) * ($m['materia']['creditos'] ?? 0)
        );

        $sumaCreditos = $conResultado->sum(fn ($m) => $m['materia']['creditos'] ?? 0);

        if ($sumaCreditos === 0) {
            return null;
        }

        $promedio = $sumaPonderada / $sumaCreditos;

        // Redondeo HALF_UP a 2 decimales (consistente con política de calificaciones)
        return round($promedio * 100) / 100;
    }

    /**
     * Calcula créditos totales del plan de estudios actual.
     *
     * La matrícula se busca SIEMPRE por el alumno indicado: en una versión
     * anterior se usaba `auth()->user()?->id` como `alumno_id`, de modo que con
     * sesión iniciada se leían los créditos de otro expediente (usuario ≠ alumno).
     */
    public function creditosTotalesPlan(Alumno $alumno): int
    {
        $matriculaActiva = MatriculaPeriodo::where('alumno_id', $alumno->id)
            ->where('estatus', 'ACTIVA')
            ->latest('periodo_id')
            ->first();

        if (!$matriculaActiva || !$matriculaActiva->planVersion) {
            return 0;
        }

        return $matriculaActiva->planVersion->materias()
            ->sum('creditos');
    }

    /**
     * Créditos aprobados (RB-A5: solo APROBADA + EXTRAORDINARIO->APROBADA).
     */
    public function creditosAprobados(Alumno $alumno): int
    {
        $historial = $this->obtenerHistorial($alumno);

        return $historial
            ->filter(fn ($m) => $m['es_aprobada'] === true)
            ->sum(fn ($m) => $m['materia']['creditos'] ?? 0);
    }

    /**
     * Créditos cursados (APROBADA + REPROBADA + EXTRAORDINARIO con resultado).
     */
    public function creditosCursados(Alumno $alumno): int
    {
        $historial = $this->obtenerHistorial($alumno);

        return $historial
            ->filter(fn ($m) => in_array($m['estatus'], ['APROBADA', 'REPROBADA', 'EXTRAORDINARIO'], true)
                && $m['calificacion_final'] !== null)
            ->sum(fn ($m) => $m['materia']['creditos'] ?? 0);
    }

    /**
     * Créditos pendientes = totales del plan - aprobados.
     */
    public function creditosPendientes(Alumno $alumno): int
    {
        $totales = $this->creditosTotalesPlan($alumno);
        $aprobados = $this->creditosAprobados($alumno);

        return max(0, $totales - $aprobados);
    }

    /**
     * Porcentaje de avance académico (RB-A4).
     *
     * avance % = créditos aprobados / créditos totales del plan * 100
     */
    public function porcentajeAvance(Alumno $alumno): float
    {
        $totales = $this->creditosTotalesPlan($alumno);

        if ($totales === 0) {
            return 0.0;
        }

        $aprobados = $this->creditosAprobados($alumno);

        return round(($aprobados / $totales) * 100, 2);
    }

    /**
     * Resumen académico completo para un alumno.
     */
    public function resumenAcademico(Alumno $alumno): array
    {
        return [
            'promedio' => $this->calcularPromedio($alumno),
            'creditos_totales' => $this->creditosTotalesPlan($alumno),
            'creditos_aprobados' => $this->creditosAprobados($alumno),
            'creditos_cursados' => $this->creditosCursados($alumno),
            'creditos_pendientes' => $this->creditosPendientes($alumno),
            'porcentaje_avance' => $this->porcentajeAvance($alumno),
            'historial' => $this->obtenerHistorial($alumno),
        ];
    }

    /**
     * Obtiene materias pendientes por cursar (para tira de materias).
     */
    public function materiasPendientes(Alumno $alumno): Collection
    {
        $matricula = MatriculaPeriodo::where('alumno_id', $alumno->id)
            ->where('estatus', 'ACTIVA')
            ->with('planVersion.materias')
            ->latest('periodo_id')
            ->first();

        if (!$matricula || !$matricula->planVersion) {
            return collect();
        }

        $aprobadas = $this->obtenerHistorial($alumno)
            ->filter(fn ($m) => $m['es_aprobada'])
            ->pluck('materia.id')
            ->toArray();

        return $matricula->planVersion->materias
            ->whereNotIn('id', $aprobadas)
            ->values();
    }

    /**
     * Tira de materias: plan vs estado de cada materia.
     *
     * Contrato de fila (consumido por `pdf/formatos/tira.blade.php`):
     * `materia`, `estatus`, `calificacion_final`, `es_aprobada`,
     * `es_reprobada`, `es_cursando`, `es_extraordinario`,
     * `es_equivalencia` y `es_baja`.
     *
     * Estados, en este orden:
     *
     * 1. Historial ordinario → APROBADA / REPROBADA / CURSANDO /
     *    EXTRAORDINARIO / VALIDADA (misma fuente que el promedio y el avance).
     * 2. Equivalencia APROBADA con `materia_id` → materia acreditada externamente.
     * 3. Inscripción BAJA → materia abandonada (excluida del promedio por diseño,
     *    pero la tira debe mostrarla: es el estado real de esa materia).
     * 4. Sin registro → PENDIENTE.
     *
     * `es_reprobada` faltaba y rompía el PDF con `Undefined array key` (BUG-03).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function tiraMaterias(Alumno $alumno): Collection
    {
        $matricula = MatriculaPeriodo::where('alumno_id', $alumno->id)
            ->where('estatus', 'ACTIVA')
            ->with('planVersion.materias')
            ->latest('periodo_id')
            ->first();

        if (!$matricula || !$matricula->planVersion) {
            return collect();
        }

        $historial = $this->obtenerHistorial($alumno)->keyBy('materia.id');
        $bajas = InscripcionMateria::where('estatus', EstatusInscripcionMateria::BAJA)
            ->whereHas('matriculaPeriodo', fn ($q) => $q->where('alumno_id', $alumno->id))
            ->with('grupo')
            ->get()
            ->keyBy('grupo.materia_id');
        $equivalencias = Equivalencia::where('alumno_id', $alumno->id)
            ->where('estatus', EstatusEquivalencia::APROBADA)
            ->whereNotNull('materia_id')
            ->get()
            ->keyBy('materia_id');

        return $matricula->planVersion->materias->map(function ($materia) use ($historial, $bajas, $equivalencias) {
            $registro = $historial->get($materia->id);

            if ($registro !== null) {
                return [
                    'materia' => $this->datosMateriaDeTira($materia),
                    'estatus' => $registro['estatus'],
                    'calificacion_final' => $registro['calificacion_final'],
                    'es_aprobada' => (bool) $registro['es_aprobada'],
                    'es_reprobada' => (bool) $registro['es_reprobada'],
                    'es_cursando' => (bool) $registro['es_cursando'],
                    'es_extraordinario' => (bool) $registro['es_extraordinario'],
                    'es_equivalencia' => false,
                    'es_baja' => $registro['estatus'] === 'BAJA',
                ];
            }

            if ($equivalencias->has($materia->id)) {
                return $this->filaTiraSinHistorial($materia, 'EQUIVALENCIA', esEquivalencia: true);
            }

            if ($bajas->has($materia->id)) {
                return $this->filaTiraSinHistorial($materia, 'BAJA', esBaja: true);
            }

            return $this->filaTiraSinHistorial($materia, 'PENDIENTE');
        })->values();
    }

    /**
     * Datos de la materia con la forma que consume la plantilla de la tira.
     *
     * @return array{id: int, clave: string, nombre: string, creditos: int}
     */
    private function datosMateriaDeTira(Materia $materia): array
    {
        return [
            'id' => $materia->id,
            'clave' => $materia->clave,
            'nombre' => $materia->nombre,
            'creditos' => $materia->creditos,
        ];
    }

    /**
     * Fila de la tira sin registro en el historial (equivalencia, baja o pendiente).
     *
     * @return array<string, mixed>
     */
    private function filaTiraSinHistorial(
        Materia $materia,
        string $estatus,
        bool $esEquivalencia = false,
        bool $esBaja = false,
    ): array {
        return [
            'materia' => $this->datosMateriaDeTira($materia),
            'estatus' => $estatus,
            'calificacion_final' => null,
            // Una equivalencia acredita la materia, pero no es una calificación
            // de este plantel: no se marca como aprobada para no inflar el
            // semáforo de la plantilla ni el resumen del PDF.
            'es_aprobada' => false,
            'es_reprobada' => false,
            'es_cursando' => false,
            'es_extraordinario' => false,
            'es_equivalencia' => $esEquivalencia,
            'es_baja' => $esBaja,
        ];
    }
}