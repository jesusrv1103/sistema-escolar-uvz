<?php

namespace App\Services\Reportes;

use App\Enums\AlcanceBeca;
use App\Enums\EstatusBeca;
use App\Enums\EstatusEquivalencia;
use App\Enums\EstatusInscripcionMateria;
use App\Enums\EstatusMatriculaPeriodo;
use App\Enums\EstatusPago;
use App\Enums\EstatusPracticaProfesional;
use App\Enums\EstatusServicioSocial;
use App\Enums\EstatusTitulacion;
use App\Enums\InscripcionEstatus;
use App\Enums\MetodoPago;
use App\Enums\TipoBaja;
use App\Enums\TipoBeca;
use App\Enums\TipoConcepto;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;

/**
 * Catálogo ÚNICO de los reportes operativos (2.0-17).
 *
 * Centraliza lo que comparten la pantalla (`PanelReportes`) y el CSV
 * (`CsvReporteController`), de modo que **tabla y exportación ven exactamente
 * lo mismo**: mismas columnas, mismos filtros, misma autorización.
 *
 * Reglas de la fase:
 * - Sólo consulta operativa: filtros → resumen → tabla → paginación → CSV.
 * - Nada de BI, gráficas, KPIs inventados ni diseñador de reportes.
 * - Los valores visibles SIEMPRE pasan por `Enum::label()` (§18 de reglas);
 *   el `value` técnico viaja sólo en filtros y consultas.
 * - PII mínima: número de control + nombre. Sin CURP, correo, teléfono,
 *   domicilio ni información médica (§J del diseño).
 */
final class CatalogoReportes
{
    /** Categorías del módulo => etiqueta + capacidad Gate que las habilita. */
    public const CATEGORIAS = [
        'ingreso' => ['label' => 'Ingreso', 'capacidad' => 'reporte_ingreso.ver', 'icono' => 'clipboard'],
        'academicos' => ['label' => 'Académicos', 'capacidad' => 'reporte_academico.ver', 'icono' => 'academic-cap'],
        'financieros' => ['label' => 'Financieros', 'capacidad' => 'reporte_financiero.ver', 'icono' => 'banknotes'],
    ];

    /** Subtipos de R5 (cada uno con sus propias columnas: nunca se mezclan). */
    public const TIPOS_MOVIMIENTO = [
        'bajas' => 'Bajas',
        'equivalencias' => 'Equivalencias',
        'servicio-social' => 'Servicio social',
        'practicas' => 'Prácticas profesionales',
        'egreso' => 'Egreso',
        'titulacion' => 'Titulación',
    ];

    /**
     * Vocabulario de filtros: etiqueta base + regla de validación Laravel.
     * Los valores de dominio (`in:`) los agrega cada reporte/subtipo.
     */
    private const FILTROS = [
        'periodo' => ['label' => 'Periodo', 'regla' => 'nullable|integer|min:1'],
        'programa' => ['label' => 'Programa', 'regla' => 'nullable|integer|min:1'],
        'estatus' => ['label' => 'Estatus', 'regla' => 'nullable|string|max:40'],
        'busqueda' => ['label' => 'Buscar alumno', 'regla' => 'nullable|string|max:100'],
        'plan' => ['label' => 'Versión de plan', 'regla' => 'nullable|integer|min:1'],
        'grupo' => ['label' => 'Grupo', 'regla' => 'nullable|integer|min:1'],
        'materia' => ['label' => 'Materia', 'regla' => 'nullable|integer|min:1'],
        'docente' => ['label' => 'Docente', 'regla' => 'nullable|integer|min:1'],
        'estatusMatricula' => ['label' => 'Estatus de matrícula', 'regla' => 'nullable|string|max:40'],
        'tipo' => ['label' => 'Tipo de movimiento', 'regla' => 'nullable|string|max:40'],
        'tipoBaja' => ['label' => 'Tipo de baja', 'regla' => 'nullable|string|max:40'],
        'metodo' => ['label' => 'Método de pago', 'regla' => 'nullable|string|max:40'],
        'concepto' => ['label' => 'Tipo de concepto', 'regla' => 'nullable|string|max:40'],
        'tipoBeca' => ['label' => 'Tipo de beca', 'regla' => 'nullable|string|max:40'],
        'desde' => ['label' => 'Desde', 'regla' => 'nullable|date'],
        'hasta' => ['label' => 'Hasta', 'regla' => 'nullable|date'],
    ];

    /** Categorías que el usuario PUEDE consultar (matriz aprobada de 2.0-17). */
    public static function categoriasDe(object $usuario): array
    {
        $disponibles = [];
        foreach (self::CATEGORIAS as $clave => $categoria) {
            if (Gate::forUser($usuario)->allows($categoria['capacidad'])) {
                $disponibles[$clave] = $categoria;
            }
        }

        return $disponibles;
    }

