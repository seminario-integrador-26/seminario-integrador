<?php

namespace App\Factories;

use App\Contracts\NotificadorInterface;
use App\Services\Notificadores\WhatsappNotificador;
use InvalidArgumentException;

/**
 * FACTORY: resuelve el canal de notificación concreto. Hoy sólo WhatsApp;
 * a futuro email/SMS se agregan acá sin tocar a quien la usa (OPEN/CLOSED).
 */
class NotificadorFactory
{
    public function crear(string $canal = 'whatsapp'): NotificadorInterface
    {
        return match ($canal) {
            'whatsapp' => new WhatsappNotificador,
            // 'email' => new EmailNotificador(),  // TODO a futuro
            default => throw new InvalidArgumentException("Canal no soportado: {$canal}"),
        };
    }
}
