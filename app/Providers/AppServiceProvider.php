<?php

namespace App\Providers;

use App\Contracts\NotificadorInterface;
use App\Services\Notificadores\WhatsappNotificador;
use Illuminate\Support\ServiceProvider;

/**
 * DEPENDENCY INVERSION: acá se cablea interfaz -> implementación concreta.
 *
 * NotificadorInterface tiene UNA sola implementación por defecto -> binding 1:1.
 * ExportadorInterface NO se bindea acá: tiene varias implementaciones y se
 * resuelve vía ExportadorFactory según el formato pedido.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificadorInterface::class, WhatsappNotificador::class);
    }

    public function boot(): void
    {
        //
    }
}
