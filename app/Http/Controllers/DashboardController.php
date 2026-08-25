<?php

namespace App\Http\Controllers;

use App\Services\EstadisticaService;

/**
 * Web (Inertia). Pasa los agregados como props -> Recharts los grafica.
 */
class DashboardController extends Controller
{
    public function __construct(
        private EstadisticaService $estadisticas,
    ) {
    }

    // TODO: index() -> Inertia::render('Dashboard/Index', ['series' => $this->estadisticas->...])
}
