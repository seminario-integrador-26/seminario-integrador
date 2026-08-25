<?php

namespace App\Services;

use App\Contracts\EstadisticaCalculadorInterface;

/**
 * Orquesta el cálculo de agregados (mensual / anual) por tipo y ubicación.
 *
 * SINGLE RESPONSIBILITY: estadísticas.
 * STRATEGY + DEPENDENCY INVERSION: delega el cómo del cálculo en un
 * EstadisticaCalculadorInterface concreto, elegido según el período.
 * REGLA DE NEGOCIO: excluir TipoEvento con es_cuantificable = false.
 */
class EstadisticaService
{
    // TODO: mensual(int $anio, int $mes): array
    // TODO: anual(int $anio): array
    // Ambos resuelven el EstadisticaCalculadorInterface adecuado (via app()/Factory).
}
