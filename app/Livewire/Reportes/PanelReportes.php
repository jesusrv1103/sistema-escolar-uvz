<?php

namespace App\Livewire\Reportes;

use App\Models\Docente;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Periodo;
use App\Models\PlanVersion;
use App\Models\Programa;
use App\Services\Reportes\CatalogoReportes;
use App\Services\Reportes\ReporteService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Módulo global de Reportes operativos (2.0-17) — pantalla única `/reportes`.
 *
 * Filtros → resumen → tabla → paginación → «Exportar CSV».
 *
 * Seguridad (misma autorización que el backend, nunca sólo visual):
 * - `mount()`: 403 si el rol no tiene NINGÚN reporte (DOCENTE/ALUMNO no
 *   entran al módulo global) y 403/404 si `?categoria=` está manipulado.
 * - Cada acción vuelve a autorizar sobre el destino (payload manipulado → 403).
 * - `render()` reautoriza la capacidad de la categoría del reporte activo.
 * - Los filtros se validan contra el dominio del reporte; un valor fuera de
 *   dominio se rechaza y NUNCA ensancha la consulta (la lista blanca vive en
 *   `CatalogoReportes`/`ReporteService::normalizar`).
 * - El CSV se autoriza en su controlador (capacidad + `reporte.exportar`).
 *
 * Sin escribir en BD, sin jobs ni cache: sólo consulta.
 */
#[Layout('layouts.app')]
class PanelReportes extends Component
{
    use WithPagination;

    /** Navegación del módulo. */
    public string $categoria = '';
    public string $reporte = '';
    public string $tipo = 'bajas';

    /** Filtros (lista blanca: sólo las claves declaradas por el reporte). */
    public string $periodo = '';
    public string $programa = '';
    public string $estatus = '';
    public string $estatusMatricula = '';
    public string $busqueda = '';
    public string $plan = '';
    public string $grupo = '';
    public string $materia = '';
    public string $docente = '';
    public string $tipoBaja = '';
    public string $metodo = '';
    public string $concepto = '';
    public string $tipoBeca = '';
    public string $desde = '';
    public string $hasta = '';

    public function mount(): void
    {
        $usuario = auth()->user();
        $categorias = CatalogoReportes::categoriasDe($usuario);

        // Sin ninguna capacidad de reporte: el módulo global no existe para
        // este rol (DOCENTE, ALUMNO, invitado ya redirigido por `auth`).
        abort_if($categorias === [], 403, 'No tienes acceso al módulo de reportes.');

        $pedido = request()->query('categoria');

        if ($pedido !== null && $pedido !== '') {
            $categoria = CatalogoReportes::CATEGORIAS[$pedido] ?? null;
            abort_unless($categoria !== null, 404, 'Categoría de reporte inexistente.');
            Gate::authorize($categoria['capacidad']);
            $this->categoria = $pedido;
        } else {
            $this->categoria = array_key_first($categorias);
        }

        $reportes = CatalogoReportes::reportesDe($this->categoria, $usuario);
        $this->reporte = $reportes[0]['slug'];
    }

    /** Cambia de categoría (acción Livewire: se reautoriza SIEMPRE). */
    public function seleccionarCategoria(string $categoria): void
    {
        $definicion = CatalogoReportes::CATEGORIAS[$categoria] ?? null;
        abort_unless($definicion !== null, 404, 'Categoría de reporte inexistente.');
        Gate::authorize($definicion['capacidad']);

        $this->categoria = $categoria;
        $this->reporte = CatalogoReportes::reportesDe($categoria, auth()->user())[0]['slug'];
        $this->tipo = CatalogoReportes::primerTipoMovimiento();
        $this->limpiarFiltros();
    }

    /** Cambia de reporte dentro de la categoría activa (reautorizado). */
    public function seleccionarReporte(string $slug): void
    {
        $definicion = CatalogoReportes::definicion($slug);
        Gate::authorize(CatalogoReportes::CATEGORIAS[$definicion['categoria']]['capacidad']);

        $this->categoria = $definicion['categoria'];
        $this->reporte = $slug;
        $this->tipo = CatalogoReportes::primerTipoMovimiento();
        $this->limpiarFiltros();
    }

    /** Subtipo de R5 (bajas, equivalencias, servicio social, …). */
    public function seleccionarTipo(string $tipo): void
    {
        Gate::authorize(CatalogoReportes::capacidad('movimientos'));
        abort_unless(array_key_exists($tipo, CatalogoReportes::TIPOS_MOVIMIENTO), 404, 'Tipo de movimiento inexistente.');

        $this->tipo = $tipo;
        $this->limpiarFiltros();
    }

    /** Quita todos los filtros (vuelve al conjunto autorizado completo). */
    public function limpiarFiltros(): void
    {
        foreach (array_keys(CatalogoReportes::filtrosDeclarados($this->reporte, ['tipo' => $this->tipo])) as $nombre) {
            $this->{$nombre} = '';
        }

        $this->resetPage();
        $this->resetErrorBag();
    }

    /**
     * Al cambiar un filtro: paginación a 1 y validación del dominio.
     * Un valor fuera de dominio queda marcado como error y la consulta sólo
     * puede devolver menos filas (nunca más de las autorizadas).
     */
    public function updated(string $propiedad): void
    {
        $declarados = CatalogoReportes::filtrosDeclarados($this->reporte, ['tipo' => $this->tipo]);

        if (! array_key_exists($propiedad, $declarados) && $propiedad !== 'tipo') {
            return;
        }

        $this->resetPage();
        $this->validarFiltros();
    }

