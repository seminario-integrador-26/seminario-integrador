<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Grupo destinatario de notificaciones. Campo: nombre.
 * N:M con TipoEvento; 1:N con Contacto.
 */
class GrupoInteresado extends Model
{
    // TODO: $fillable
    // TODO: relaciones -> tiposEvento() (belongsToMany), contactos() (hasMany)
}
