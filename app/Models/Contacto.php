<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Destinatario concreto de WhatsApp. Campos: nombre, numero_whatsapp,
 * grupo_interesado_id.
 */
class Contacto extends Model
{
    // TODO: $fillable
    // TODO: relación -> grupoInteresado()
}
