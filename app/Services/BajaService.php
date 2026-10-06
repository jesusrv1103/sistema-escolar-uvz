<?php

namespace App\Services;

use App\Enums\EstatusInscripcionMateria;
use App\Enums\EstatusMatriculaPeriodo;
use App\Enums\TipoBaja;
use App\Exceptions\BajaInvalidaException;
use App\Models\Acta;
use App\Models\Alumno;
use App\Models\Baja;
use App\Models\InscripcionMateria;
use App\Models\MatriculaPeriodo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Servicio único de bajas y reingresos (2.0-11).
 *
 * Reglas aprobadas:
 *
 * - TEMPORAL: registra la baja con motivo/fecha/responsable, la matrícula
 *   activa pasa a BAJA_TEMPORAL (bloquea nuevas cargas mientras esté vigente),
 *   `reingreso_at` queda null hasta que CONTROL_ESCOLAR autoriza el reingreso.
 * - DEFINITIVA: la matrícula activa pasa a BAJA_DEFINITIVA, deja constancia en
 *   auditoría y es irreversible en el alcance actual. Bloquea futuras cargas y
 *   matrículas. No borra nada: se preservan historial, número de control,
 *   pagos y documentos.
 * - MATERIA: sólo mientras el acta del grupo no esté CERRADA; la inscripción
 *   pasa a BAJA con `fecha_baja` y deja de aportar promedio y créditos.
 *
 * Las transiciones se acotan en la propia consulta (sólo matrículas ACTIVA →
 * baja y sólo BAJA_TEMPORAL → ACTIVA en el reingreso), así que no puede
 * producirse ninguna transición fuera de la matriz de `MatriculaService`.
 *
 * Toda escritura ocurre dentro de una transacción con auditoría; la
 * autorización (`baja.crear` / `baja.reingreso`) es server-side.
 */
