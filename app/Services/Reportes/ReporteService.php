<?php

namespace App\Services\Reportes;

use App\Enums\InscripcionEstatus;
use App\Models\Alumno;
use App\Models\Baja;
use App\Models\BecaAlumno;
use App\Models\Egreso;
use App\Models\Equivalencia;
use App\Models\Inscripcion;
use App\Models\InscripcionMateria;
use App\Models\Pago;
use App\Models\PracticaProfesional;
use App\Models\ServicioSocial;
use App\Models\Titulacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de CONSULTA de los reportes operativos (2.0-17).
 *
 * Arquitectura pedida en la fase: servicio → dataset → Livewire → vista.
 * Aquí viven todos los `SELECT` (nunca en Blade), con:
 *
 * - lista blanca de filtros (sólo los declarados en `CatalogoReportes`);
 * - autorización FUERA de este servicio (Gates en el componente Livewire y
 *   en el controlador CSV: aquí no se decide quién puede ver);
 * - reutilización de reglas canónicas: los estatus salen de los mismos enums
 *   que usan `HistorialAcademicoService` y `ExtraordinarioService`, y la
 *   cartera reproduce la fórmula de `EstadoCuentaService`
 *   (cargos − pagos VALIDADO − ajustes) con prueba de igualdad en tests.
 *
 * Sin N+1 (eager loading), sin índices nuevos, sin cache: sólo lectura.
 */
class ReporteService
{
    /** Filas por página en pantalla (el CSV no depende de la página). */
    public const POR_PAGINA = 15;

    /**
     * Lista blanca de filtros: sólo las claves declaradas para el reporte,
     * con `''` normalizado a `null`. Las claves ajenas se ignoran (nunca se
     * «salta» un filtro declarado, para no ensanchar la consulta).
     *
     * @return array<string, mixed>
     */
    public static function normalizar(string $slug, array $filtros): array
    {
        $limpio = [];

        foreach (CatalogoReportes::filtrosDeclarados($slug, $filtros) as $nombre => $etiqueta) {
            $valor = $filtros[$nombre] ?? null;

            if (is_string($valor)) {
                $valor = trim($valor);
                $valor = $valor === '' ? null : $valor;
            }

            $limpio[$nombre] = $valor;
        }

        // El subtipo de R5 no es un filtro de fila: define columnas y filtros.
        if ($slug === 'movimientos') {
            $tipo = $filtros['tipo'] ?? null;
            $limpio['tipo'] = in_array($tipo, array_keys(CatalogoReportes::TIPOS_MOVIMIENTO), true)
                ? $tipo
                : CatalogoReportes::primerTipoMovimiento();
        }

        return $limpio;
    }

    /** Consulta completa (filtros crudos o normalizados: se normalizan aquí). */
    public function consulta(string $slug, array $filtros): Builder
    {
        $f = self::normalizar($slug, $filtros);

        return match ($slug) {
            'ingreso' => $this->ingreso($f),
            'matricula' => $this->matricula($f),
            'rendimiento' => $this->rendimiento($f),
            'asistencia' => $this->asistencia($f),
            'movimientos' => $this->movimientos($f),
            'cartera' => $this->cartera($f),
            'pagos' => $this->pagos($f),
            'becas' => $this->becas($f),
            default => abort(404, 'Reporte inexistente.'),
        };
    }

    /** Página de filas para la tabla. */
    public function pagina(string $slug, array $filtros, int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        return $this->consulta($slug, $filtros)->paginate($porPagina);
    }

    /**
     * Resumen del conjunto filtrado: sólo conteos y sumas directas.
     *
     * No se calcula ningún promedio agregado: el promedio aprobado es el
     * ponderado por créditos de `HistorialAcademicoService` (por alumno).
     *
     * @return list<array{label: string, valor: string}>
     */
    public function resumen(string $slug, array $filtros): array
    {
        $f = self::normalizar($slug, $filtros);

        return match ($slug) {
            'ingreso' => $this->resumenIngreso($f),
            'matricula' => $this->resumenMatricula($f),
            'rendimiento' => $this->resumenRendimiento($f),
            'asistencia' => $this->resumenAsistencia($f),
            'movimientos' => $this->resumenMovimientos($f),
            'cartera' => $this->resumenCartera($f),
            'pagos' => $this->resumenPagos($f),
            'becas' => $this->resumenBecas($f),
            default => [],
        };
    }

    // =====================================================================
    // Reportes
    // =====================================================================

