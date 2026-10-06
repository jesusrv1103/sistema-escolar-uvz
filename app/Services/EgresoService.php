<?php

namespace App\Services;

use App\Enums\EstatusEquivalencia;
use App\Exceptions\EgresoInvalidoException;
use App\Models\Alumno;
use App\Models\Egreso;
use App\Models\Equivalencia;
use App\Models\Materia;
use App\Models\MatriculaPeriodo;
use App\Models\PracticaProfesional;
use App\Models\ServicioSocial;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Servicio único de Egreso interno (2.0-14).
 *
 * Requisitos evaluables con datos que YA existen en el sistema (fila 270 del
 * análisis de evolución + alcance de esta fase):
 *
 *  1. Versión del plan identificable (matrícula ACTIVA con plan).
 *  2. Materias del plan cubiertas: aprobadas en el histórico O equivalencia
 *     APROBADA con `materia_id`; las equivalencias APROBADA sin `materia_id`
 *     aportan sólo sus créditos (no cubren materias).
 *  3. Créditos aprobados (histórico + equivalencias) >= créditos del plan.
 *  4. Servicio Social VALIDADO.
 *  5. Prácticas profesionales VALIDADAS.
 *  6. Sin baja vigente ni definitiva (`BajaService::bloqueaNuevaMatricula`).
 *
 * NO se evalúan requisitos administrativos/documentales (constancias, pagos,
 * expediente completo): no hay fuente que los defina → pendiente institucional.
 * La deuda NO es requisito de egreso.
 *
 * El registro sólo se crea cuando TODO está cumplido: su presencia es el
 * estado "EGRESÓ".
 */
class EgresoService
{
    public function __construct(
        private readonly HistorialAcademicoService $historial,
        private readonly BajaService $bajas,
        private readonly AuditoriaService $auditoria,
    ) {
    }

    /**
     * Evalúa los requisitos de egreso sin persistir nada.
     *
     * @return array{
     *     egresado: bool,
     *     egreso: ?\App\Models\Egreso,
     *     plan_version: ?\App\Models\PlanVersion,
     *     requisitos: list<array{clave: string, nombre: string, cumplido: bool, detalle: string}>,
     *     creditos_requeridos: int,
     *     creditos_acumulados: int,
     *     faltantes: list<string>,
     *     puede_registrar: bool
     * }
     */
    public function verificar(Alumno $alumno): array
    {
        $egreso = Egreso::where('alumno_id', $alumno->id)->first();

        $matricula = MatriculaPeriodo::where('alumno_id', $alumno->id)
            ->where('estatus', 'ACTIVA')
            ->latest('periodo_id')
            ->first();

        $version = $matricula?->planVersion;
        $materiasPlan = $version
            ? $version->materias()->get()
            : collect();

        [$aprobadas, $creditosEquivalenciaPlan, $creditosLibres, $faltantesMateria] =
            $this->cubrimiento($alumno, $materiasPlan);

        $creditosRequeridos = (int) $materiasPlan->sum('creditos');
        $creditosAcumulados = (int) $aprobadas->sum() + $creditosEquivalenciaPlan + $creditosLibres;

        $servicioSocial = ServicioSocial::where('alumno_id', $alumno->id)->first();
        $practicas = PracticaProfesional::where('alumno_id', $alumno->id)->first();
        $bloqueada = $this->bajas->bloqueaNuevaMatricula($alumno);

        $requisitos = [
            [
                'clave' => 'plan',
                'nombre' => 'Versión del plan identificable',
                'cumplido' => $version !== null,
                'detalle' => $version
                    ? 'Plan ' . $version->plan?->clave . ' versión ' . $version->version
                    : 'Sin matrícula activa con versión de plan identificable',
            ],
            [
                'clave' => 'materias',
                'nombre' => 'Materias del plan cubiertas',
                'cumplido' => $version !== null
                    && $materiasPlan->isNotEmpty()
                    && $faltantesMateria->isEmpty(),
                'detalle' => $version
                    ? ($materiasPlan->isEmpty()
                        ? 'El plan no tiene materias registradas'
                        : ($faltantesMateria->isEmpty()
                            ? $materiasPlan->count() . ' de ' . $materiasPlan->count() . ' materias cubiertas'
                            : $materiasPlan->count() - $faltantesMateria->count() . ' de ' . $materiasPlan->count()
                                . ' materias; faltan: ' . $faltantesMateria->take(4)->implode(', ')
                                . ($faltantesMateria->count() > 4 ? ' y ' . ($faltantesMateria->count() - 4) . ' más' : '')))
                    : 'No evaluable sin versión de plan',
            ],
            [
                'clave' => 'creditos',
                'nombre' => 'Créditos aprobados requeridos',
                'cumplido' => $version !== null
                    && $creditosRequeridos > 0
                    && $creditosAcumulados >= $creditosRequeridos,
                'detalle' => $version
                    ? $creditosAcumulados . ' de ' . $creditosRequeridos . ' créditos'
                    : 'No evaluable sin versión de plan',
            ],
            [
                'clave' => 'servicio_social',
                'nombre' => 'Servicio Social validado',
                'cumplido' => $servicioSocial?->esValidado() === true,
                'detalle' => $servicioSocial
                    ? ($servicioSocial->esValidado()
                        ? 'Validado el ' . ($servicioSocial->validado_at?->format('d/m/Y') ?? '—')
                        : 'En proceso (avance ' . $servicioSocial->avance . '%)')
                    : 'Sin registro de Servicio Social',
            ],
            [
                'clave' => 'practicas',
                'nombre' => 'Prácticas profesionales validadas',
                'cumplido' => $practicas?->esValidado() === true,
                'detalle' => $practicas
                    ? ($practicas->esValidado()
                        ? 'Validadas el ' . ($practicas->validado_at?->format('d/m/Y') ?? '—')
                        : 'En proceso')
                    : 'Sin registro de Prácticas',
            ],
            [
                'clave' => 'sin_baja',
                'nombre' => 'Sin baja vigente o definitiva',
                'cumplido' => ! $bloqueada,
                'detalle' => $bloqueada
                    ? 'Baja vigente o definitiva registrada'
                    : 'Sin bloqueos por baja',
            ],
        ];

        $faltantes = collect($requisitos)
            ->filter(fn (array $r) => ! $r['cumplido'])
            ->pluck('nombre')
            ->values()
            ->all();

        return [
            'egresado' => $egreso !== null,
            'egreso' => $egreso,
            'plan_version' => $version,
            'requisitos' => $requisitos,
            'creditos_requeridos' => $creditosRequeridos,
            'creditos_acumulados' => $creditosAcumulados,
            'faltantes' => $faltantes,
            'puede_registrar' => $egreso === null && $faltantes === [],
        ];
    }

