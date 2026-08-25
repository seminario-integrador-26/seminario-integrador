<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario del sistema. Roles vía spatie/laravel-permission:
 * SÓLO 'Operador' y 'Administrador' (no agregar otros).
 * Campos: nombre, email, password (+ roles por spatie).
 *
 * Nota: Breeze genera App\Models\User. Definir si se usa ese o este alias
 * en español. // TODO: confirmar con el equipo el nombre del modelo de auth.
 */
class Usuario extends Authenticatable
{
    use HasRoles;

    // TODO: $fillable, $hidden, casts
    // TODO: relaciones -> eventos() (como operador), turnos()
}