    /** R1 — Ingreso / inscripciones (proceso de admisión). */
    private function ingreso(array $f): Builder
    {
        return Inscripcion::query()
            ->with(['alumno', 'periodo', 'programa'])
            ->when($f['periodo'], fn ($q) => $q->where('periodo_id', (int) $f['periodo']))
            ->when($f['programa'], fn ($q) => $q->where('programa_id', (int) $f['programa']))
            ->when($f['estatus'], fn ($q) => $q->where('estatus', $f['estatus']))
            ->when($f['busqueda'], fn ($q) => $q->whereHas('alumno', fn ($q) => $this->buscarAlumno($q, $f['busqueda'])))
            ->orderBy('id');
    }

    /** R2 — Matrícula y carga académica. */
    private function matricula(array $f): Builder
    {
        return InscripcionMateria::query()
            ->with([
                'matriculaPeriodo.alumno',
                'matriculaPeriodo.periodo',
                'matriculaPeriodo.programa',
                'matriculaPeriodo.planVersion',
                'grupo.materia',
                'grupo.periodo',
                'grupo.docente',
                'materia',
            ])
            ->when($f['periodo'], fn ($q) => $q->whereHas('matriculaPeriodo', fn ($q) => $q->where('periodo_id', (int) $f['periodo'])))
            ->when($f['programa'], fn ($q) => $q->whereHas('matriculaPeriodo', fn ($q) => $q->where('programa_id', (int) $f['programa'])))
            ->when($f['plan'], fn ($q) => $q->whereHas('matriculaPeriodo', fn ($q) => $q->where('plan_version_id', (int) $f['plan'])))
            ->when($f['estatusMatricula'], fn ($q) => $q->whereHas('matriculaPeriodo', fn ($q) => $q->where('estatus', $f['estatusMatricula'])))
            ->when($f['grupo'], fn ($q) => $q->where('grupo_id', (int) $f['grupo']))
            ->when($f['estatus'], fn ($q) => $q->where('inscripciones_materias.estatus', $f['estatus']))
            ->when($f['busqueda'], fn ($q) => $q->whereHas('matriculaPeriodo.alumno', fn ($q) => $this->buscarAlumno($q, $f['busqueda'])))
            ->orderBy('inscripciones_materias.id');
    }

    /** R3 — Rendimiento académico (mismos estatus canónicos, sin recalcular). */
    private function rendimiento(array $f): Builder
    {
        return InscripcionMateria::query()
            ->with([
                'matriculaPeriodo.alumno',
                'matriculaPeriodo.programa',
                'grupo.materia',
                'grupo.periodo',
                'grupo.docente',
                'materia',
            ])
            ->when($f['periodo'], fn ($q) => $q->whereHas('grupo', fn ($q) => $q->where('periodo_id', (int) $f['periodo'])))
            ->when($f['programa'], fn ($q) => $q->whereHas('matriculaPeriodo', fn ($q) => $q->where('programa_id', (int) $f['programa'])))
            ->when($f['materia'], fn ($q) => $q->where('materia_id', (int) $f['materia']))
            ->when($f['grupo'], fn ($q) => $q->where('grupo_id', (int) $f['grupo']))
            ->when($f['docente'], fn ($q) => $q->whereHas('grupo', fn ($q) => $q->where('docente_id', (int) $f['docente'])))
            ->when($f['estatus'], fn ($q) => $q->where('inscripciones_materias.estatus', $f['estatus']))
            ->when($f['busqueda'], fn ($q) => $q->whereHas('matriculaPeriodo.alumno', fn ($q) => $this->buscarAlumno($q, $f['busqueda'])))
            ->orderBy('inscripciones_materias.id');
    }

