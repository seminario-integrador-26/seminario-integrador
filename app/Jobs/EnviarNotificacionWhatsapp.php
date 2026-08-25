<?php

namespace App\Jobs;

use App\Contracts\NotificadorInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job en cola (QUEUE_CONNECTION=database) que ejecuta el envío real.
 *
 * DEPENDENCY INVERSION: recibe NotificadorInterface por inyección en handle();
 * el binding concreto (WhatsappNotificador) lo resuelve el container.
 */
class EnviarNotificacionWhatsapp implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private array $destinatarios,
        private string $mensaje,
    ) {
    }

    public function handle(NotificadorInterface $notificador): void
    {
        // TODO: $notificador->notificar($this->destinatarios, $this->mensaje);
    }
}
