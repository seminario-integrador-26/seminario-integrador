<?php

namespace App\Providers;

use App\Events\EventoRegistrado;
use App\Listeners\AuditarAccesosListener;
use App\Listeners\NotificarInteresadosListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * OBSERVER: mapea eventos de dominio -> listeners.
 * EventoRegistrado dispara NotificarInteresadosListener (en cola).
 * Los eventos de autenticación (Login/Failed/Logout/Lockout) los toma
 * AuditarAccesosListener como subscriber (US-001, log de auditoría).
 */
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        EventoRegistrado::class => [
            NotificarInteresadosListener::class,
        ],
    ];

    protected $subscribe = [
        AuditarAccesosListener::class,
    ];
}