class BajaService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
    ) {
    }

    /**
     * Registra una baja académica y aplica sus efectos.
     *
     * @throws BajaInvalidaException si alguna regla 2.0-11 no se cumple
     */
    public function registrar(
        Alumno $alumno,
        TipoBaja $tipo,
        string $motivo,
        string $fecha,
        ?InscripcionMateria $inscripcion = null,
        ?User $usuario = null,
    ): Baja {
        $usuario ??= auth()->user();
        $this->autorizar('baja.crear', null, $usuario);

        $motivo = trim($motivo);

        return DB::transaction(function () use ($alumno, $tipo, $motivo, $fecha, $inscripcion, $usuario): Baja {
            $this->validarSinBajaQueBloquee($alumno);

            if ($tipo === TipoBaja::MATERIA) {
                return $this->registrarBajaDeMateria($alumno, $inscripcion, $motivo, $fecha, $usuario);
            }

            $baja = Baja::create([
                'alumno_id' => $alumno->id,
                'tipo' => $tipo,
                'motivo' => $motivo,
                'fecha' => $fecha,
                'registrado_por' => $usuario?->getKey(),
            ]);

            $estatus = $tipo === TipoBaja::TEMPORAL
                ? EstatusMatriculaPeriodo::BAJA_TEMPORAL
                : EstatusMatriculaPeriodo::BAJA_DEFINITIVA;

            $this->bajarMatriculasActivas($alumno, $estatus, $baja, $motivo, $usuario);

            $this->auditoria->registrar(
                $baja,
                'baja_registrada',
                [],
                ['tipo' => $tipo->value, 'estatus_matricula' => $estatus->value],
                ['motivo' => $motivo, 'fecha' => $fecha],
                $usuario,
            );

            return $baja;
        });
    }

    /**
     * Autoriza el reingreso de una baja temporal y restaura su matrícula.
     *
     * @throws BajaInvalidaException si la baja no es temporal o ya reingresó
     */
    public function reingresar(Baja $baja, string $reingresoAt, ?User $usuario = null): Baja
    {
        $usuario ??= auth()->user();
        $this->autorizar('baja.reingreso', $baja, $usuario);

        if (! $baja->esTemporal() || $baja->tieneReingreso()) {
            throw new BajaInvalidaException('Sólo las bajas temporales sin reingreso pueden autorizar reingreso.');
        }

        return DB::transaction(function () use ($baja, $reingresoAt, $usuario): Baja {
            $enlace = Carbon::parse($reingresoAt);

            $baja->forceFill(['reingreso_at' => $enlace])->save();

            $restauradas = MatriculaPeriodo::where('alumno_id', $baja->alumno_id)
                ->where('estatus', EstatusMatriculaPeriodo::BAJA_TEMPORAL->value)
                ->get();

            foreach ($restauradas as $matricula) {
                $antes = $matricula->estatus;
                $matricula->forceFill(['estatus' => EstatusMatriculaPeriodo::ACTIVA->value])->save();

                $this->auditoria->registrar(
                    $matricula,
                    'estatus_cambiado',
                    ['estatus' => $antes->value],
                    ['estatus' => EstatusMatriculaPeriodo::ACTIVA->value],
                    ['baja_id' => $baja->id, 'motivo' => 'Reingreso autorizado'],
                    $usuario,
                );
            }

            $this->auditoria->registrar(
                $baja,
                'reingreso_autorizado',
                ['reingreso_at' => null],
                ['reingreso_at' => $enlace->toDateTimeString()],
                ['motivos' => 'Reingreso manual autorizado por control escolar'],
                $usuario,
            );

            return $baja->refresh();
        });
    }

    /**
     * ¿Tiene el alumno una baja que impida crearle una matrícula nueva?
     *
     * Se consulta desde `MatriculaService` para bloquear futuras matrículas.
     */
    public function bloqueaNuevaMatricula(Alumno $alumno): bool
    {
        return Baja::where('alumno_id', $alumno->id)
            ->where(fn ($q) => $q
                ->where('tipo', TipoBaja::DEFINITIVA->value)
                ->orWhere(fn ($sub) => $sub
                    ->where('tipo', TipoBaja::TEMPORAL->value)
                    ->whereNull('reingreso_at')))
            ->exists();
    }

    /**
     * @throws BajaInvalidaException si ya existe una baja que bloquee al alumno
     */
    private function validarSinBajaQueBloquee(Alumno $alumno): void
    {
        if ($this->bloqueaNuevaMatricula($alumno)) {
            throw new BajaInvalidaException('El alumno ya tiene una baja vigente o definitiva registrada.');
        }
    }

    /**
     * @throws BajaInvalidaException si la baja de materia no cumple las reglas
     */
    private function registrarBajaDeMateria(
        Alumno $alumno,
        ?InscripcionMateria $inscripcion,
        string $motivo,
        string $fecha,
        ?User $usuario,
    ): Baja {
        if ($inscripcion === null) {
            throw new BajaInvalidaException('Selecciona la inscripción de la materia que se dará de baja.');
        }

        if ($inscripcion->matriculaPeriodo->alumno_id !== $alumno->id) {
            throw new BajaInvalidaException('La inscripción seleccionada no pertenece al alumno indicado.');
        }

        if ($inscripcion->estatus === EstatusInscripcionMateria::BAJA) {
            throw new BajaInvalidaException('La materia ya está dada de baja.');
        }

        if ($this->actaCerrada($inscripcion)) {
            throw new BajaInvalidaException('No se puede dar de baja: el acta del grupo ya está cerrada.');
        }

        $baja = Baja::create([
            'alumno_id' => $alumno->id,
            'inscripcion_materia_id' => $inscripcion->id,
            'tipo' => TipoBaja::MATERIA,
            'motivo' => $motivo,
            'fecha' => $fecha,
            'registrado_por' => $usuario?->getKey(),
        ]);

        $antes = $inscripcion->estatus;
        $inscripcion->forceFill([
            'estatus' => EstatusInscripcionMateria::BAJA->value,
            'fecha_baja' => now(),
        ])->save();

        $this->auditoria->registrar(
            $inscripcion,
            'estatus_cambiado',
            ['estatus' => $antes->value],
            ['estatus' => EstatusInscripcionMateria::BAJA->value],
            ['baja_id' => $baja->id, 'motivo' => $motivo],
            $usuario,
        );

        $this->auditoria->registrar(
            $baja,
            'baja_registrada',
            [],
            ['tipo' => TipoBaja::MATERIA->value, 'estatus_inscripcion' => EstatusInscripcionMateria::BAJA->value],
            ['motivo' => $motivo, 'fecha' => $fecha],
            $usuario,
        );

        return $baja;
    }

    /**
     * El acta del grupo (única por grupo+periodo) no puede estar cerrada.
     */
    private function actaCerrada(InscripcionMateria $inscripcion): bool
    {
        return Acta::query()
            ->where('grupo_id', $inscripcion->grupo_id)
            ->where('periodo_id', $inscripcion->grupo->periodo_id)
            ->cerradas()
            ->exists();
    }

    /**
     * Baja las matrículas ACTIVA del alumno al estatus indicado.
     *
     * Sólo se toman las ACTIVA, así que no puede producirse una transición
     * fuera de la matriz permitida (EGRESADO es terminal, por ejemplo).
     *
     * @return int matrículas afectadas
     */
    private function bajarMatriculasActivas(
        Alumno $alumno,
        EstatusMatriculaPeriodo $estatus,
        Baja $baja,
        string $motivo,
        ?User $usuario,
    ): int {
        $matriculas = MatriculaPeriodo::where('alumno_id', $alumno->id)
            ->where('estatus', EstatusMatriculaPeriodo::ACTIVA->value)
            ->get();

        foreach ($matriculas as $matricula) {
            $antes = $matricula->estatus;
            $matricula->forceFill(['estatus' => $estatus->value])->save();

            $this->auditoria->registrar(
                $matricula,
                'estatus_cambiado',
                ['estatus' => $antes->value],
                ['estatus' => $estatus->value],
                ['baja_id' => $baja->id, 'motivo' => $motivo],
                $usuario,
            );
        }

        return $matriculas->count();
    }

    /**
     * Autorización server-side (Gate::before da la capacidad a ADMIN).
     */
    private function autorizar(string $capacidad, ?Baja $baja, ?User $usuario): void
    {
        if ($usuario !== null) {
            $baja === null
                ? Gate::forUser($usuario)->authorize($capacidad)
                : Gate::forUser($usuario)->authorize($capacidad, $baja);

            return;
        }

        $baja === null
            ? Gate::authorize($capacidad)
            : Gate::authorize($capacidad, $baja);
    }
}