    /** Slug de la primera categoría autorizada del usuario (o null). */
    public static function primeraCategoria(object $usuario): ?string
    {
        $disponibles = self::categoriasDe($usuario);

        return array_key_first($disponibles) ?: null;
    }

    /**
     * Reportes de una categoría autorizada (para las pestañas del módulo).
     *
     * @return list<array{slug: string, nombre: string, descripcion: string}>
     */
    public static function reportesDe(string $categoria, object $usuario): array
    {
        $autorizada = self::CATEGORIAS[$categoria] ?? null;
        if ($autorizada === null || ! Gate::forUser($usuario)->allows($autorizada['capacidad'])) {
            return [];
        }

        $reportes = [];
        foreach (self::definiciones() as $def) {
            if ($def['categoria'] === $categoria) {
                $reportes[] = [
                    'slug' => $def['slug'],
                    'nombre' => $def['nombre'],
                    'descripcion' => $def['descripcion'],
                ];
            }
        }

        return $reportes;
    }

    public static function existe(string $slug): bool
    {
        return array_key_exists($slug, self::definiciones());
    }

    /**
     * Definición completa de un reporte (slug desconocido → 404).
     *
     * @param  string|null  $tipo  subtipo de `movimientos` (R5)
     * @return array<string, mixed>
     */
    public static function definicion(string $slug, ?string $tipo = null): array
    {
        $definiciones = self::definiciones();
        abort_unless(array_key_exists($slug, $definiciones), 404, 'Reporte inexistente.');

        $def = $definiciones[$slug];

        if ($slug === 'movimientos') {
            $tipo = in_array($tipo, array_keys(self::TIPOS_MOVIMIENTO), true) ? $tipo : self::primerTipoMovimiento();
            $def['tipo'] = $tipo;
            $def['filtros'] = self::filtrosMovimiento($tipo);
            $def['enums'] = self::enumsMovimiento($tipo);
            $def['columnas'] = self::columnasMovimiento($tipo);
        }

        return $def;
    }

    /** Capacidad Gate que habilita el reporte (ADMIN pasa por Gate::before). */
    public static function capacidad(string $slug): string
    {
        return self::CATEGORIAS[self::definicion($slug)['categoria']]['capacidad'];
    }

    /**
     * Columnas del dataset: etiqueta + tipo de celda + accesor.
     *
     * `get` recibe la fila (modelo Eloquent o fila agregada) y devuelve el
     * valor crudo; el formato lo aplican `paraTabla()` y `paraCsv()`.
     *
     * @return list<array{clave: string, label: string, tipo: string, get: \Closure}>
     */
    public static function columnas(string $slug, array $filtros = []): array
    {
        return self::definicion($slug, $filtros['tipo'] ?? null)['columnas'];
    }

    /**
     * Filtros declarados para el reporte: nombre => etiqueta visible.
     *
     * @return array<string, string>
     */
    public static function filtrosDeclarados(string $slug, array $filtros = []): array
    {
        return self::definicion($slug, $filtros['tipo'] ?? null)['filtros'];
    }

    /**
     * Enums de dominio de los filtros declarados: nombre => clase de enum.
     * El componente Livewire arma los `<select>` con esto (etiqueta en
     * español vía `Enum::label()`), sin duplicar listas.
     *
     * @return array<string, class-string<\BackedEnum>>
     */
    public static function enumsDe(string $slug, array $filtros = []): array
    {
        return self::definicion($slug, $filtros['tipo'] ?? null)['enums'];
    }

    /**
     * Reglas de validación de los filtros declarados (Laravel Validator).
     *
     * Las claves fuera de la lista blanca no generan regla: se ignoran y nunca
     * amplían el alcance. Los valores fuera de dominio sí se RECHAZAN (422),
     * nunca se «salta» un filtro para no ensanchar la consulta.
     *
     * @return array<string, string>
     */
    public static function reglas(string $slug, array $filtros = []): array
    {
        $def = self::definicion($slug, $filtros['tipo'] ?? null);
        $reglas = [];

        // El subtipo de R5 también se valida contra su dominio: un `tipo`
        // manipulado se rechaza (422 / error de validación), nunca se omite.
        if ($slug === 'movimientos') {
            $reglas['tipo'] = 'nullable|string|in:' . implode(',', array_keys(self::TIPOS_MOVIMIENTO));
        }

        foreach ($def['filtros'] as $nombre => $etiqueta) {
            $regla = self::FILTROS[$nombre]['regla'];
            $enum = $def['enums'][$nombre] ?? null;

            if ($enum !== null) {
                $regla .= '|in:' . implode(',', array_map(fn ($caso) => $caso->value, $enum::cases()));
            }

            $reglas[$nombre] = $regla;
        }

        if (isset($reglas['desde'], $reglas['hasta'])) {
            $reglas['hasta'] .= '|after_or_equal:desde';
        }

        return $reglas;
    }

