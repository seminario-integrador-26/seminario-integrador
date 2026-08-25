<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Evento detectado por videovigilancia. INMUTABLE tras su creación:
 * sin edición directa a nivel negocio; toda corrección va por Errata.
 *
 * Campos: tipo_evento_id, punto_monitoreo_id (nullable, ver requiere_ubicacion),
 * turno_id, usuario_id (operador), fecha_hora, descripcion, area_derivada,
 * asistencia_operativo (bool).
 */
class Evento extends Model
{
    // TODO: $fillable, casts (fecha_hora -> datetime, asistencia_operativo -> bool)
    // TODO: relaciones -> tipoEvento(), puntoMonitoreo(), turno(), operador(), erratas()
}
