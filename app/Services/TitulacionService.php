<?php

namespace App\Services;

use App\Enums\EstatusTitulacion;
use App\Exceptions\TitulacionInvalidaException;
use App\Models\Alumno;
use App\Models\Egreso;
use App\Models\Titulacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Servicio único de Titulación interna (2.0-14).
 *
 * Reglas conservadoras:
 *
 * - Exige egreso registrado (la UI muestra el egreso asociado).
 * - `modalidad` es texto libre: no existe catálogo de modalidades en el
 *   sistema, así que no se infiere ninguna (pendiente institucional).
 * - `folio_interno` es captura manual opcional con unicidad; NO se genera un
 *   folio automático ni se inventa un formato de folio.
 * - Un expediente por alumno; estatus sólo EN_TRAMITE / TITULADO (sin
 *   estados de rechazo/cancelación: no hay fuente que los defina).
 */
class TitulacionService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
    ) {
    }

    /**
     * Crea el expediente de titulación del alumno (EN_TRAMITE por defecto).
     *
     * @param array<string, mixed> $datos modalidad?, folio_interno?, estatus?
     *
     * @throws TitulacionInvalidaException si no hay egreso, ya existe expediente o el folio está en uso
     */
    public function crear(Alumno $alumno, array $datos, ?User $usuario = null): Titulacion
    {
        $usuario ??= auth()->user();
        $this->autorizar('titulacion.crear', $usuario);

        return DB::transaction(function () use ($alumno, $datos, $usuario): Titulacion {
            $egreso = Egreso::where('alumno_id', $alumno->id)->first();

            if ($egreso === null) {
                throw new TitulacionInvalidaException(
                    'El alumno no tiene egreso registrado: primero debe registrarse el egreso.'
                );
            }

            if (Titulacion::where('alumno_id', $alumno->id)->exists()) {
                throw new TitulacionInvalidaException('El alumno ya tiene un expediente de titulación.');
            }

            $this->validarFolio($datos['folio_interno'] ?? null);
            $this->validarModalidad($datos['modalidad'] ?? null);

            $titulacion = Titulacion::create([
                'alumno_id' => $alumno->id,
                'egreso_id' => $egreso->id,
                'modalidad' => $this->texto($datos['modalidad'] ?? null),
                'folio_interno' => $this->texto($datos['folio_interno'] ?? null),
                'estatus' => isset($datos['estatus'])
                    ? EstatusTitulacion::from((string) $datos['estatus'])
                    : EstatusTitulacion::EN_TRAMITE,
                'registrado_por' => $usuario?->getKey(),
                'registrado_at' => now(),
            ]);

            $this->auditoria->registrar(
                $titulacion,
                'titulacion_registrada',
                [],
                ['estatus' => $titulacion->estatus->value],
                ['egreso_id' => $egreso->id, 'modalidad' => $titulacion->modalidad],
                $usuario,
            );

            return $titulacion;
        });
    }

    /**
     * Actualiza los datos del expediente (modalidad, folio, estatus).
     *
     * @param array<string, mixed> $datos
     *
     * @throws TitulacionInvalidaException si el folio ya se usa en otro expediente
     */
    public function actualizar(Titulacion $titulacion, array $datos, ?User $usuario = null): Titulacion
    {
        $usuario ??= auth()->user();
        $this->autorizar('titulacion.actualizar', $usuario);

        $this->validarFolio($datos['folio_interno'] ?? null, $titulacion);
        $this->validarModalidad($datos['modalidad'] ?? null);

        return DB::transaction(function () use ($titulacion, $datos, $usuario): Titulacion {
            $antes = ['estatus' => $titulacion->estatus->value];

            $titulacion->update([
                'modalidad' => $this->texto($datos['modalidad'] ?? null),
                'folio_interno' => $this->texto($datos['folio_interno'] ?? null),
                'estatus' => isset($datos['estatus'])
                    ? EstatusTitulacion::from((string) $datos['estatus'])
                    : $titulacion->estatus,
            ]);

            $this->auditoria->registrar(
                $titulacion,
                'titulacion_actualizada',
                $antes,
                ['estatus' => $titulacion->estatus->value],
                ['folio_interno' => $titulacion->folio_interno],
                $usuario,
            );

            return $titulacion->refresh();
        });
    }

    private function texto(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }

    /**
     * @throws TitulacionInvalidaException
     */
    private function validarFolio(mixed $folio, ?Titulacion $excluir = null): void
    {
        $valor = $this->texto($folio);

        if ($valor === null) {
            return;
        }

        $enUso = Titulacion::where('folio_interno', $valor)
            ->when($excluir !== null, fn ($q) => $q->where('id', '!=', $excluir->id))
            ->exists();

        if ($enUso) {
            throw new TitulacionInvalidaException("El folio interno \"{$valor}\" ya está en uso.");
        }
    }

    /**
     * @throws TitulacionInvalidaException
     */
    private function validarModalidad(mixed $modalidad): void
    {
        if (mb_strlen($this->texto($modalidad) ?? '') > 200) {
            throw new TitulacionInvalidaException('La modalidad no puede superar los 200 caracteres.');
        }
    }

    private function autorizar(string $capacidad, ?User $usuario): void
    {
        $usuario !== null
            ? Gate::forUser($usuario)->authorize($capacidad)
            : Gate::authorize($capacidad);
    }
}
