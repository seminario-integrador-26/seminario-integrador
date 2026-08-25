<?php

namespace App\Listeners;

use App\Events\EventoRegistrado;
use App\Jobs\EnviarNotificacionWhatsapp;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Escucha EventoRegistrado y despacha la notificación a los grupos interesados.
 *
 * ShouldQueue: corre en cola -> el registro del evento responde rápido y no
 * se ve afectado si WhatsApp está lento o caído.
 * Registrado en EventServiceProvider (evento -> listener).
 */
class NotificarInteresadosListener implements ShouldQueue
{
    public function handle(EventoRegistrado $event): void
    {
        // TODO: resolver GrupoInteresado/Contactos según el TipoEvento del evento
        //       y despachar el Job por cada grupo correspondiente.
        // EnviarNotificacionWhatsapp::dispatch(...);
    }
}
