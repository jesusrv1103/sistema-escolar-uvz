<?php

namespace App\Services;

use App\Enums\TipoFormato;
use App\Enums\TipoConsecutivo;
use App\Models\DocumentoGenerado;
use App\Models\MatriculaPeriodo;
use App\Models\Grupo;
use App\Models\Alumno;
use App\Models\User;
use App\Services\ConsecutivoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Motor de formatos académicos (pipeline: DATOS/REGLAS → SERVICIO → PLANTILLA BLADE → PDF).
 *
 * Único punto de negocio para generación de formatos académicos internos UVZ.
 * Patrón: DATOS/REGLAS → SERVICIO → PLANTILLA BLADE → PDF (DomPDF bajo demanda).
 */
class FormatoService
{
    public function __construct(
        private readonly ConsecutivoService $consecutivoService,
    ) {
    }

    /**
     * Genera un formato académico y retorna la ruta del PDF generado.
     *
     * @param  array<string, mixed>  $datos  Datos adicionales específicos del formato
     * @return string  Ruta del PDF generado en storage
     */
    public function generar(
        TipoFormato $tipo,
        ?Alumno $alumno = null,
        ?MatriculaPeriodo $matricula = null,
        ?Grupo $grupo = null,
        array $datos = [],
        ?User $usuario = null,
    ): string {
        $usuario ??= auth()->user();

        if (!$usuario) {
            abort(403, 'Usuario no autenticado.');
        }

        // Validar que se proporcione alumno para formatos que lo requieren
        if ($tipo->requiereAlumno() && !$alumno) {
            abort(400, 'Se requiere un alumno para este tipo de formato.');
        }

        // Guarda de veracidad: la constancia de terminación sólo existe para
        // alumnos con egreso registrado (emitirla sin egreso sería falso).
        if ($tipo === TipoFormato::CONSTANCIA_TERMINACION && $alumno && !$alumno->egreso()->exists()) {
            abort(422, 'Sólo se emite la Constancia de Terminación para alumnos con egreso registrado.');
        }

        // Validar autorización
        $this->autorizar($tipo, $alumno, $matricula, $grupo, $usuario);

        // Preparar datos normalizados
        $datosNormalizados = $this->prepararDatos($tipo, $alumno, $matricula, $grupo, $datos);

        // Generar folio si el formato lo requiere
        $folio = null;
        if ($tipo->requiereFolio()) {
            // Para ACTA, verificar si se proporciona un acta_id para reutilizar el folio
            if ($tipo === TipoFormato::ACTA && isset($datos['acta_id'])) {
                $actaExistente = \App\Models\Acta::find($datos['acta_id']);
                if ($actaExistente && $actaExistente->folio) {
                    $folio = $actaExistente->folio;
                } else {
                    $folio = $this->generarFolio($tipo);
                }
            } else {
                $folio = $this->generarFolio($tipo);
            }
        }

        // Renderizar PDF
        $pdfPath = $this->renderizarPdf($tipo, $datosNormalizados, $folio);

        // Registrar trazabilidad
        $this->registrarTrazabilidad($tipo, $alumno, $matricula, $grupo, $folio, $datosNormalizados, $usuario);

        return $pdfPath;
    }

