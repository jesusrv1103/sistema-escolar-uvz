<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Mostrar el formulario de acceso.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Autenticar al usuario con rate limiting por correo + IP.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Intente de nuevo en {$seconds} segundos.",
            ]);
        }

        if (! Auth::attempt($credentials + ['activo' => true], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        $request->user()->forceFill(['last_login_at' => now()])->save();

        // Portal de docente (2.0-15): el DOCENTE entra a su portal y no al
        // panel operativo administrativo (que le devolvería 403).
        $destino = $request->user()->rol->value === 'DOCENTE'
            ? 'docente.inicio'
            : 'dashboard';

        return redirect()->intended(route($destino));
    }

    /**
     * Cerrar la sesión.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
