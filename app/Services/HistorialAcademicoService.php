<?php

namespace App\Services;

use App\Models\Alumno;
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
                'inscripcionesMaterias.grupo.materia',
                'inscripcionesMaterias.grupo.periodo',
                'inscripcionesMaterias.calificaciones.evaluacion',
            ])
            ->orderBy('periodo_id')
            ->get();

        return $matriculas->flatMap(function ($matricula) {
            return $matricula->inscripcionesMaterias
                ->where('estatus', '!=', 'BAJA')
                ->map(function ($inscripcion) use ($matricula) {
                    $grupo = $inscripcion->grupo;
                    $materia = $grupo?->materia;

                    $calificacionFinal = $inscripcion->calificacion_final;
                    $esAprobada = $inscripcion->esAprobada();
                    $esReprobada = $inscripcion->esReprobada();
                    $esCursando = $inscripcion->esCursando();
                    $esExtraordinario = $inscripcion->estatus === 'EXTRAORDINARIO';

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
     */
    public function creditosTotalesPlan(Alumno $alumno): int
    {
        $matriculaActiva = MatriculaPeriodo::where('alumno_id', auth()->user()?->id ?? $alumno->id)
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

        return $matricula->planVersion->materias->map(function ($materia) use ($historial) {
            $registro = $historial->get($materia->id);

            return [
                'materia' => [
                    'id' => $materia->id,
                    'clave' => $materia->clave,
                    'nombre' => $materia->nombre,
                    'creditos' => $materia->creditos,
                ],
                'estatus' => $registro['estatus'] ?? 'PENDIENTE',
                'calificacion_final' => $registro['calificacion_final'] ?? null,
                'es_aprobada' => $registro['es_aprobada'] ?? false,
                'es_extraordinario' => $registro['es_extraordinario'] ?? false,
            ];
        })->values();
    }
}