    /** Etiqueta visible de un filtro (para el UI y para tests). */
    public static function etiquetaFiltro(string $nombre): string
    {
        return self::FILTROS[$nombre]['label'] ?? $nombre;
    }

    /**
     * Celda lista para la tabla (formato visible, §18).
     *
     * @return array{texto: string, tipo: string, tono: ?string}
     */
    public static function paraTabla(array $columna, mixed $valor): array
    {
        $esEstado = $columna['tipo'] === 'estado' && $valor !== null && $valor !== '';

        return [
            'texto' => self::formatear($columna, $valor, true),
            'tipo' => $columna['tipo'],
            'tono' => $esEstado ? self::tono((string) $valor) : null,
        ];
    }

    /** Celda lista para el CSV (mismo dato, formato plano). */
    public static function paraCsv(array $columna, mixed $valor): string
    {
        return self::formatear($columna, $valor, false);
    }

    /**
     * Tono de badge para valores de estado conocidos (sólo presentación).
     */
    public static function tono(string $valor): string
    {
        return match ($valor) {
            'APROBADA', 'VALIDADO', 'VALIDADA', 'ENROLLED', 'TITULADO', 'ACTIVA', 'PAGADO' => 'success',
            'REPROBADA', 'RECHAZADO', 'RECHAZADA', 'CANCELLED', 'CANCELADO', 'BAJA_DEFINITIVA', 'DEFINITIVA' => 'danger',
            'PENDIENTE', 'EXTRAORDINARIO', 'CURSANDO', 'EN_PROCESO', 'EN_TRAMITE', 'BAJA_TEMPORAL',
            'PENDING_PAYMENT', 'PAYMENT_REVIEW', 'PAYMENT_VALIDATED', 'PENDING_DOCUMENTS',
            'DOCUMENT_REVIEW', 'FINAL_REVIEW', 'TEMPORAL' => 'warning',
            'DRAFT', 'DATA_COMPLETE', 'MATERIA' => 'info',
            default => 'neutral',
        };
    }

    public static function primerTipoMovimiento(): string
    {
        return array_key_first(self::TIPOS_MOVIMIENTO);
    }

    // =====================================================================
    // Definiciones por reporte
    // =====================================================================

