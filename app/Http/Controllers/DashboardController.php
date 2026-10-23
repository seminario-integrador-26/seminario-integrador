<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Web (Inertia). Controller FINO: la foto de situación la arma DashboardService.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * Consola táctica de monitoreo: turno vigente, feed de eventos y cartografía.
     */
    public function index(): Response
    {
        $apertura = $this->dashboard->aperturaVigente();
        $eventos = $this->dashboard->eventosDelTurno($apertura);
        $puntos = $this->dashboard->puntosMonitoreo();

        return Inertia::render('Dashboard', [
            'turno' => $this->dashboard->turnoVigente($apertura),
            'eventos' => $eventos,
            'conteoPorCategoria' => $this->dashboard->conteoPorCategoria($eventos),
            'puntosMonitoreo' => $puntos,
            'resumenPuntos' => $this->dashboard->resumenPuntosMonitoreo($puntos),
        ]);
    }
}