    /**
     * R4 — Asistencia: una fila por inscripción con sesiones y presentes.
     *
     * El rango de fechas va en el `ON` del `LEFT JOIN` (no en el `WHERE`):
     * así un alumno sin registros dentro del rango sigue apareciendo con 0
     * sesiones y su porcentaje se muestra como «—», en vez de desaparecer.
     */
    private function asistencia(array $f): Builder
    {
        $desde = $f['desde'];
        $hasta = $f['hasta'];

        return InscripcionMateria::query()
            ->select('inscripciones_materias.*')
            // El alias va DENTRO del `DB::raw`: Laravel descarta la clave del
            // arreglo cuando el valor ya es una expresión cruda.
            ->addSelect([
                'sesiones' => DB::raw('COUNT(asistencias.id) as `sesiones`'),
                'presentes' => DB::raw('COALESCE(SUM(asistencias.presente), 0) as `presentes`'),
            ])
            ->leftJoin('asistencias', function ($union) use ($desde, $hasta) {
                $union->on('asistencias.inscripcion_materia_id', '=', 'inscripciones_materias.id')
                    ->when($desde, fn ($j) => $j->where('asistencias.fecha', '>=', $desde))
                    ->when($hasta, fn ($j) => $j->where('asistencias.fecha', '<=', $hasta));
            })
            ->with(['matriculaPeriodo.alumno', 'grupo.materia', 'grupo.docente', 'grupo.periodo'])
            ->when($f['periodo'], fn ($q) => $q->whereHas('grupo', fn ($q) => $q->where('periodo_id', (int) $f['periodo'])))
            ->when($f['grupo'], fn ($q) => $q->where('grupo_id', (int) $f['grupo']))
            ->when($f['materia'], fn ($q) => $q->where('materia_id', (int) $f['materia']))
            ->when($f['docente'], fn ($q) => $q->whereHas('grupo', fn ($q) => $q->where('docente_id', (int) $f['docente'])))
            ->when($f['busqueda'], fn ($q) => $q->whereHas('matriculaPeriodo.alumno', fn ($q) => $this->buscarAlumno($q, $f['busqueda'])))
            ->groupBy('inscripciones_materias.id')
            ->orderBy('inscripciones_materias.id');
    }

    /**
     * R5 — Movimientos y avance académico (un subtipo por consulta).
     *
     * Seis entidades distintas: NUNCA se mezclan en una sola tabla.
     */
    private function movimientos(array $f): Builder
    {
        $tipo = $f['tipo'];
        $conAlumno = fn (Builder $consulta) => $consulta
            ->when($f['periodo'], fn ($q) => $q->whereHas('alumno.matriculas', fn ($q) => $q->where('periodo_id', (int) $f['periodo'])))
            ->when($f['programa'], fn ($q) => $q->whereHas('alumno.matriculas', fn ($q) => $q->where('programa_id', (int) $f['programa'])))
            ->when($f['busqueda'], fn ($q) => $q->whereHas('alumno', fn ($q) => $this->buscarAlumno($q, $f['busqueda'])));

        return match ($tipo) {
            'bajas' => $conAlumno(Baja::query()->with(['alumno', 'inscripcionMateria.grupo.materia'])
                ->when($f['tipoBaja'], fn ($q) => $q->where('tipo', $f['tipoBaja'])))
                ->orderBy('fecha')->orderBy('id'),

            'equivalencias' => $conAlumno(Equivalencia::query()->with(['alumno', 'materia'])
                ->when($f['estatus'], fn ($q) => $q->where('estatus', $f['estatus'])))
                ->orderBy('fecha_dictamen', 'desc')->orderBy('id'),

            'servicio-social' => $conAlumno(ServicioSocial::query()->with('alumno')
                ->when($f['estatus'], fn ($q) => $q->where('estatus', $f['estatus'])))
                ->orderBy('id'),

            'practicas' => $conAlumno(PracticaProfesional::query()->with('alumno')
                ->when($f['estatus'], fn ($q) => $q->where('estatus', $f['estatus'])))
                ->orderBy('id'),

            'egreso' => $conAlumno(Egreso::query()->with(['alumno', 'planVersion']))
                ->orderBy('registrado_at', 'desc')->orderBy('id'),

            'titulacion' => $conAlumno(Titulacion::query()->with('alumno')
                ->when($f['estatus'], fn ($q) => $q->where('estatus', $f['estatus'])))
                ->orderBy('registrado_at', 'desc')->orderBy('id'),
        };
    }