    /** @return array<string, array<string, mixed>> */
    private static function definiciones(): array
    {
        return [
            'ingreso' => [
                'slug' => 'ingreso',
                'categoria' => 'ingreso',
                'nombre' => 'Inscripciones y aspirantes',
                'descripcion' => 'Seguimiento operativo de aspirantes e inscripciones por periodo, programa y estatus.',
                'filtros' => [
                    'periodo' => 'Periodo (inscripción)',
                    'programa' => 'Programa',
                    'estatus' => 'Estatus de inscripción',
                    'busqueda' => 'Buscar alumno',
                ],
                'enums' => [
                    'estatus' => InscripcionEstatus::class,
                ],
                'columnas' => [
                    ['clave' => 'numero_control', 'label' => 'N. de control', 'tipo' => 'texto', 'get' => fn ($f) => $f->alumno?->numero_control],
                    ['clave' => 'alumno', 'label' => 'Alumno', 'tipo' => 'texto', 'get' => fn ($f) => $f->alumno?->nombre_completo],
                    ['clave' => 'programa', 'label' => 'Programa', 'tipo' => 'texto', 'get' => fn ($f) => $f->programa?->nombre],
                    ['clave' => 'periodo', 'label' => 'Periodo', 'tipo' => 'texto', 'get' => fn ($f) => $f->periodo?->clave],
                    ['clave' => 'estatus', 'label' => 'Estatus', 'tipo' => 'estado', 'get' => fn ($f) => $f->estatus instanceof \BackedEnum ? $f->estatus->value : (string) $f->estatus],
                    ['clave' => 'alta', 'label' => 'Alta', 'tipo' => 'fecha', 'get' => fn ($f) => $f->created_at],
                    ['clave' => 'finalizada', 'label' => 'Finalizada', 'tipo' => 'fecha', 'get' => fn ($f) => $f->finalizada_at],
                ],
            ],

            'matricula' => [
                'slug' => 'matricula',
                'categoria' => 'academicos',
                'nombre' => 'Matrícula y carga académica',
                'descripcion' => 'Alumnos matriculados y carga por periodo, plan, grupo y estatus.',
                'filtros' => [
                    'periodo' => 'Periodo (matrícula)',
                    'programa' => 'Programa',
                    'plan' => 'Versión de plan',
                    'grupo' => 'Grupo',
                    'estatusMatricula' => 'Estatus de matrícula',
                    'estatus' => 'Estatus de carga',
                    'busqueda' => 'Buscar alumno',
                ],
                'enums' => [
                    'estatus' => EstatusInscripcionMateria::class,
                    'estatusMatricula' => EstatusMatriculaPeriodo::class,
                ],
                'columnas' => [
                    ['clave' => 'numero_control', 'label' => 'N. de control', 'tipo' => 'texto', 'get' => fn ($f) => $f->matriculaPeriodo?->alumno?->numero_control],
                    ['clave' => 'alumno', 'label' => 'Alumno', 'tipo' => 'texto', 'get' => fn ($f) => $f->matriculaPeriodo?->alumno?->nombre_completo],
                    ['clave' => 'programa', 'label' => 'Programa', 'tipo' => 'texto', 'get' => fn ($f) => $f->matriculaPeriodo?->programa?->nombre],
                    ['clave' => 'periodo', 'label' => 'Periodo', 'tipo' => 'texto', 'get' => fn ($f) => $f->matriculaPeriodo?->periodo?->clave],
                    ['clave' => 'plan', 'label' => 'Versión de plan', 'tipo' => 'texto', 'get' => fn ($f) => $f->matriculaPeriodo?->planVersion?->version],
                    ['clave' => 'grupo', 'label' => 'Grupo', 'tipo' => 'texto', 'get' => fn ($f) => $f->grupo?->clave],
                    ['clave' => 'materia', 'label' => 'Materia', 'tipo' => 'texto', 'get' => fn ($f) => $f->grupo?->materia?->nombre ?? $f->materia?->nombre],
                    ['clave' => 'estatus', 'label' => 'Estatus de carga', 'tipo' => 'estado', 'get' => fn ($f) => $f->estatus instanceof \BackedEnum ? $f->estatus->value : (string) $f->estatus],
                ],
            ],

            'rendimiento' => [
                'slug' => 'rendimiento',
                'categoria' => 'academicos',
                'nombre' => 'Rendimiento académico',
                'descripcion' => 'Resultados por materia, grupo y docente con los estatus ya aprobados (D-N4 / RB-A5).',
                'filtros' => [
                    'periodo' => 'Periodo (grupo)',
                    'programa' => 'Programa',
                    'materia' => 'Materia',
                    'grupo' => 'Grupo',
                    'docente' => 'Docente',
                    'estatus' => 'Estatus',
                    'busqueda' => 'Buscar alumno',
                ],
                'enums' => [
                    'estatus' => EstatusInscripcionMateria::class,
                ],
                'columnas' => [
                    ['clave' => 'numero_control', 'label' => 'N. de control', 'tipo' => 'texto', 'get' => fn ($f) => $f->matriculaPeriodo?->alumno?->numero_control],
                    ['clave' => 'alumno', 'label' => 'Alumno', 'tipo' => 'texto', 'get' => fn ($f) => $f->matriculaPeriodo?->alumno?->nombre_completo],
                    ['clave' => 'programa', 'label' => 'Programa', 'tipo' => 'texto', 'get' => fn ($f) => $f->matriculaPeriodo?->programa?->nombre],
                    ['clave' => 'periodo', 'label' => 'Periodo', 'tipo' => 'texto', 'get' => fn ($f) => $f->grupo?->periodo?->clave],
                    ['clave' => 'materia', 'label' => 'Materia', 'tipo' => 'texto', 'get' => fn ($f) => $f->grupo?->materia?->nombre],
                    ['clave' => 'grupo', 'label' => 'Grupo', 'tipo' => 'texto', 'get' => fn ($f) => $f->grupo?->clave],
                    ['clave' => 'docente', 'label' => 'Docente', 'tipo' => 'texto', 'get' => fn ($f) => trim(($f->grupo?->docente?->nombre ?? '') . ' ' . ($f->grupo?->docente?->apellidos ?? '')) ?: null],
                    ['clave' => 'estatus', 'label' => 'Estatus', 'tipo' => 'estado', 'get' => fn ($f) => $f->estatus instanceof \BackedEnum ? $f->estatus->value : (string) $f->estatus],
                    ['clave' => 'calificacion', 'label' => 'Calificación final', 'tipo' => 'calificacion', 'get' => fn ($f) => $f->calificacion_final],
                ],
            ],

            'asistencia' => [
                'slug' => 'asistencia',
                'categoria' => 'academicos',
                'nombre' => 'Asistencia',
                'descripcion' => 'Asistencia por grupo, materia y periodo: sesiones registradas y porcentaje de presencia.',
                'filtros' => [
                    'periodo' => 'Periodo (grupo)',
                    'grupo' => 'Grupo',
                    'materia' => 'Materia',
                    'docente' => 'Docente',
                    'desde' => 'Desde',
                    'hasta' => 'Hasta',
                    'busqueda' => 'Buscar alumno',
                ],
                'enums' => [],
                'columnas' => [
                    ['clave' => 'numero_control', 'label' => 'N. de control', 'tipo' => 'texto', 'get' => fn ($f) => $f->matriculaPeriodo?->alumno?->numero_control],
                    ['clave' => 'alumno', 'label' => 'Alumno', 'tipo' => 'texto', 'get' => fn ($f) => $f->matriculaPeriodo?->alumno?->nombre_completo],
                    ['clave' => 'grupo', 'label' => 'Grupo', 'tipo' => 'texto', 'get' => fn ($f) => $f->grupo?->clave],
                    ['clave' => 'materia', 'label' => 'Materia', 'tipo' => 'texto', 'get' => fn ($f) => $f->grupo?->materia?->nombre],
                    ['clave' => 'docente', 'label' => 'Docente', 'tipo' => 'texto', 'get' => fn ($f) => trim(($f->grupo?->docente?->nombre ?? '') . ' ' . ($f->grupo?->docente?->apellidos ?? '')) ?: null],
                    ['clave' => 'sesiones', 'label' => 'Sesiones', 'tipo' => 'entero', 'get' => fn ($f) => (int) $f->sesiones],
                    ['clave' => 'presentes', 'label' => 'Presentes', 'tipo' => 'entero', 'get' => fn ($f) => (int) $f->presentes],
                    ['clave' => 'ausentes', 'label' => 'Ausentes', 'tipo' => 'entero', 'get' => fn ($f) => (int) $f->sesiones - (int) $f->presentes],
                    ['clave' => 'porcentaje', 'label' => '% de asistencia', 'tipo' => 'porcentaje', 'get' => fn ($f) => $f->sesiones > 0 ? round($f->presentes * 100 / $f->sesiones, 1) : null],
                ],
            ],

            'movimientos' => [
                'slug' => 'movimientos',
                'categoria' => 'academicos',
                'nombre' => 'Movimientos y avance académico',
                'descripcion' => 'Bajas, equivalencias, servicio social, prácticas, egreso y titulación (subreportes por tipo).',
                'filtros' => [],
                'enums' => [],
                'columnas' => [],
                'tipo' => 'bajas',
            ],

            'cartera' => [
                'slug' => 'cartera',
                'categoria' => 'financieros',
                'nombre' => 'Estado de cartera',
                'descripcion' => 'Cargos, pagos validados, ajustes y saldo exigible con la fórmula canónica de EstadoCuentaService.',
                'filtros' => [
                    'periodo' => 'Periodo (con cargo)',
                    'programa' => 'Programa',
                    'busqueda' => 'Buscar alumno',
                ],
                'enums' => [],
                'columnas' => [
                    ['clave' => 'numero_control', 'label' => 'N. de control', 'tipo' => 'texto', 'get' => fn ($f) => $f->numero_control],
                    ['clave' => 'alumno', 'label' => 'Alumno', 'tipo' => 'texto', 'get' => fn ($f) => trim("{$f->nombre} {$f->apellidos}") ?: null],
                    ['clave' => 'cargos', 'label' => 'Cargos', 'tipo' => 'moneda', 'get' => fn ($f) => (float) $f->cargos],
                    ['clave' => 'pagos', 'label' => 'Pagos validados', 'tipo' => 'moneda', 'get' => fn ($f) => (float) $f->pagos],
                    ['clave' => 'ajustes', 'label' => 'Ajustes', 'tipo' => 'moneda', 'get' => fn ($f) => (float) $f->ajustes],
                    ['clave' => 'saldo', 'label' => 'Saldo exigible', 'tipo' => 'moneda', 'get' => fn ($f) => (float) $f->saldo],
                ],
            ],

            'pagos' => [
                'slug' => 'pagos',
                'categoria' => 'financieros',
                'nombre' => 'Pagos',
                'descripcion' => 'Movimientos de pago con folio, concepto, método, estatus y fecha.',
                'filtros' => [
                    'periodo' => 'Periodo',
                    'programa' => 'Programa',
                    'estatus' => 'Estatus',
                    'metodo' => 'Método de pago',
                    'concepto' => 'Tipo de concepto',
                    'desde' => 'Desde',
                    'hasta' => 'Hasta',
                    'busqueda' => 'Buscar alumno',
                ],
                'enums' => [
                    'estatus' => EstatusPago::class,
                    'metodo' => MetodoPago::class,
                    'concepto' => TipoConcepto::class,
                ],
                'columnas' => [
                    ['clave' => 'folio', 'label' => 'Folio', 'tipo' => 'texto', 'get' => fn ($f) => $f->folio],
                    ['clave' => 'numero_control', 'label' => 'N. de control', 'tipo' => 'texto', 'get' => fn ($f) => $f->conceptoPago?->inscripcion?->alumno?->numero_control],
                    ['clave' => 'alumno', 'label' => 'Alumno', 'tipo' => 'texto', 'get' => fn ($f) => $f->conceptoPago?->inscripcion?->alumno?->nombre_completo],
                    ['clave' => 'concepto', 'label' => 'Concepto', 'tipo' => 'texto', 'get' => fn ($f) => $f->conceptoPago?->concepto],
                    ['clave' => 'monto', 'label' => 'Monto', 'tipo' => 'moneda', 'get' => fn ($f) => (float) $f->monto],
                    ['clave' => 'metodo', 'label' => 'Método', 'tipo' => 'estado', 'get' => fn ($f) => $f->metodo instanceof \BackedEnum ? $f->metodo->value : (string) $f->metodo],
                    ['clave' => 'estatus', 'label' => 'Estatus', 'tipo' => 'estado', 'get' => fn ($f) => $f->estatus instanceof \BackedEnum ? $f->estatus->value : (string) $f->estatus],
                    ['clave' => 'fecha', 'label' => 'Fecha', 'tipo' => 'fecha', 'get' => fn ($f) => $f->fecha_pago],
                ],
            ],

            'becas' => [
                'slug' => 'becas',
                'categoria' => 'financieros',
                'nombre' => 'Becas y descuentos',
                'descripcion' => 'Becas asignadas por alumno y periodo con su importe ya aplicado en el ledger de ajustes.',
                'filtros' => [
                    'periodo' => 'Periodo',
                    'estatus' => 'Estatus',
                    'tipoBeca' => 'Tipo de beca',
                    'busqueda' => 'Buscar alumno',
                ],
                'enums' => [
                    'estatus' => EstatusBeca::class,
                    'tipoBeca' => TipoBeca::class,
                ],
                'columnas' => [
                    ['clave' => 'numero_control', 'label' => 'N. de control', 'tipo' => 'texto', 'get' => fn ($f) => $f->alumno?->numero_control],
                    ['clave' => 'alumno', 'label' => 'Alumno', 'tipo' => 'texto', 'get' => fn ($f) => $f->alumno?->nombre_completo],
                    ['clave' => 'beca', 'label' => 'Beca', 'tipo' => 'texto', 'get' => fn ($f) => $f->beca?->nombre],
                    ['clave' => 'periodo', 'label' => 'Periodo', 'tipo' => 'texto', 'get' => fn ($f) => $f->periodo?->clave],
                    ['clave' => 'tipo', 'label' => 'Tipo', 'tipo' => 'estado', 'get' => fn ($f) => $f->beca?->tipo instanceof \BackedEnum ? $f->beca->tipo->value : (string) $f->beca?->tipo],
                    ['clave' => 'alcance', 'label' => 'Alcance', 'tipo' => 'estado', 'get' => fn ($f) => $f->beca?->alcance instanceof \BackedEnum ? $f->beca->alcance->value : (string) $f->beca?->alcance],
                    ['clave' => 'valor', 'label' => 'Valor', 'tipo' => 'valor', 'get' => fn ($f) => $f->beca?->valor],
                    ['clave' => 'estatus', 'label' => 'Estatus', 'tipo' => 'estado', 'get' => fn ($f) => $f->estatus instanceof \BackedEnum ? $f->estatus->value : (string) $f->estatus],
                    ['clave' => 'aplicado', 'label' => 'Aplicado en ledger', 'tipo' => 'moneda', 'get' => fn ($f) => (float) $f->aplicado],
                ],
            ],
        ];
    }

