<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "Fe de errata": único mecanismo de corrección de un Evento. Tabla separada,
 * NO una columna en eventos. Referencia al evento original sin modificarlo.
 *
 * Campos: evento_id, campo_corregido, valor_anterior, valor_nuevo, motivo,
 * usuario_id, created_at.
 */
class Errata extends Model
{
    // TODO: $fillable
    // TODO: relaciones -> evento(), autor()
}
