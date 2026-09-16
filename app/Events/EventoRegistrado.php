<?php

namespace App\Events;

use App\Models\Evento;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de dominio: se dispara cuando EventoService persiste un Evento nuevo.
 *
 * OBSERVER (via Laravel Events & Listeners): desacopla el registro del evento
 * de sus side-effects (notificación). El que dispara no sabe quién escucha.
 */
class EventoRegistrado
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Evento $evento,
    ) {}
}