    // =====================================================================
    // R5 — subreportes de movimientos
    // =====================================================================

    /** @return array<string, string> */
    private static function filtrosMovimiento(string $tipo): array
    {
        $comunes = ['periodo' => 'Periodo (matrícula)', 'programa' => 'Programa', 'busqueda' => 'Buscar alumno'];

        return match ($tipo) {
            'bajas' => ['tipoBaja' => 'Tipo de baja'] + $comunes,
            'equivalencias', 'servicio-social', 'practicas', 'titulacion' => ['estatus' => 'Estatus'] + $comunes,
            default => $comunes,
        };
    }

    /**
     * Enums de dominio de cada filtro, por subtipo de movimiento.
     *
     * @return array<string, class-string<\BackedEnum>>
     */
    private static function enumsMovimiento(string $tipo): array
    {
        return match ($tipo) {
            'bajas' => ['tipoBaja' => TipoBaja::class],
            'equivalencias' => ['estatus' => EstatusEquivalencia::class],
            'servicio-social' => ['estatus' => EstatusServicioSocial::class],
            'practicas' => ['estatus' => EstatusPracticaProfesional::class],
            'titulacion' => ['estatus' => EstatusTitulacion::class],
            default => [],
        };
    }

    /** @return list<array{clave: string, label: string, tipo: string, get: \Closure}> */
    private static function columnasMovimiento(string $tipo): array
    {
        $nc = ['clave' => 'numero_control', 'label' => 'N. de control', 'tipo' => 'texto', 'get' => fn ($f) => $f->alumno?->numero_control];
        $alumno = ['clave' => 'alumno', 'label' => 'Alumno', 'tipo' => 'texto', 'get' => fn ($f) => $f->alumno?->nombre_completo];
        $identidad = [$nc, $alumno];

        return match ($tipo) {
            'bajas' => array_merge([
                ['clave' => 'fecha', 'label' => 'Fecha', 'tipo' => 'fecha', 'get' => fn ($f) => $f->fecha],
                ['clave' => 'tipo', 'label' => 'Tipo', 'tipo' => 'estado', 'get' => fn ($f) => $f->tipo instanceof \BackedEnum ? $f->tipo->value : (string) $f->tipo],
            ], $identidad, [
                ['clave' => 'materia', 'label' => 'Materia', 'tipo' => 'texto', 'get' => fn ($f) => $f->inscripcionMateria?->grupo?->materia?->nombre],
                ['clave' => 'motivo', 'label' => 'Motivo', 'tipo' => 'texto', 'get' => fn ($f) => $f->motivo],
                ['clave' => 'reingreso', 'label' => 'Reingreso', 'tipo' => 'texto', 'get' => fn ($f) => $f->reingreso_at?->format('d/m/Y')],
            ]),
            'equivalencias' => array_merge($identidad, [
                ['clave' => 'materia', 'label' => 'Materia', 'tipo' => 'texto', 'get' => fn ($f) => $f->materia?->nombre],
                ['clave' => 'origen', 'label' => 'Origen', 'tipo' => 'texto', 'get' => fn ($f) => $f->origen],
                ['clave' => 'creditos', 'label' => 'Créditos', 'tipo' => 'entero', 'get' => fn ($f) => $f->creditos],
                ['clave' => 'estatus', 'label' => 'Estatus', 'tipo' => 'estado', 'get' => fn ($f) => $f->estatus instanceof \BackedEnum ? $f->estatus->value : (string) $f->estatus],
                ['clave' => 'dictamen', 'label' => 'Dictamen', 'tipo' => 'texto', 'get' => fn ($f) => $f->dictamen],
                ['clave' => 'fecha_dictamen', 'label' => 'Fecha de dictamen', 'tipo' => 'fecha', 'get' => fn ($f) => $f->fecha_dictamen],
            ]),
            'servicio-social' => array_merge($identidad, [
                ['clave' => 'actividad', 'label' => 'Actividad', 'tipo' => 'texto', 'get' => fn ($f) => $f->actividad],
                ['clave' => 'dependencia', 'label' => 'Dependencia', 'tipo' => 'texto', 'get' => fn ($f) => $f->dependencia],
                ['clave' => 'inicio', 'label' => 'Inicio', 'tipo' => 'fecha', 'get' => fn ($f) => $f->fecha_inicio],
                ['clave' => 'fin', 'label' => 'Fin', 'tipo' => 'fecha', 'get' => fn ($f) => $f->fecha_fin],
                ['clave' => 'avance', 'label' => 'Avance', 'tipo' => 'porcentaje', 'get' => fn ($f) => $f->avance],
                ['clave' => 'estatus', 'label' => 'Estatus', 'tipo' => 'estado', 'get' => fn ($f) => $f->estatus instanceof \BackedEnum ? $f->estatus->value : (string) $f->estatus],
            ]),
            'practicas' => array_merge($identidad, [
                ['clave' => 'empresa', 'label' => 'Empresa', 'tipo' => 'texto', 'get' => fn ($f) => $f->empresa],
                ['clave' => 'asesor', 'label' => 'Asesor', 'tipo' => 'texto', 'get' => fn ($f) => $f->asesor],
                ['clave' => 'inicio', 'label' => 'Inicio', 'tipo' => 'fecha', 'get' => fn ($f) => $f->fecha_inicio],
                ['clave' => 'fin', 'label' => 'Fin', 'tipo' => 'fecha', 'get' => fn ($f) => $f->fecha_fin],
                ['clave' => 'calificacion', 'label' => 'Calificación', 'tipo' => 'calificacion', 'get' => fn ($f) => $f->calificacion],
                ['clave' => 'estatus', 'label' => 'Estatus', 'tipo' => 'estado', 'get' => fn ($f) => $f->estatus instanceof \BackedEnum ? $f->estatus->value : (string) $f->estatus],
            ]),
            'egreso' => array_merge($identidad, [
                ['clave' => 'plan', 'label' => 'Versión de plan', 'tipo' => 'texto', 'get' => fn ($f) => $f->planVersion?->version],
                ['clave' => 'creditos_requeridos', 'label' => 'Créditos requeridos', 'tipo' => 'entero', 'get' => fn ($f) => $f->creditos_requeridos],
                ['clave' => 'creditos_acumulados', 'label' => 'Créditos acumulados', 'tipo' => 'entero', 'get' => fn ($f) => $f->creditos_acumulados],
                ['clave' => 'registrado', 'label' => 'Registrado', 'tipo' => 'fecha', 'get' => fn ($f) => $f->registrado_at],
            ]),
            'titulacion' => array_merge($identidad, [
                ['clave' => 'modalidad', 'label' => 'Modalidad', 'tipo' => 'texto', 'get' => fn ($f) => $f->modalidad],
                ['clave' => 'folio', 'label' => 'Folio interno', 'tipo' => 'texto', 'get' => fn ($f) => $f->folio_interno],
                ['clave' => 'estatus', 'label' => 'Estatus', 'tipo' => 'estado', 'get' => fn ($f) => $f->estatus instanceof \BackedEnum ? $f->estatus->value : (string) $f->estatus],
                ['clave' => 'registrado', 'label' => 'Registrado', 'tipo' => 'fecha', 'get' => fn ($f) => $f->registrado_at],
            ]),
            default => $identidad,
        };
    }