    public function render()
    {
        // Reautorización en cada render (defensa en profundidad).
        Gate::authorize(CatalogoReportes::capacidad($this->reporte));

        $filtros = $this->filtros();
        $columnas = CatalogoReportes::columnas($this->reporte, $filtros);
        $reportes = app(ReporteService::class);

        $pagina = $reportes->pagina($this->reporte, $filtros);
        $resumen = $reportes->resumen($this->reporte, $filtros);

        $filas = $pagina->getCollection()->map(fn ($registro) => array_map(
            fn (array $columna) => CatalogoReportes::paraTabla($columna, $columna['get']($registro)),
            $columnas,
        ));

        $puedeExportar = Gate::allows('reporte.exportar')
            && Gate::allows(CatalogoReportes::capacidad($this->reporte));

        return view('livewire.reportes.panel-reportes', [
            'categorias' => CatalogoReportes::categoriasDe(auth()->user()),
            'reportes' => CatalogoReportes::reportesDe($this->categoria, auth()->user()),
            'definicion' => CatalogoReportes::definicion($this->reporte, $this->reporte === 'movimientos' ? $this->tipo : null),
            'columnas' => $columnas,
            'filas' => $filas,
            'pagina' => $pagina,
            'resumen' => $resumen,
            'filtros' => $filtros,
            'opciones' => $this->opciones(),
            'tiposMovimiento' => CatalogoReportes::TIPOS_MOVIMIENTO,
            'puedeExportar' => $puedeExportar,
            'urlCsv' => $puedeExportar ? $this->urlCsv($filtros) : null,
        ]);
    }

    // =====================================================================
    // Filtros
    // =====================================================================

    /** Valores crudos de los filtros declarados (para validar). */
    private function filtrosCrudos(): array
    {
        $crudos = [];

        foreach (array_keys(CatalogoReportes::filtrosDeclarados($this->reporte, ['tipo' => $this->tipo])) as $nombre) {
            $crudos[$nombre] = $this->{$nombre} ?? '';
        }

        if ($this->reporte === 'movimientos') {
            $crudos['tipo'] = $this->tipo;
        }

        return $crudos;
    }

    /** Filtros normalizados (lista blanca) listos para la consulta. */
    private function filtros(): array
    {
        return ReporteService::normalizar($this->reporte, $this->filtrosCrudos());
    }

    /** Valida el dominio de los filtros sin dejar escapar excepciones. */
    private function validarFiltros(): void
    {
        $crudos = $this->filtrosCrudos();
        $validador = validator($crudos, CatalogoReportes::reglas($this->reporte, $crudos));

        $this->resetErrorBag();

        if ($validador->fails()) {
            foreach ($validador->errors()->messages() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError($campo, $mensaje);
                }
            }
        }
    }

    /** Enlace de descarga CSV con los filtros actuales (misma autorización). */
    private function urlCsv(array $filtros): string
    {
        $parametros = array_filter($filtros, fn ($valor) => $valor !== null && $valor !== '');
        $parametros['reporte'] = $this->reporte;

        return route('reportes.csv', $parametros);
    }

    // =====================================================================
    // Opciones de los selects de filtro (catálogos reales del sistema)
    // =====================================================================

    /** @return array<string, list<array{valor: mixed, label: string}>> */
    private function opciones(): array
    {
        $enums = CatalogoReportes::enumsDe($this->reporte, ['tipo' => $this->tipo]);
        $opciones = [];

        foreach (array_keys(CatalogoReportes::filtrosDeclarados($this->reporte, ['tipo' => $this->tipo])) as $nombre) {
            $opciones[$nombre] = match ($nombre) {
                'periodo' => Periodo::orderBy('clave')->get(['id', 'clave', 'nombre'])
                    ->map(fn ($p) => ['valor' => $p->id, 'label' => "{$p->clave} — {$p->nombre}"])->all(),
                'programa' => Programa::orderBy('nombre')->get(['id', 'nombre'])
                    ->map(fn ($p) => ['valor' => $p->id, 'label' => $p->nombre])->all(),
                'plan' => PlanVersion::orderByDesc('id')->get(['id', 'version'])
                    ->map(fn ($v) => ['valor' => $v->id, 'label' => "Versión {$v->version}"])->all(),
                'grupo' => Grupo::with(['materia', 'periodo'])->orderBy('clave')->get()
                    ->map(fn ($g) => ['valor' => $g->id, 'label' => trim("{$g->clave} — {$g->materia?->nombre} ({$g->periodo?->clave})")])->all(),
                'materia' => Materia::orderBy('nombre')->get(['id', 'clave', 'nombre'])
                    ->map(fn ($m) => ['valor' => $m->id, 'label' => "{$m->clave} — {$m->nombre}"])->all(),
                'docente' => Docente::orderBy('apellidos')->orderBy('nombre')->get(['id', 'nombre', 'apellidos'])
                    ->map(fn ($d) => ['valor' => $d->id, 'label' => trim("{$d->nombre} {$d->apellidos}")])->all(),
                'tipo' => collect(CatalogoReportes::TIPOS_MOVIMIENTO)
                    ->map(fn ($etiqueta, $valor) => ['valor' => $valor, 'label' => $etiqueta])->values()->all(),
                default => isset($enums[$nombre])
                    ? $this->enumOpciones($enums[$nombre])
                    : [],
            };
        }

        return $opciones;
    }

    /** @return list<array{valor: string, label: string}> */
    private function enumOpciones(string $clase): array
    {
        return array_map(
            fn ($caso) => ['valor' => $caso->value, 'label' => $caso->label()],
            $clase::cases(),
        );
    }
}
