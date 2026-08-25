<?php

namespace App\Services\Notificadores;

use App\Contracts\NotificadorInterface;

/**
 * Canal de notificación por WhatsApp Cloud API (Meta). NO Twilio.
 *
 * Se invoca desde el Job EnviarNotificacionWhatsapp (en cola), nunca de
 * forma síncrona dentro del registro del evento.
 */
class WhatsappNotificador implements NotificadorInterface
{
    public function notificar(array $destinatarios, string $mensaje): void
    {
        // TODO: integrar WhatsApp Cloud API (Meta). Credenciales por .env.
    }
}
