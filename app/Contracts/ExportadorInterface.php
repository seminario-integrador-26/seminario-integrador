<?php

namespace App\Contracts;

/**
 * Contrato de un exportador de datos (XLSX, PDF).
 *
 * OPEN/CLOSED + STRATEGY: agregar un formato nuevo = agregar una clase que
 * implemente esta interfaz. NO se toca código existente.
 * INTERFACE SEGREGATION: sólo exportar().
 */
interface ExportadorInterface
{
    /**
     * @param  iterable $datos  Colección/consulta a serializar.
     * @return string           Ruta o contenido del archivo generado.
     */
    public function exportar(iterable $datos): string;
}
