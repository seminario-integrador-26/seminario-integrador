<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ubicación física de una cámara/punto. Campos: nombre, latitud, longitud.
 * latitud/longitud alimentan Leaflet + OpenStreetMap en el frontend.
 */
class PuntoMonitoreo extends Model
{
    // TODO: $fillable, casts (latitud, longitud -> decimal/float)
    // TODO: relación -> eventos()
}
