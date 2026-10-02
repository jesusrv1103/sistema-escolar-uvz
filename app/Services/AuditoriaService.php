<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Único punto de escritura de la tabla `auditoria` (§10.1).
 *
 * - Funciona sin contexto HTTP (tests/CLI): si no hay Request utilizable,
 *   ip/user_agent quedan en null.
 * - Nunca persiste información sensible: depura passwords, tokens y datos
 *   médicos de los `datos` enviados.
 */
class AuditoriaService
{
    /** Claves jamás persistidas en `auditoria.datos`. */
    private const CLAVES_PROHIBIDAS = [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'secret',
        'api_key',
        'alergias',
        'condiciones',
        'medicamentos',
        'detalle_discapacidad',
        'tipo_sangre',
    ];

    /**
     * @param array<string, mixed> $antes  ej. ['estatus' => 'DRAFT']
     * @param array<string, mixed> $despues ej. ['estatus' => 'DATA_COMPLETE']
     * @param array<string, mixed> $datos  payload mínimo necesario (ej. motivo)
     */
    public function registrar(
        Model $modelo,
        string $accion,
        array $antes = [],
        array $despues = [],
        array $datos = [],
        ?User $usuario = null,
    ): Auditoria {
        $usuario ??= auth()->user();
        [$ip, $agente] = $this->contextoHttp();

        $payload = $this->depurar($datos);

        return Auditoria::create([
            'auditable_type' => $modelo->getMorphClass(),
            'auditable_id' => $modelo->getKey(),
            'user_id' => $usuario?->getKey(),
            'accion' => $accion,
            'estado_anterior' => $this->valorEstado($antes['estatus'] ?? null),
            'estado_nuevo' => $this->valorEstado($despues['estatus'] ?? null),
            'datos' => $payload === [] ? null : $payload,
            'ip' => $ip,
            'user_agent' => $agente,
        ]);
    }

    private function valorEstado(mixed $valor): ?string
    {
        if ($valor instanceof \BackedEnum) {
            return (string) $valor->value;
        }

        return $valor === null ? null : (string) $valor;
    }

    /** @return array{0: ?string, 1: ?string} [ip, user_agent] */
    private function contextoHttp(): array
    {
        try {
            $request = request();

            if (! $request instanceof Request) {
                return [null, null];
            }

            $ip = $request->ip() ?: null;
            $agente = $request->userAgent() ?: null;

            return [$ip, $agente === null ? null : Str::limit($agente, 255, '')];
        } catch (Throwable) {
            return [null, null];
        }
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    private function depurar(array $datos): array
    {
        $resultado = [];

        foreach ($datos as $clave => $valor) {
            if (is_string($clave) && in_array(strtolower($clave), self::CLAVES_PROHIBIDAS, true)) {
                continue;
            }

            $resultado[$clave] = is_array($valor) ? $this->depurar($valor) : $valor;
        }

        return $resultado;
    }
}
