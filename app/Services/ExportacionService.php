<?php

namespace App\Services;

use App\Factories\ExportadorFactory;

/**
 * Punto de entrada de exportación. NO conoce el formato concreto.
 *
 * SINGLE RESPONSIBILITY: coordinar la exportación.
 * FACTORY: pide a ExportadorFactory el ExportadorInterface según el formato
 * ('xlsx' | 'pdf'), evitando if/switch en el Controller.
 */
class ExportacionService
{
    public function __construct(
        private ExportadorFactory $factory,
    ) {}

    // TODO: exportar(string $formato, iterable $datos): string
    //   $exportador = $this->factory->crear($formato);
    //   return $exportador->exportar($datos);
}
