<?php

namespace App\Services\Exportadores;

use App\Contracts\ExportadorInterface;

/**
 * Estrategia de exportación a PDF (reporte de turno).
 * Implementación real: barryvdh/laravel-dompdf, renderizando una vista
 * Blade PURA de resources/views/reportes/ (sin Inertia).
 */
class PdfExportador implements ExportadorInterface
{
    public function exportar(iterable $datos): string
    {
        // TODO: implementar con barryvdh/laravel-dompdf + vista Blade en resources/views/reportes
        return '';
    }
}
