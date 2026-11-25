<?php

namespace App\Services\Tableros;

use App\Models\Evento;
use App\Models\GrupoEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tablero de Análisis Estratégico y Tendencias (rango de fechas).
 *
 * Solo novedades vigentes de tipos cuantificables (RN4), como las
 * estadísticas. Se lee una vez el rango y se agrega en PHP.
 */
class TableroEstrategicoService
{
    public const TOP_PUNTOS = 5;

    /** Lunes a domingo (Carbon: 1 = lunes … 7 = domingo con isoWeekday). */
    public const DIAS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    /**
     * Rango elegido; por defecto, el mes en curso hasta hoy.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{desde: Carbon, hasta: Carbon}
     */
    public function rango(array $filtros): array
    {
        return [
            'desde' => isset($filtros['desde']) ? Carbon::parse($filtros['desde'])->startOfDay() : now()->startOfMonth(),
            'hasta' => isset($filtros['hasta']) ? Carbon::parse($filtros['hasta'])->endOfDay() : now()->endOfDay(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function calcular(array $filtros): array
    {
        ['desde' => $desde, 'hasta' => $hasta] = $this->rango($filtros);
        $categorias = $filtros['categorias'] ?? [];

        $eventos = $this->cuantificables($categorias)
            ->whereBetween('eventos.fecha_hora', [$desde, $hasta])
            ->leftJoin('puntos_monitoreo', 'puntos_monitoreo.id', '=', 'eventos.punto_monitoreo_id')
            ->get([
                'eventos.fecha_hora',
                'grupos_evento.codigo as categoria',
                'puntos_monitoreo.id as punto_id',
                'puntos_monitoreo.codigo',
                'puntos_monitoreo.nombre',
            ])
            ->map(fn ($e) => [
                'fecha' => Carbon::parse($e->fecha_hora),
                'categoria' => $e->categoria,
                'punto_id' => $e->punto_id,
                'codigo' => $e->codigo,
                'nombre' => $e->nombre,
            ]);

        return [
            'periodo' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()],
            'variacion' => $this->variacion($categorias, $eventos->count(), $desde, $hasta),
            'perfil_temporal' => $this->perfilTemporal($eventos),
            'top_puntos' => $this->topPuntos($eventos),
            'tendencia' => $this->tendencia($eventos, $desde, $hasta),
            'actualizado' => now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * KPI 1: el total del rango contra el período anterior de igual duración
     * (intermensual si el rango es un mes) y contra el mismo rango un año antes
     * (interanual).
     *
     * @param  list<string>  $categorias
     */
    public function variacion(array $categorias, int $total, Carbon $desde, Carbon $hasta): array
    {
        $dias = (int) $desde->copy()->startOfDay()->diffInDays($hasta->copy()->startOfDay()) + 1;
        // Rango de meses completos (p. ej. 01/10 al 31/10): el anterior es el mes anterior entero.
        $mesesCompletos = $desde->day === 1 && $hasta->isSameDay($hasta->copy()->endOfMonth());

        if ($mesesCompletos) {
            $meses = (int) round($desde->diffInMonths($hasta->copy()->addSecond()));
            $anteriorDesde = $desde->copy()->subMonthsNoOverflow($meses);
            $anteriorHasta = $desde->copy()->subSecond();
        } else {
            $anteriorDesde = $desde->copy()->subDays($dias);
            $anteriorHasta = $hasta->copy()->subDays($dias);
        }

        $interanualDesde = $desde->copy()->subYearNoOverflow();
        $interanualHasta = $hasta->copy()->subYearNoOverflow();

        $contar = fn (Carbon $d, Carbon $h) => $this->cuantificables($categorias)
            ->whereBetween('eventos.fecha_hora', [$d, $h])
            ->count();

        $comparar = function (Carbon $d, Carbon $h) use ($contar, $total) {
            $anterior = $contar($d, $h);

            return [
                'desde' => $d->toDateString(),
                'hasta' => $h->toDateString(),
                'total' => $anterior,
                'variacion' => $anterior > 0 ? round(($total - $anterior) * 100 / $anterior, 1) : null,
            ];
        };

        return [
            'total' => $total,
            'dias' => $dias,
            'anterior' => $comparar($anteriorDesde, $anteriorHasta),
            'interanual' => $comparar($interanualDesde, $interanualHasta),
        ];
    }

    /**
     * KPI 2: matriz de calor temporal, día de la semana (lunes a domingo) por
     * hora del día (0 a 23).
     *
     * @param  Collection<int, array<string, mixed>>  $eventos
     * @return array{dias: list<string>, celdas: list<list<int>>, maximo: int}
     */
    public function perfilTemporal(Collection $eventos): array
    {
        $celdas = array_fill(0, 7, array_fill(0, 24, 0));

        foreach ($eventos as $e) {
            $celdas[$e['fecha']->isoWeekday() - 1][$e['fecha']->hour]++;
        }

        return [
            'dias' => self::DIAS,
            'celdas' => $celdas,
            'maximo' => max(array_map('max', $celdas)),
        ];
    }

    /**
     * KPI 3: los PMs con más novedades del rango (las sin ubicación no cuentan).
     *
     * @param  Collection<int, array<string, mixed>>  $eventos
     * @return list<array{id: int, codigo: string, nombre: string, cantidad: int, porcentaje: float}>
     */
    public function topPuntos(Collection $eventos): array
    {
        $conPunto = $eventos->whereNotNull('punto_id');
        $total = $conPunto->count();

        return $conPunto
            ->groupBy('punto_id')
            ->map(fn (Collection $g) => [
                'id' => (int) $g->first()['punto_id'],
                'codigo' => $g->first()['codigo'],
                'nombre' => $g->first()['nombre'],
                'cantidad' => $g->count(),
                'porcentaje' => round($g->count() * 100 / $total, 1),
            ])
            ->sortBy([['cantidad', 'desc'], ['codigo', 'asc']])
            ->take(self::TOP_PUNTOS)
            ->values()
            ->all();
    }

    /**
     * Línea de tendencia: novedades por día y por grupo de evento.
     *
     * @param  Collection<int, array<string, mixed>>  $eventos
     * @return list<array<string, int|string>>
     */
    public function tendencia(Collection $eventos, Carbon $desde, Carbon $hasta): array
    {
        $porDia = $eventos->groupBy(fn ($e) => $e['fecha']->toDateString());
        $claves = array_keys(GrupoEvento::opciones());
        $dias = [];

        for ($dia = $desde->copy()->startOfDay(); $dia->lte($hasta); $dia->addDay()) {
            $delDia = $porDia->get($dia->toDateString(), collect());
            $fila = ['fecha' => $dia->toDateString(), 'etiqueta' => $dia->format('d/m'), 'total' => $delDia->count()];

            foreach ($claves as $clave) {
                $fila[$clave] = $delDia->where('categoria', $clave)->count();
            }

            $dias[] = $fila;
        }

        return $dias;
    }

    /**
     * @param  list<string>  $categorias
     * @return Builder<Evento>
     */
    private function cuantificables(array $categorias): Builder
    {
        return Evento::vigentes()
            ->join('tipos_evento', 'tipos_evento.id', '=', 'eventos.tipo_evento_id')
            ->join('grupos_evento', 'grupos_evento.id', '=', 'tipos_evento.grupo_evento_id')
            ->where('tipos_evento.es_cuantificable', true)
            ->when($categorias, fn (Builder $q) => $q->whereIn('grupos_evento.codigo', $categorias));
    }
}
