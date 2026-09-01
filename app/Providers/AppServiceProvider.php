<?php

namespace App\Providers;

use App\Contracts\NotificadorInterface;
use App\Services\Notificadores\WhatsappNotificador;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * DEPENDENCY INVERSION: interfaz -> implementación concreta.
     * NotificadorInterface tiene una sola implementación por defecto (binding 1:1).
     * ExportadorInterface NO se bindea acá: tiene varias implementaciones y se
     * resuelve vía ExportadorFactory según el formato pedido.
     */
    public function register(): void
    {
        $this->app->bind(NotificadorInterface::class, WhatsappNotificador::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