    // =====================================================================
    // Formato de celdas (tabla y CSV comparten el valor crudo)
    // =====================================================================

    private static function formatear(array $columna, mixed $valor, bool $paraTabla): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($columna['tipo']) {
            'estado' => self::etiquetaEstado($valor),
            'fecha' => $valor instanceof CarbonInterface
                ? $valor->format($paraTabla ? 'd/m/Y' : 'Y-m-d')
                : (string) $valor,
            'fecha_hora' => $valor instanceof CarbonInterface
                ? $valor->format($paraTabla ? 'd/m/Y H:i' : 'Y-m-d H:i')
                : (string) $valor,
            'moneda' => number_format((float) $valor, 2, '.', $paraTabla ? ',' : ''),
            'porcentaje' => number_format((float) $valor, 1, '.', '') . ($paraTabla ? ' %' : ''),
            'calificacion' => (string) (float) $valor,
            'entero' => (string) (int) $valor,
            default => (string) $valor,
        };
    }

    /**
     * Etiqueta visible de un valor técnico (§18: jamás se muestra `->value`).
     */
    private static function etiquetaEstado(mixed $valor): string
    {
        if (! is_string($valor) || $valor === '') {
            return (string) $valor;
        }

        $enum = self::mapaEnums()[$valor] ?? null;

        if ($enum === null) {
            return $valor;
        }

        $caso = $enum::tryFrom($valor);

        return $caso !== null && method_exists($caso, 'label') ? $caso->label() : $valor;
    }

    /**
     * Valor técnico => enum que define su etiqueta en español.
     *
     * Si dos enums comparten un valor (p. ej. `APROBADA`), sus etiquetas
     * coinciden, así que el último en registrarse no cambia el resultado.
     *
     * @return array<string, class-string<\BackedEnum>>
     */
    private static function mapaEnums(): array
    {
        static $mapa = null;

        if ($mapa === null) {
            $mapa = [];
            $enums = [
                InscripcionEstatus::class,
                EstatusInscripcionMateria::class,
                EstatusPago::class,
                EstatusBeca::class,
                EstatusEquivalencia::class,
                EstatusServicioSocial::class,
                EstatusPracticaProfesional::class,
                EstatusTitulacion::class,
                EstatusMatriculaPeriodo::class,
                TipoBaja::class,
                MetodoPago::class,
                TipoBeca::class,
                AlcanceBeca::class,
            ];
            foreach ($enums as $clase) {
                foreach ($clase::cases() as $caso) {
                    $mapa[$caso->value] = $clase;
                }
            }
        }

        return $mapa;
    }
}
