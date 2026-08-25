<?php

namespace App\Factories;

use App\Contracts\ExportadorInterface;
use App\Services\Exportadores\PdfExportador;
use App\Services\Exportadores\XlsxExportador;
use InvalidArgumentException;

/**
 * FACTORY: resuelve qué ExportadorInterface instanciar según el formato.
 * Como hay más de una implementación, NO se puede bindear 1:1 en el
 * container; por eso se centraliza la decisión acá.
 */
class ExportadorFactory
{
    public function crear(string $formato): ExportadorInterface
    {
        return match ($formato) {
            'xlsx' => new XlsxExportador(),
            'pdf'  => new PdfExportador(),
            default => throw new InvalidArgumentException("Formato no soportado: {$formato}"),
        };
    }
}
