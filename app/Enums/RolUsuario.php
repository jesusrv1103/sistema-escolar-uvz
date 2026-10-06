<?php

namespace App\Enums;

/**
 * Un único rol por usuario (D1 aprobado). ADMIN realiza todas las acciones vía Gate::before.
 */
enum RolUsuario: string
{
    case ADMIN = 'ADMIN';
    case ADMISIONES = 'ADMISIONES';
    case ADMINISTRACION = 'ADMINISTRACION';
    case CONTROL_ESCOLAR = 'CONTROL_ESCOLAR';
    case DOCENTE = 'DOCENTE';
    case ALUMNO = 'ALUMNO';

    /** Etiqueta visible en español; nunca exponer `->value` al usuario. */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrador',
            self::ADMISIONES => 'Admisiones',
            self::ADMINISTRACION => 'Administración',
            self::CONTROL_ESCOLAR => 'Control Escolar',
            self::DOCENTE => 'Docente',
            self::ALUMNO => 'Alumno',
        };
    }
}
