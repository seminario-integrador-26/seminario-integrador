<?php

namespace App\Services;

use App\Contracts\NotificadorInterface;

/**
 * Lógica de negocio del registro y consulta de eventos.
 *
 * SINGLE RESPONSIBILITY: sólo eventos. Correcciones -> ErrataService.
 * DEPENDENCY INVERSION: recibe NotificadorInterface por constructor (no la
 * clase concreta). En la práctica NO notifica de forma directa: al persistir
 * un Evento dispara el evento de dominio EventoRegistrado (OBSERVER) y un
 * Listener en cola se encarga de la notificación, para desacoplar el registro
 * del side-effect (si WhatsApp falla, el evento igual queda guardado).
 *
 * REGLA DE NEGOCIO: los eventos son INMUTABLES. Este service NO expone update().
 */
class EventoService
{
    public function __construct(
        private NotificadorInterface $notificador,
    ) {
    }

    // TODO: registrar(array $datos): Evento  -> persistir + event(new EventoRegistrado($evento))
    // TODO: buscar(array $filtros): iterable  -> filtros por tipo, fecha, horario, ubicación
}