    /**
     * R6A — Cartera: una fila por alumno con la fórmula canónica.
     *
     *     saldo = cargos − pagos VALIDADO − ajustes (efectoSobreSaldo())
     *
     * Mismo resultado que `EstadoCuentaService::resumen()` (probado en tests),
     * pero en una sola consulta agregada para no hacer N+1 por alumno.
     * `PENDIENTE` y `RECHAZADO` NO cuentan como pago.
     */
    private function cartera(array $f): Builder
    {
        $cargos = $this->subqueryCargos();
        $pagos = $this->subqueryPagosValidados();
        $ajustes = $this->subqueryAjustes();

        return Alumno::query()
            ->select('alumnos.id', 'alumnos.numero_control', 'alumnos.nombre', 'alumnos.apellidos')
            ->addSelect(['cargos' => DB::raw($cargos . ' as `cargos`')])
            ->addSelect(['pagos' => DB::raw($pagos . ' as `pagos`')])
            ->addSelect(['ajustes' => DB::raw($ajustes . ' as `ajustes`')])
            ->addSelect(['saldo' => DB::raw("($cargos - $pagos - $ajustes) as `saldo`")])
            ->when($f['busqueda'], fn ($q) => $this->buscarAlumno($q, $f['busqueda']))
            ->when($f['periodo'], fn ($q) => $q->whereExists(fn ($e) => $e
                ->select(DB::raw(1))
                ->from('conceptos_pago')
                ->join('inscripciones', 'inscripciones.id', '=', 'conceptos_pago.inscripcion_id')
                ->whereColumn('inscripciones.alumno_id', 'alumnos.id')
                ->whereNull('inscripciones.deleted_at')
                ->where('inscripciones.periodo_id', (int) $f['periodo'])))
            ->when($f['programa'], fn ($q) => $q->whereExists(fn ($e) => $e
                ->select(DB::raw(1))
                ->from('conceptos_pago')
                ->join('inscripciones', 'inscripciones.id', '=', 'conceptos_pago.inscripcion_id')
                ->whereColumn('inscripciones.alumno_id', 'alumnos.id')
                ->whereNull('inscripciones.deleted_at')
                ->where('inscripciones.programa_id', (int) $f['programa'])))
            ->orderBy('alumnos.apellidos')
            ->orderBy('alumnos.id');
    }

    /** R6B — Pagos. */
    private function pagos(array $f): Builder
    {
        return Pago::query()
            ->with(['conceptoPago.inscripcion.alumno'])
            ->when($f['periodo'], fn ($q) => $q->whereHas('conceptoPago.inscripcion', fn ($q) => $q->where('periodo_id', (int) $f['periodo'])))
            ->when($f['programa'], fn ($q) => $q->whereHas('conceptoPago.inscripcion', fn ($q) => $q->where('programa_id', (int) $f['programa'])))
            ->when($f['estatus'], fn ($q) => $q->where('estatus', $f['estatus']))
            ->when($f['metodo'], fn ($q) => $q->where('metodo', $f['metodo']))
            ->when($f['concepto'], fn ($q) => $q->whereHas('conceptoPago', fn ($q) => $q->where('tipo', $f['concepto'])))
            ->when($f['desde'], fn ($q) => $q->where('fecha_pago', '>=', $f['desde']))
            ->when($f['hasta'], fn ($q) => $q->where('fecha_pago', '<=', $f['hasta']))
            ->when($f['busqueda'], fn ($q) => $q->whereHas('conceptoPago.inscripcion.alumno', fn ($q) => $this->buscarAlumno($q, $f['busqueda'])))
            ->orderBy('fecha_pago', 'desc')
            ->orderBy('id');
    }

    /** R6C — Becas y descuentos, con el importe ya aplicado en el ledger. */
    private function becas(array $f): Builder
    {
        return BecaAlumno::query()
            ->select('becas_alumnos.*')
            ->addSelect(['aplicado' => DB::raw($this->subqueryAjustesBeca() . ' as `aplicado`')])
            ->with(['beca', 'alumno', 'periodo'])
            ->when($f['periodo'], fn ($q) => $q->where('periodo_id', (int) $f['periodo']))
            ->when($f['estatus'], fn ($q) => $q->where('estatus', $f['estatus']))
            ->when($f['tipoBeca'], fn ($q) => $q->whereHas('beca', fn ($q) => $q->where('tipo', $f['tipoBeca'])))
            ->when($f['busqueda'], fn ($q) => $q->whereHas('alumno', fn ($q) => $this->buscarAlumno($q, $f['busqueda'])))
            ->orderBy('becas_alumnos.id');
    }

    // =====================================================================
    // Búsqueda (PII mínima: nombre, apellidos y número de control)
    // =====================================================================

    private function buscarAlumno(Builder $consulta, string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $consulta;
        }

