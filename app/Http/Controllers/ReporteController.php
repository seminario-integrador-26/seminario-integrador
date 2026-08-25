<?php

namespace App\Http\Controllers;

use App\Services\ExportacionService;

/**
 * Web (Inertia para la UI + genera PDF/XLSX). Controller fino.
 * Delega la generación en ExportacionService (que usa ExportadorFactory).
 * El PDF se renderiza desde una vista Blade PURA en resources/views/reportes.
 */
class ReporteController extends Controller
{
    public function __construct(
        private ExportacionService $exportacion,
    ) {
    }

    // TODO: index()  -> Inertia::render('Reportes/Index', [...])
    // TODO: exportar(Request $request) -> $this->exportacion->exportar($formato, $datos)
}
