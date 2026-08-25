<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\EstadisticaService;

/**
 * API PÚBLICA v1, sólo lectura: agregados para Gobierno Abierto.
 */
class EstadisticaApiController extends Controller
{
    public function __construct(
        private EstadisticaService $estadisticas,
    ) {
    }

    // TODO: mensual(int $anio, int $mes)  -> JSON
    // TODO: anual(int $anio)               -> JSON
}
