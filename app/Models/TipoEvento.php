<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Campos: nombre, categoria (prevención | convivencia urbana | seguridad pública),
 * requiere_ubicacion (bool)  -> si false, el Evento puede no tener PuntoMonitoreo.
 * es_cuantificable (bool)     -> si false, NO cuenta en estadísticas.
 * Ambos flags son independientes entre sí.
 *
 * Relación N:M con GrupoInteresado (a quién se notifica según clasificación).
 */
class TipoEvento extends Model
{
    // TODO: $fillable, casts (requiere_ubicacion, es_cuantificable -> bool)
    // TODO: relaciones -> gruposInteresados() (belongsToMany), eventos()
}
