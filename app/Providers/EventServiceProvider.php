<?php

namespace App\Providers;

use App\Events\EventoRegistrado;
use App\Listeners\NotificarInteresadosListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * OBSERVER: mapea eventos de dominio -> listeners.
 * EventoRegistrado dispara NotificarInteresadosListener (en cola).
 */
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        EventoRegistrado::class => [
            NotificarInteresadosListener::class,
        ],
    ];
}