    /**
     * Registra el egreso. Sólo es posible con TODOS los requisitos cumplidos.
     *
     * @throws EgresoInvalidoException si ya existe el egreso o faltan requisitos
     */
    public function registrar(Alumno $alumno, ?User $usuario = null): Egreso
    {
        $usuario ??= auth()->user();
        $this->autorizar('egreso.crear', $usuario);

        return DB::transaction(function () use ($alumno, $usuario): Egreso {
            $verificacion = $this->verificar($alumno);

            if ($verificacion['egreso'] !== null) {
                throw new EgresoInvalidoException('El alumno ya tiene el egreso registrado.');
            }

            if ($verificacion['faltantes'] !== []) {
                throw new EgresoInvalidoException(
                    'No se puede registrar el egreso, faltan requisitos: '
                    . implode(', ', $verificacion['faltantes']) . '.'
                );
            }

            $version = $verificacion['plan_version'];

            if ($version === null) {
                throw new EgresoInvalidoException('No se identificó la versión del plan del alumno.');
            }

            $egreso = Egreso::create([
                'alumno_id' => $alumno->id,
                'plan_version_id' => $version->id,
                'creditos_requeridos' => $verificacion['creditos_requeridos'],
                'creditos_acumulados' => $verificacion['creditos_acumulados'],
                'requisitos' => $verificacion['requisitos'],
                'registrado_por' => $usuario?->getKey(),
                'registrado_at' => now(),
            ]);

            $this->auditoria->registrar(
                $egreso,
                'egreso_registrado',
                [],
                ['creditos_requeridos' => $egreso->creditos_requeridos, 'creditos_acumulados' => $egreso->creditos_acumulados],
                ['plan_version_id' => $egreso->plan_version_id],
                $usuario,
            );

            return $egreso;
        });
    }

    /**
     * @param  \Illuminate\Support\Collection  $materiasPlan
     * @return array{0: Collection<int, int>, 1: int, 2: int, 3: Collection<int, string>}
     */
    private function cubrimiento(Alumno $alumno, $materiasPlan): array
    {
        $aprobadas = $this->historial->obtenerHistorial($alumno)
            ->filter(fn (array $r) => $r['es_aprobada'] === true && $r['materia'] !== null)
            ->mapWithKeys(fn (array $r) => [$r['materia']['id'] => (int) $r['materia']['creditos']]);

        $equivalencias = Equivalencia::where('alumno_id', $alumno->id)
            ->where('estatus', EstatusEquivalencia::APROBADA)
            ->get();

        $creditosEquivalenciaPlan = 0;
        $faltantesMateria = collect();

        /** @var Materia $materia */
        foreach ($materiasPlan as $materia) {
            if (isset($aprobadas[$materia->id])) {
                continue;
            }

            $equivalencia = $equivalencias->first(
                fn (Equivalencia $e) => $e->materia_id === $materia->id
            );

            if ($equivalencia !== null) {
                // Cubre la materia del plan: se acreditan sus créditos de plan
                // (evita duplicar si el alumno además tiene la materia aprobada).
                $creditosEquivalenciaPlan += (int) $materia->creditos;
                continue;
            }

            $faltantesMateria->push($materia->clave . ' ' . $materia->nombre);
        }

        // Equivalencias sin materia asociada: sólo aportan créditos sueltos.
        $creditosLibres = (int) $equivalencias
            ->where('materia_id', null)
            ->sum('creditos');

        return [$aprobadas, $creditosEquivalenciaPlan, $creditosLibres, $faltantesMateria];
    }

    private function autorizar(string $capacidad, ?User $usuario): void
    {
        $usuario !== null
            ? Gate::forUser($usuario)->authorize($capacidad)
            : Gate::authorize($capacidad);
    }
}
