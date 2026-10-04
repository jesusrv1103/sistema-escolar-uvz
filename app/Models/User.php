<?php

namespace App\Models;

use App\Enums\RolUsuario;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<User> */
    use HasFactory, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rol',
        'activo',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => RolUsuario::class,
            'activo' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * ADMIN realiza todas las acciones vía Gate::before (D1 aprobado).
     */
    public function esAdmin(): bool
    {
        return $this->rol === RolUsuario::ADMIN;
    }

    /**
     * Perfil de docente asociado al usuario.
     */
    public function docente(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Docente::class, 'user_id');
    }

    /**
     * Obtiene o crea el perfil de docente del usuario.
     */
    public function docenteOrCreate(): Docente
    {
        return $this->docente ?? $this->docente()->create([
            'clave' => 'DOC-' . str_pad($this->id, 6, '0', STR_PAD_LEFT),
            'nombre' => $this->name,
            'apellidos' => '',
            'curp' => $this->generateCurp(),
        ]);
    }

    /**
     * Genera una CURP sintética para el docente.
     */
    private function generateCurp(): string
    {
        $anio = now()->year - 30;
        $mes = rand(1, 12);
        $dia = rand(1, 28);
        $sexo = $this->sexo ?? 'H';
        $letras = Str::random(5);
        return sprintf('DOC%02d%02d%02d%s%s%d', $anio % 100, $mes, $dia, $sexo, $letras, rand(10, 99));
    }
}