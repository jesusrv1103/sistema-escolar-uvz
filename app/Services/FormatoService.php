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
        Alumno $alumno,
        ?MatriculaPeriodo $matricula = null,
        ?Grupo $grupo = null,
        array $datos = [],
        ?User $usuario = null,
    ): string {
        $usuario ??= auth()->user();

        // Validar autorización
        $this->autorizar($tipo, $alumno, $matricula, $grupo, $usuario);

        // Preparar datos normalizados
        $datosNormalizados = $this->prepararDatos($tipo, $alumno, $matricula, $grupo, $datos);

        // Generar folio si el formato lo requiere
        $folio = null;
        if ($tipo->requiereFolio()) {
            $folio = $this->generarFolio($tipo);
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
        Alumno $alumno,
        ?MatriculaPeriodo $matricula,
        ?Grupo $grupo,
        array $datosExtra = [],
    ): array {
        $alumno->loadMissing(['matriculas.periodo', 'matriculas.programa', 'matriculas.planVersion']);

        $datos = [
            'tipo' => $tipo->value,
            'tipo_label' => $tipo->label(),
            'alumno' => [
                'id' => $alumno->id,
                'nombre_completo' => $alumno->nombre . ' ' . $alumno->apellidos,
                'curp' => $alumno->curp,
                'numero_control' => $alumno->numero_control,
                'correo' => $alumno->correo,
                'telefono' => $alumno->telefono,
                'domicilio' => $this->formatearDomicilio($alumno),
            ],
            'branding' => $this->obtenerBranding(),
            'fecha_emision' => now()->format('d/m/Y'),
            'fecha_emision_larga' => now()->locale('es_MX')->isoFormat('D [de] MMMM [de] YYYY'),
        ];

        if ($matricula) {
            $matricula->loadMissing(['periodo', 'programa', 'planVersion']);
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
            ] : null;
        }

        if ($grupo) {
            $grupo->loadMissing(['materia', 'periodo', 'docente', 'aula', 'horarios']);
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
                'horarios' => $grupo->horarios->map(fn ($h) => [
                    'dia' => $h->dia->label(),
                    'hora_inicio' => $h->hora_inicio->format('H:i'),
                    'hora_fin' => $h->hora_fin->format('H:i'),
                ])->toArray(),
            ] : null;
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
        $vista = "pdf.formatos.{$tipo->value}";
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
        };

        return "{$prefijo}_{$timestamp}.pdf";
    }

    /**
     * Registra la trazabilidad en documentos_generados.
     */
    private function registrarTrazabilidad(
        TipoFormato $tipo,
        Alumno $alumno,
        ?MatriculaPeriodo $matricula,
        ?Grupo $grupo,
        ?string $folio,
        array $snapshotDatos,
        ?User $usuario,
    ): void {
        DocumentoGenerado::create([
            'alumno_id' => $alumno->id,
            'matricula_periodo_id' => $matricula?->id,
            'grupo_id' => $grupo?->id,
            'tipo' => $tipo->value,
            'folio' => $folio,
            'emitido_por' => $usuario?->id,
            'emitido_at' => now(),
            'snapshot_datos' => $snapshotDatos,
        ]);
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
        Alumno $alumno,
        ?MatriculaPeriodo $matricula,
        ?Grupo $grupo,
        ?User $usuario,
    ): void {
        $usuario ??= auth()->user();

        // Verificar que el usuario tiene acceso al alumno/matrícula/grupo
        // La lógica específica se valida en los Policies correspondientes

        switch ($tipo) {
            case TipoFormato::CONSTANCIA_INSCRIPCION:
            case TipoFormato::COMPROBANTE_INSCRIPCION:
            case TipoFormato::FICHA:
                Gate::authorize('documento_generado.crear', [TipoFormato::CONSTANCIA_INSCRIPCION, $alumno]);
                break;
            case TipoFormato::HORARIO:
                Gate::authorize('documento_generado.crear', [TipoFormato::HORARIO, $alumno]);
                if ($grupo) {
                    Gate::authorize('ver', $grupo);
                }
                break;
            case TipoFormato::LISTA_GRUPO:
                Gate::authorize('documento_generado.crear', [TipoFormato::LISTA_GRUPO, $alumno]);
                if ($grupo) {
                    Gate::authorize('ver', $grupo);
                }
                break;
        }
    }
}