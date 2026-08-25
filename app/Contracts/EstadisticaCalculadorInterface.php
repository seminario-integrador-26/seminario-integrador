<?php

namespace App\Contracts;

use DateTimeInterface;

/**
 * Contrato para un algoritmo de cálculo estadístico (mensual, anual, etc.).
 *
 * STRATEGY: EstadisticaService elige el calculador según el período pedido,
 * sin if/switch gigantes. OPEN/CLOSED: un período nuevo = una clase nueva.
 *
 * Recordatorio de dominio: sólo se cuentan TipoEvento con es_cuantificable = true.
 */
interface EstadisticaCalculadorInterface
{
    /**
     * Cantidad de eventos por tipo y por ubicación dentro del período.
     */
    public function calcular(DateTimeInterface $desde, DateTimeInterface $hasta): array;
}