    /**
     * Prepara los datos normalizados para la plantilla.
     *
     * @return array<string, mixed>
     */
    private function prepararDatos(
        TipoFormato $tipo,
        ?Alumno $alumno,
        ?MatriculaPeriodo $matricula,
        ?Grupo $grupo,
        array $datosExtra = [],
    ): array {
        $datos = [
            'tipo' => $tipo->value,
            'tipo_label' => $tipo->label(),
            'branding' => $this->obtenerBranding(),
            'fecha_emision' => now()->format('d/m/Y'),
            'fecha_emision_larga' => now()->locale('es_MX')->isoFormat('D [de] MMMM [de] YYYY'),
        ];

        if ($alumno) {
            $alumno->loadMissing(['matriculas.periodo', 'matriculas.programa', 'matriculas.planVersion']);
            $datos['alumno'] = [
                'id' => $alumno->id,
                'nombre_completo' => $alumno->nombre . ' ' . $alumno->apellidos,
                'curp' => $alumno->curp,
                'numero_control' => $alumno->numero_control,
                'correo' => $alumno->correo,
                'telefono' => $alumno->telefono,
                'domicilio' => $this->formatearDomicilio($alumno),
                'fecha_nacimiento' => $alumno->fecha_nacimiento,
                'sexo' => $alumno->sexo,
                'nacionalidad' => $alumno->nacionalidad,
            ];
        }

        if ($matricula) {
            $matricula->loadMissing(['periodo', 'programa', 'planVersion', 'inscripcionesCursando', 'inscripcionesAprobadas']);
            $datos['matricula'] = [
                'id' => $matricula->id,
                'origen' => $matricula->origen?->label(),
                'fecha_matricula' => $matricula->fecha_matricula?->format('d/m/Y'),
                'estatus' => $matricula->estatus?->label(),
                'periodo' => $matricula->periodo ? [
                    'clave' => $matricula->periodo->clave,
                    'nombre' => $matricula->periodo->nombre,
                    'fecha_inicio' => $matricula->periodo->fecha_inicio?->format('d/m/Y'),
                    'fecha_fin' => $matricula->periodo->fecha_fin?->format('d/m/Y'),
                ] : null,
                'programa' => $matricula->programa ? [
                    'clave' => $matricula->programa->clave,
                    'nombre' => $matricula->programa->nombre,
                ] : null,
                'plan_version' => $matricula->planVersion ? [
                    'version' => $matricula->planVersion->version,
                    'fecha_vigencia' => $matricula->planVersion->fecha_vigencia?->format('d/m/Y'),
                ] : null,
                'inscripcionesCursando' => collect($matricula->inscripcionesCursando->map(function ($inscripcion) {
                    $inscripcion->loadMissing(['grupo.materia', 'grupo.docente', 'grupo.horarios']);
                    return [
                        'id' => $inscripcion->id,
                        'materia' => [
                            'clave' => $inscripcion->materia->clave,
                            'nombre' => $inscripcion->materia->nombre,
                            'creditos' => $inscripcion->materia->creditos,
                        ],
                        'grupo' => $inscripcion->grupo ? [
                            'id' => $inscripcion->grupo->id,
                            'clave' => $inscripcion->grupo->clave,
                            'materia' => [
                                'clave' => $inscripcion->grupo->materia->clave,
                                'nombre' => $inscripcion->grupo->materia->nombre,
                                'creditos' => $inscripcion->grupo->materia->creditos,
                            ],
                            'docente' => $inscripcion->grupo->docente ? [
                                'nombre_completo' => $inscripcion->grupo->docente->nombreCompleto(),
                            ] : null,
                            'horarios' => $inscripcion->grupo->horarios->map(function ($h) {
                                return [
                                    'dia' => $h->dia,
                                    'hora_inicio' => $h->hora_inicio,
                                    'hora_fin' => $h->hora_fin,
                                ];
                            }),
                        ] : null,
                    ];
                })),
                'inscripcionesAprobadas' => $matricula->inscripcionesAprobadas->map(function ($inscripcion) {
                    $inscripcion->loadMissing(['grupo.materia', 'grupo.periodo']);
                    return [
                        'id' => $inscripcion->id,
                        'materia' => [
                            'clave' => $inscripcion->materia->clave,
                            'nombre' => $inscripcion->materia->nombre,
                            'creditos' => $inscripcion->materia->creditos,
                        ],
                        'calificacion_final' => $inscripcion->calificacion_final,
                        'grupo' => $inscripcion->grupo ? [
                            'clave' => $inscripcion->grupo->clave,
                            'periodo' => $inscripcion->grupo->periodo ? [
                                'clave' => $inscripcion->grupo->periodo->clave,
                            ] : null,
                        ] : null,
                    ];
                }),
            ];
        }

        if ($grupo) {
            $grupo->loadMissing(['materia', 'periodo', 'docente', 'aula', 'horarios', 'inscripcionesMaterias.matriculaPeriodo.alumno']);
            $datos['grupo'] = [
                'id' => $grupo->id,
                'clave' => $grupo->clave,
                'cupo' => $grupo->cupo,
                'turno' => $grupo->turno?->label(),
                'estatus' => $grupo->estatus?->label(),
                'materia' => $grupo->materia ? [
                    'clave' => $grupo->materia->clave,
                    'nombre' => $grupo->materia->nombre,
                    'creditos' => $grupo->materia->creditos,
                ] : null,
                'periodo' => $grupo->periodo ? [
                    'clave' => $grupo->periodo->clave,
                    'nombre' => $grupo->periodo->nombre,
                ] : null,
                'docente' => $grupo->docente ? [
                    'nombre_completo' => $grupo->docente->nombreCompleto(),
                    'clave' => $grupo->docente->clave,
                ] : null,
                'aula' => $grupo->aula ? [
                    'clave' => $grupo->aula->clave,
                    'nombre' => $grupo->aula->nombre,
                    'edificio' => $grupo->aula->edificio,
                ] : null,
                // Misma forma que el DTO de inscripción: enum de día + Carbon.
                // `lista_grupo` etiqueta y formatea desde esa forma (con texto
                // hacía `Call to a member function sortBy() on array`, HTTP 500).
                // El orden es determinista: día de la semana y luego hora inicio.
                'horarios' => $grupo->horarios
                    ->sortBy(fn ($h) => sprintf('%d-%s', $h->dia->orden(), $h->hora_inicio->format('H:i')))
                    ->map(fn ($h) => [
                        'dia' => $h->dia,
                        'hora_inicio' => $h->hora_inicio,
                        'hora_fin' => $h->hora_fin,
                    ])
                    ->values(),
                'inscripcionesMaterias' => $grupo->inscripcionesMaterias->map(function ($inscripcion) {
                    return [
                        'id' => $inscripcion->id,
                        'estatus' => $inscripcion->estatus?->value,
                        'matriculaPeriodo' => $inscripcion->matriculaPeriodo ? [
                            'id' => $inscripcion->matriculaPeriodo->id,
                            'alumno' => $inscripcion->matriculaPeriodo->alumno ? [
                                'id' => $inscripcion->matriculaPeriodo->alumno->id,
                                'nombre_completo' => $inscripcion->matriculaPeriodo->alumno->nombre . ' ' . $inscripcion->matriculaPeriodo->alumno->apellidos,
                                'curp' => $inscripcion->matriculaPeriodo->alumno->curp,
                                'numero_control' => $inscripcion->matriculaPeriodo->alumno->numero_control,
                                'correo' => $inscripcion->matriculaPeriodo->alumno->correo,
                                'telefono' => $inscripcion->matriculaPeriodo->alumno->telefono,
                            ] : null,
                        ] : null,
                    ];
                }),
            ];
        }

        // Cargar datos específicos para formatos 2.0-10
        if (in_array($tipo->value, ['KARDEX', 'BOLETA', 'HISTORIAL', 'TIRA', 'AVANCE'])) {
            if ($alumno) {
                $service = app(\App\Services\HistorialAcademicoService::class);
                $datos['historial'] = $service->obtenerHistorial($alumno);
                $datos['resumen'] = $service->resumenAcademico($alumno);
            }
        }

        if ($tipo->value === 'ACTA') {
            if ($grupo) {
                $acta = \App\Models\Acta::where('grupo_id', $grupo->id)
                    ->where('periodo_id', $grupo->periodo_id)
                    ->first();
                if ($acta) {
                    $acta->loadMissing(['grupo.materia', 'periodo', 'cerradaPor', 'grupo.inscripcionesMaterias.matriculaPeriodo.alumno']);
                    $datos['acta'] = $acta;
                }
            }
        }

        if (in_array($tipo->value, ['TIRA', 'AVANCE'])) {
            if ($alumno) {
                $service = app(\App\Services\HistorialAcademicoService::class);
                $datos['tira'] = $service->tiraMaterias($alumno);
                $datos['resumen'] = $service->resumenAcademico($alumno);
            }
        }

        // Fila 13: constancia de terminación (avance + servicio social + prácticas).
        if ($tipo === \App\Enums\TipoFormato::CONSTANCIA_TERMINACION && $alumno) {
            $historial = app(\App\Services\HistorialAcademicoService::class);

            $datos['egreso'] = $alumno->egreso()->with(['planVersion.plan', 'registradoPor'])->first();
            $datos['resumen'] = $historial->resumenAcademico($alumno);
            $datos['servicio_social'] = \App\Models\ServicioSocial::where('alumno_id', $alumno->id)->first();
            $datos['practicas'] = \App\Models\PracticaProfesional::where('alumno_id', $alumno->id)->first();
        }

        return array_merge($datos, $datosExtra);
    }

