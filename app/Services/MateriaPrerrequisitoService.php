<?php

namespace App\Services;

use App\Enums\EstatusPlanVersion;
use App\Enums\TipoPrerrequisito;
use App\Exceptions\CicloPrerrequisitoException;
use App\Exceptions\PrerrequisitoInvalidoException;
use App\Models\Materia;
use App\Models\MateriaPrerrequisito;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para gestión de prerrequisitos entre materias.
 *
 * Único punto de negocio para agregar/quitar prerrequisitos.
 * Valida reglas de integridad: no autorreferencia, misma versión, no ciclos, no duplicados.
 * Detección de ciclos mediante DFS (O(V+E)).
 */
class MateriaPrerrequisitoService
{
    /**
     * Agrega un prerrequisito a una materia.
     *
     * @throws PrerrequisitoInvalidoException si falla alguna validación
     * @throws CicloPrerrequisitoException si se detecta un ciclo
     */
    public function agregar(Materia $materia, Materia $prerrequisito, \App\Enums\TipoPrerrequisito $tipo, ?int $usuarioId = null): MateriaPrerrequisito
    {
        // 1. Validaciones básicas
        $this->validarBasico($materia, $prerrequisito, $tipo->value);

        // 2. Verificar que no exista ya
        $existe = MateriaPrerrequisito::where('materia_id', $materia->id)
            ->where('prerrequisito_id', $prerrequisito->id)
            ->exists();

        if ($existe) {
            throw new PrerrequisitoInvalidoException('La relación de prerrequisito ya existe.');
        }

        // 3. Detectar ciclos (DFS) — antes de persistir
        $this->validarSinCiclos($materia->id, $prerrequisito->id);

        // 4. Persistir en transacción
        return DB::transaction(function () use ($materia, $prerrequisito, $tipo): MateriaPrerrequisito {
            return MateriaPrerrequisito::create([
                'materia_id' => $materia->id,
                'prerrequisito_id' => $prerrequisito->id,
                'tipo' => $tipo->value,
            ]);
        });
    }

    /**
     * Quita un prerrequisito de una materia.
     */
    public function quitar(Materia $materia, Materia $prerrequisito): bool
    {
        return DB::transaction(function () use ($materia, $prerrequisito): bool {
            return MateriaPrerrequisito::where('materia_id', $materia->id)
                ->where('prerrequisito_id', $prerrequisito->id)
                ->delete() > 0;
        });
    }

    /**
     * Valida reglas básicas antes de agregar prerrequisito.
     */
    private function validarBasico(Materia $materia, Materia $prerrequisito, string $tipo): void
    {
        // 1. No autorreferencia
        if ($materia->id === $prerrequisito->id) {
            throw new PrerrequisitoInvalidoException('Una materia no puede ser prerrequisito de sí misma.');
        }

        // 2. Misma versión de plan
        if ($materia->plan_version_id !== $prerrequisito->plan_version_id) {
            throw new PrerrequisitoInvalidoException('El prerrequisito debe pertenecer a la misma versión de plan.');
        }

        // 3. Tipo válido
        try {
            \App\Enums\TipoPrerrequisito::from($tipo);
        } catch (\ValueError) {
            throw new PrerrequisitoInvalidoException("Tipo de prerrequisito inválido: {$tipo}. Valores permitidos: OBLIGATORIO, RECOMENDADO.");
        }
    }

    /**
     * Detecta ciclos mediante DFS iterativo.
     * Lanza CicloPrerrequisitoException si detecta que agregar la arista crearía un ciclo.
     */
    private function validarSinCiclos(int $materiaId, int $nuevoPrerrequisitoId): void
    {
        // Buscamos si existe un camino desde el nuevo prerrequisito hacia la materia origen
        // Si existe, al agregar la arista se formaría un ciclo.
        $visitados = [];
        $pila = [$nuevoPrerrequisitoId];

        while (!empty($pila)) {
            $actual = array_pop($pila);

            // Si llegamos a la materia origen, hay ciclo
            if ($actual === $materiaId) {
                throw new \App\Exceptions\CicloPrerrequisitoException('La relación crearía un ciclo de prerrequisitos.');
            }

            if (isset($visitados[$actual])) {
                continue;
            }

            $visitados[$actual] = true;

            // Obtener prerrequisitos del nodo actual
            $siguientes = MateriaPrerrequisito::where('materia_id', $actual)
                ->pluck('prerrequisito_id')
                ->toArray();

            $pila = array_merge($pila, $siguientes);
        }
    }
}