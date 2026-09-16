<?php

namespace App\Contracts;

/**
 * Contrato de un canal de notificación (WhatsApp, y a futuro email/SMS).
 *
 * INTERFACE SEGREGATION: interfaz mínima, un solo método.
 * LISKOV: cualquier implementación (WhatsappNotificador, un fake en tests)
 * debe poder reemplazar a otra sin que el llamador se entere.
 * DEPENDENCY INVERSION: los Services dependen de ESTA interfaz, nunca de
 * la clase concreta. El binding se resuelve en un ServiceProvider / Factory.
 */
interface NotificadorInterface
{
    /**
     * @param  array  $destinatarios  Contactos del grupo interesado.
     * @param  string  $mensaje  Cuerpo a enviar.
     */
    public function notificar(array $destinatarios, string $mensaje): void;
}