    /**
     * Genera el folio usando el servicio de consecutivos.
     */
    private function generarFolio(TipoFormato $tipo): string
    {
        $tipoConsecutivo = $tipo->tipoConsecutivo();
        if (!$tipoConsecutivo) {
            throw new \InvalidArgumentException("El formato {$tipo->value} no tiene tipo de consecutivo asociado.");
        }

        return $this->consecutivoService->generar(TipoConsecutivo::from($tipoConsecutivo));
    }

    /**
     * Renderiza el PDF usando DomPDF.
     */
    private function renderizarPdf(TipoFormato $tipo, array $datos, ?string $folio): string
    {
        $vista = "pdf.formatos." . strtolower($tipo->value);
        $nombreArchivo = $this->generarNombreArchivo($tipo);

        $pdf = \PDF::loadView($vista, array_merge($datos, ['folio' => $folio]));
        $pdf->setPaper('letter', 'portrait');

        $path = "documentos_generados/{$nombreArchivo}";
        Storage::disk('private')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Genera el nombre del archivo PDF.
     */
    private function generarNombreArchivo(TipoFormato $tipo): string
    {
        $timestamp = now()->format('Ymd_His');
        $prefijo = match ($tipo) {
            TipoFormato::CONSTANCIA_INSCRIPCION => 'constancia_inscripcion',
            TipoFormato::COMPROBANTE_INSCRIPCION => 'comprobante_inscripcion',
            TipoFormato::FICHA => 'ficha',
            TipoFormato::HORARIO => 'horario',
            TipoFormato::LISTA_GRUPO => 'lista_grupo',
            TipoFormato::KARDEX => 'kardex',
            TipoFormato::BOLETA => 'boleta',
            TipoFormato::HISTORIAL => 'historial',
            TipoFormato::ACTA => 'acta',
            TipoFormato::TIRA => 'tira',
            TipoFormato::AVANCE => 'avance',
            TipoFormato::CONSTANCIA_TERMINACION => 'constancia_terminacion',
        };

        return "{$prefijo}_{$timestamp}.pdf";
    }

    /**
     * Registra la trazabilidad en documentos_generados.
     */
    private function registrarTrazabilidad(
        TipoFormato $tipo,
        ?Alumno $alumno,
        ?MatriculaPeriodo $matricula,
        ?Grupo $grupo,
        ?string $folio,
        array $snapshotDatos,
        ?User $usuario,
    ): void {
        $atributos = [
            'alumno_id' => $alumno?->id,
            'matricula_periodo_id' => $matricula?->id,
            'grupo_id' => $grupo?->id,
            'tipo' => $tipo->value,
            'folio' => $folio,
            'emitido_por' => $usuario?->id,
            'emitido_at' => now(),
            'snapshot_datos' => $snapshotDatos,
        ];

        // `documentos_generados.folio` es UNIQUE y sólo el ACTA reutiliza un
        // folio ya emitido (`actas.folio`), de modo que volver a generarlo
        // reenviaba un segundo INSERT con el mismo folio (SQLSTATE 23000/1062)
        // y el error crudo llegaba a la pantalla. La clave lógica del documento
        // con folio es el folio: se conserva UNA fila por folio y se refresca
        // la emisión; los folios de constancia siguen llegando nuevos del
        // consecutivo, así que su comportamiento no cambia.
        if ($folio !== null) {
            $emitido = DocumentoGenerado::query()->where('folio', $folio)->first();

            if ($emitido !== null) {
                // Mismo folio sólo si es el mismo tipo (y el mismo grupo, cuando
                // ambos están registrados): nunca se reescribe un ajeno.
                $incompatible = $emitido->tipo->value !== $tipo->value
                    || ($grupo !== null && $emitido->grupo_id !== null && $emitido->grupo_id !== $grupo->id);

                if ($incompatible) {
                    abort(409, "El folio {$folio} ya está registrado para otro documento.");
                }

                $emitido->fill([
                    'alumno_id' => $emitido->alumno_id ?? $alumno?->id,
                    'matricula_periodo_id' => $emitido->matricula_periodo_id ?? $matricula?->id,
                    'grupo_id' => $emitido->grupo_id ?? $grupo?->id,
                    'emitido_por' => $usuario?->id,
                    'emitido_at' => now(),
                    'snapshot_datos' => $snapshotDatos,
                ])->save();

                return;
            }
        }

        DocumentoGenerado::create($atributos);
    }

    /**
     * Obtiene el branding desde config.
     *
     * @return array<string, mixed>
     */
    private function obtenerBranding(): array
    {
        return [
            'nombre' => config('branding.name'),
            'nombre_corto' => config('branding.short_name'),
            'sistema' => config('branding.system_name'),
            'slogan' => config('branding.slogan'),
            'hashtag' => config('branding.hashtag'),
            'website' => config('branding.website'),
            'logo' => config('branding.logo'),
            'logo_vertical' => config('branding.logo_vertical'),
            'logo_dark' => config('branding.logo_dark'),
            'favicon' => config('branding.favicon'),
            'primary_color' => config('branding.primary_color'),
            'secondary_color' => config('branding.secondary_color'),
            'highlight_color' => config('branding.highlight_color'),
            'accent_color' => config('branding.accent_color'),
            'background_color' => config('branding.background_color'),
            'surface_color' => config('branding.surface_color'),
            'text_color' => config('branding.text_color'),
            'muted_color' => config('branding.muted_color'),
            'border_color' => config('branding.border_color'),
            'phone' => config('branding.phone'),
            'email' => config('branding.email'),
            'address' => config('branding.address'),
        ];
    }

    /**
     * Formatea el domicilio del alumno.
     */
    private function formatearDomicilio(\App\Models\Alumno $alumno): string
    {
        $partes = [];
        if ($alumno->calle) $partes[] = $alumno->calle;
        if ($alumno->colonia) $partes[] = $alumno->colonia;
        if ($alumno->cp) $partes[] = "CP {$alumno->cp}";
        if ($alumno->municipio) $partes[] = $alumno->municipio;
        if ($alumno->estado) $partes[] = $alumno->estado;
        if ($alumno->pais) $partes[] = $alumno->pais;

        return implode(', ', $partes);
    }

    /**
     * Valida autorización según el tipo de formato.
     */
    private function autorizar(
        TipoFormato $tipo,
        ?Alumno $alumno,
        ?MatriculaPeriodo $matricula,
        ?Grupo $grupo,
        ?User $usuario,
    ): void {
        $usuario ??= auth()->user();

        if (!$usuario) {
            abort(403, 'Usuario no autenticado.');
        }

        // Usar autorización para el usuario específico
        Gate::forUser($usuario)->authorize('create', DocumentoGenerado::class);

        // Validaciones adicionales por tipo
        if (in_array($tipo, [TipoFormato::HORARIO, TipoFormato::LISTA_GRUPO], true) && $grupo) {
            Gate::forUser($usuario)->authorize('ver', $grupo);
        }
    }
}