<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege rutas por rol (alias `role`, ej. ->middleware('role:ADMIN')).
 *
 * El acceso global de ADMIN se resuelve aquí y en Gate::before (única fuente:
 * App\Providers\AuthServiceProvider); no se dispersan excepciones de ADMIN
 * por controladores ni vistas.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->activo) {
            abort(403, 'La cuenta está inactiva.');
        }

        $requeridos = array_map(strtoupper(...), $roles);

        if (! $user->esAdmin() && ! in_array($user->rol?->value, $requeridos, true)) {
            abort(403, 'No tiene permiso para acceder a este recurso.');
        }

        return $next($request);
    }
}