        return $consulta->where(function ($interna) use ($termino) {
            $interna->where('nombre', 'like', "%{$termino}%")
                ->orWhere('apellidos', 'like', "%{$termino}%")
                ->orWhere('numero_control', 'like', "%{$termino}%");
        });
    }

    // =====================================================================
    // Resúmenes (subquery: funciona igual con `GROUP BY` interno)
    // =====================================================================

    private function agregar(array $f, string $slug, string $agregado): object
    {
        return DB::query()
            ->fromSub($this->consulta($slug, $f), 'reporte')
            ->selectRaw($agregado)
            ->first();
    }

    private function agrupar(array $f, string $slug, string $columna, string $agregado): Collection
    {
        return DB::query()
            ->fromSub($this->consulta($slug, $f), 'reporte')
            ->selectRaw("{$columna} as grupo, {$agregado}")
            ->groupBy($columna)
            ->get();
    }

    /** @return list<array{label: string, valor: string}> */
    private function resumenIngreso(array $f): array
    {
        $chips = [['label' => 'Total', 'valor' => $this->entero($this->agregar($f, 'ingreso', 'COUNT(*) as total')->total)]];

        foreach ($this->agrupar($f, 'ingreso', 'estatus', 'COUNT(*) as total') as $fila) {
            $caso = InscripcionEstatus::tryFrom((string) $fila->grupo);
            $chips[] = ['label' => $caso?->label() ?? (string) $fila->grupo, 'valor' => $this->entero($fila->total)];
        }

        return $chips;
    }

    /** @return list<array{label: string, valor: string}> */
    private function resumenMatricula(array $f): array
    {
        $totales = $this->agregar($f, 'matricula', 'COUNT(*) as total, COUNT(DISTINCT matricula_periodo_id) as alumnos');

        $chips = [
            ['label' => 'Inscripciones de carga', 'valor' => $this->entero($totales->total)],
            ['label' => 'Alumnos', 'valor' => $this->entero($totales->alumnos)],
        ];

        foreach ($this->agrupar($f, 'matricula', 'estatus', 'COUNT(*) as total') as $fila) {
            $chips[] = [
                'label' => \App\Enums\EstatusInscripcionMateria::tryFrom((string) $fila->grupo)?->label() ?? (string) $fila->grupo,
                'valor' => $this->entero($fila->total),
            ];
        }

        return $chips;
    }

    /**
     * Conteos directos de los estatus aprobados (D-N4 / RB-A5).
     * Sin promedio agregado: eso sería recalcular una regla canónica.
     *
     * @return list<array{label: string, valor: string}>
     */
    private function resumenRendimiento(array $f): array
    {
        $conteos = $this->agrupar($f, 'rendimiento', 'estatus', 'COUNT(*) as total')
            ->mapWithKeys(fn ($fila) => [(string) $fila->grupo => (int) $fila->total]);

        return [
            ['label' => 'Inscritos', 'valor' => $this->entero($conteos->sum())],
            ['label' => 'Aprobados', 'valor' => $this->entero($conteos->get('APROBADA', 0))],
            ['label' => 'Reprobados', 'valor' => $this->entero($conteos->get('REPROBADA', 0))],
            ['label' => 'Bajas', 'valor' => $this->entero($conteos->get('BAJA', 0))],
            ['label' => 'Extraordinarios', 'valor' => $this->entero($conteos->get('EXTRAORDINARIO', 0))],
        ];
    }

    /** @return list<array{label: string, valor: string}> */
    private function resumenAsistencia(array $f): array
    {
        $totales = $this->agregar($f, 'asistencia', 'COUNT(*) as alumnos, COALESCE(SUM(sesiones), 0) as sesiones, COALESCE(SUM(presentes), 0) as presentes');

        $sesiones = (int) $totales->sesiones;
        $presentes = (int) $totales->presentes;

        return [
            ['label' => 'Alumnos', 'valor' => $this->entero($totales->alumnos)],
            ['label' => 'Registros', 'valor' => $this->entero($sesiones)],
            ['label' => 'Presentes', 'valor' => $this->entero($presentes)],
            ['label' => 'Ausentes', 'valor' => $this->entero($sesiones - $presentes)],
            ['label' => '% de asistencia', 'valor' => $sesiones > 0
                ? number_format($presentes * 100 / $sesiones, 1, '.', '') . ' %'
                : '—'],
        ];
    }

    /** @return list<array{label: string, valor: string}> */
    private function resumenMovimientos(array $f): array
    {
        return [['label' => 'Total', 'valor' => $this->entero($this->agregar($f, 'movimientos', 'COUNT(*) as total')->total)]];
    }

    /** @return list<array{label: string, valor: string}> */
    private function resumenCartera(array $f): array
    {
        $t = $this->agregar($f, 'cartera', 'COUNT(*) as alumnos, COALESCE(SUM(cargos), 0) as cargos, COALESCE(SUM(pagos), 0) as pagos, COALESCE(SUM(ajustes), 0) as ajustes, COALESCE(SUM(saldo), 0) as saldo');

        return [
            ['label' => 'Alumnos', 'valor' => $this->entero($t->alumnos)],
            ['label' => 'Cargos', 'valor' => $this->moneda($t->cargos)],
            ['label' => 'Pagos validados', 'valor' => $this->moneda($t->pagos)],
            ['label' => 'Ajustes', 'valor' => $this->moneda($t->ajustes)],
            ['label' => 'Saldo exigible', 'valor' => $this->moneda($t->saldo)],
        ];
    }

    /** @return list<array{label: string, valor: string}> */
    private function resumenPagos(array $f): array
    {
        $t = $this->agregar($f, 'pagos', 'COUNT(*) as total, COALESCE(SUM(monto), 0) as monto');

        $chips = [
            ['label' => 'Movimientos', 'valor' => $this->entero($t->total)],
            ['label' => 'Monto total', 'valor' => $this->moneda($t->monto)],
        ];

        foreach ($this->agrupar($f, 'pagos', 'estatus', 'COUNT(*) as total, COALESCE(SUM(monto), 0) as monto') as $fila) {
            $caso = \App\Enums\EstatusPago::tryFrom((string) $fila->grupo);
            $chips[] = ['label' => ($caso?->label() ?? (string) $fila->grupo) . ' (' . $this->entero($fila->total) . ')', 'valor' => $this->moneda($fila->monto)];
        }

        return $chips;
    }

    /** @return list<array{label: string, valor: string}> */
    private function resumenBecas(array $f): array
    {
        $t = $this->agregar($f, 'becas', 'COUNT(*) as total, COALESCE(SUM(aplicado), 0) as aplicado');

        $chips = [
            ['label' => 'Becas', 'valor' => $this->entero($t->total)],
            ['label' => 'Aplicado en ledger', 'valor' => $this->moneda($t->aplicado)],
        ];

        foreach ($this->agrupar($f, 'becas', 'estatus', 'COUNT(*) as total') as $fila) {
            $caso = \App\Enums\EstatusBeca::tryFrom((string) $fila->grupo);
            $chips[] = ['label' => $caso?->label() ?? (string) $fila->grupo, 'valor' => $this->entero($fila->total)];
        }

        return $chips;
    }

    // =====================================================================
    // Auxiliares
    // =====================================================================

    private function entero(mixed $valor): string
    {
        return number_format((int) $valor);
    }

    private function moneda(mixed $valor): string
    {
        return number_format((float) $valor, 2);
    }

    private function subqueryCargos(): string
    {
        return '(SELECT COALESCE(SUM(cp.monto_total), 0) FROM conceptos_pago cp
                 INNER JOIN inscripciones i ON i.id = cp.inscripcion_id
                 WHERE i.alumno_id = alumnos.id AND i.deleted_at IS NULL)';
    }

    private function subqueryPagosValidados(): string
    {
        return '(SELECT COALESCE(SUM(pg.monto), 0) FROM pagos pg
                 INNER JOIN conceptos_pago cp ON cp.id = pg.concepto_pago_id
                 INNER JOIN inscripciones i ON i.id = cp.inscripcion_id
                 WHERE i.alumno_id = alumnos.id AND i.deleted_at IS NULL
                   AND pg.estatus = \'VALIDADO\')';
    }

    /** Réplica SQL de `AjusteFinanciero::efectoSobreSaldo()` (mismos 2 tipos). */
    private function subqueryAjustes(): string
    {
        return '(SELECT COALESCE(SUM(CASE aj.tipo
                    WHEN \'DESCUENTO_BECA\' THEN aj.monto
                    WHEN \'REVERSO_DESCUENTO_BECA\' THEN -aj.monto
                    ELSE 0 END), 0)
                 FROM ajustes_financieros aj
                 WHERE aj.alumno_id = alumnos.id AND aj.deleted_at IS NULL)';
    }

    private function subqueryAjustesBeca(): string
    {
        return '(SELECT COALESCE(SUM(CASE aj.tipo
                    WHEN \'DESCUENTO_BECA\' THEN aj.monto
                    WHEN \'REVERSO_DESCUENTO_BECA\' THEN -aj.monto
                    ELSE 0 END), 0)
                 FROM ajustes_financieros aj
                 WHERE aj.beca_alumno_id = becas_alumnos.id AND aj.deleted_at IS NULL)';
    }
}
