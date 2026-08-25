<?php

namespace App\Services\Exportadores;

use App\Contracts\ExportadorInterface;

/**
 * Estrategia de exportación a .xlsx (datos abiertos).
 * Implementación real: maatwebsite/laravel-excel.
 */
class XlsxExportador implements ExportadorInterface
{
    public function exportar(iterable $datos): string
    {
        // TODO: implementar con maatwebsite/laravel-excel
        return '';
    }
}
