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
}
