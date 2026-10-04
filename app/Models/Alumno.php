<?php

namespace App\Models;

use App\Enums\Sexo;
use Database\Factories\AlumnoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Expediente del alumno: entidad PERMANENTE e independiente de cada inscripción.
 *
 * `numero_control` pertenece al alumno (D3), queda NULL hasta la Fase 12 y no es
 * editable por el usuario (fuera de `$fillable`).
 * `curp` es sintaxis local (§12.1) + unicidad local; no consulta RENAPO.
 */
class Alumno extends Model
{
    use HasFactory, SoftDeletes;

    /** Estructura de CURP mexicana (18 chars): sintáctica, no semántica. */
    public const REGEX_CURP = '/^[A-Z]{4}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HM][A-Z]{5}[0-9A-Z]\d$/';

    /** @var list<string> (numero_control NO se masa-asigna: solo Fase 12) */
    protected $fillable = [
        'nombre',
        'apellidos',
        'curp',
        'fecha_nacimiento',
        'sexo',
        'nacionalidad',
        'correo',
        'telefono',
        'calle',
        'colonia',
        'cp',
        'municipio',
        'estado',
        'pais',
        'creado_por',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'sexo' => Sexo::class,
        ];
    }

    protected function setCurpAttribute(string $valor): void
    {
        $this->attributes['curp'] = preg_replace('/\s+/', '', strtoupper($valor));
    }

    protected function setCorreoAttribute(?string $valor): void
    {
        $this->attributes['correo'] = $valor === null || $valor === '' ? null : strtolower(trim($valor));
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(Contacto::class);
    }

    public function informacionMedica(): HasOne
    {
        return $this->hasOne(InformacionMedica::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Matrículas por periodo del alumno.
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(MatriculaPeriodo::class, 'alumno_id');
    }

    /**
     * Documentos generados (formatos académicos) del alumno.
     */
    public function documentosGenerados(): HasMany
    {
        return $this->hasMany(DocumentoGenerado::class, 'alumno_id');
    }

    /** Búsqueda por nombre, apellidos, CURP o número de control. */
    public function scopeBuscar(Builder $consulta, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $consulta;
        }

        return $consulta->where(function (Builder $interna) use ($termino): void {
            $interna->where('nombre', 'like', "%{$termino}%")
                ->orWhere('apellidos', 'like', "%{$termino}%")
                ->orWhere('curp', 'like', "%{$termino}%")
                ->orWhere('numero_control', 'like', "%{$termino}%");
        });
    }

    protected static function newFactory(): AlumnoFactory
    {
        return AlumnoFactory::new();
    }
}
