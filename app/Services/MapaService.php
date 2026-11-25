<?php

namespace App\Services;

use App\Models\Evento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * US-016 / US-017: datos del mapa de eventos y del mapa de calor.
 *
 * Se agrega por Punto de Monitoreo (los eventos se ubican en la posición de su
 * PM). Quedan afuera los eventos sin ubicación, los de PMs todavía sin
 * coordenadas (no hay dónde dibujarlos) y los tipos no cuantificables
 * (Informativos, RN4), igual que en las estadísticas.
 */
class MapaService
{
    /**
     * Período por defecto cuando no se elige uno.
     */
    public const DIAS_POR_DEFECTO = 30;

    /**
     * Máximo de marcadores individuales (US-016): más que esto vuelve lento
     * el navegador; el mapa de calor y los PMs siguen contando todo.
     */
    public const MAX_EVENTOS = 2000;

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{desde: string, hasta: string}
     */
    public function periodo(array $filtros): array
    {
        return [
            'desde' => $filtros['desde'] ?? now()->subDays(self::DIAS_POR_DEFECTO - 1)->toDateString(),
            'hasta' => $filtros['hasta'] ?? now()->toDateString(),
        ];
    }

    /**
     * Fecha de la primera novedad cuantificable (para "todo hasta la fecha");
     * hoy si todavía no hay ninguna.
     */
    public function primeraFecha(): string
    {
        $primera = Evento::vigentes()
            ->join('tipos_evento', 'tipos_evento.id', '=', 'eventos.tipo_evento_id')
            ->where('tipos_evento.es_cuantificable', true)
            ->min('eventos.fecha_hora');

        return $primera ? Carbon::parse($primera)->toDateString() : now()->toDateString();
    }

    /**
     * Cantidad de eventos por PM en el período, con sus coordenadas.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{puntos: list<array<string, mixed>>, total: int, sin_ubicacion: int, sin_coordenadas: int}
     */
    public function densidad(array $filtros): array
    {
        $periodo = $this->periodo($filtros);
        $base = fn () => $this->cuantificables($filtros, $periodo);

        $conPunto = fn () => $base()
            ->join('puntos_monitoreo', 'puntos_monitoreo.id', '=', 'eventos.punto_monitoreo_id');

        $puntos = $conPunto()
            ->join('tipos_punto_monitoreo', 'tipos_punto_monitoreo.id', '=', 'puntos_monitoreo.tipo_punto_monitoreo_id')
            // Sin coordenadas, (float) null daría 0 y el PM aparecería en (0, 0).
            ->whereNotNull('puntos_monitoreo.latitud')
            ->whereNotNull('puntos_monitoreo.longitud')
            ->groupBy(
                'puntos_monitoreo.id', 'puntos_monitoreo.codigo', 'puntos_monitoreo.nombre',
                'tipos_punto_monitoreo.codigo', 'tipos_punto_monitoreo.nombre',
                'puntos_monitoreo.latitud', 'puntos_monitoreo.longitud',
            )
            ->orderByDesc(DB::raw('count(*)'))
            ->get([
                'puntos_monitoreo.id',
                'puntos_monitoreo.codigo',
                'puntos_monitoreo.nombre',
                'tipos_punto_monitoreo.codigo as tipo',
                'tipos_punto_monitoreo.nombre as tipo_nombre',
                'puntos_monitoreo.latitud',
                'puntos_monitoreo.longitud',
                DB::raw('count(*) as cantidad'),
            ])
            ->map(fn ($p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                // Código de TipoPuntoMonitoreo (municipal | provincial | alarma).
                'tipo' => $p->tipo,
                'tipo_nombre' => $p->tipo_nombre,
                'latitud' => (float) $p->latitud,
                'longitud' => (float) $p->longitud,
                'cantidad' => (int) $p->cantidad,
            ])
            ->all();

        return [
            'puntos' => $puntos,
            'total' => array_sum(array_column($puntos, 'cantidad')),
            'sin_ubicacion' => $base()->whereNull('eventos.punto_monitoreo_id')->count(),
            // Eventos con PM, pero de un PM que todavía no tiene coordenadas.
            'sin_coordenadas' => $conPunto()
                ->where(fn (Builder $q) => $q
                    ->whereNull('puntos_monitoreo.latitud')
                    ->orWhereNull('puntos_monitoreo.longitud'))
                ->count(),
        ];
    }

    /**
     * US-016: un marcador por evento, en la posición de su PM, del más
     * reciente al más antiguo (hasta MAX_EVENTOS).
     *
     * @param  array<string, mixed>  $filtros
     * @return array{items: list<array<string, mixed>>, total: int, limite: int}
     */
    public function eventos(array $filtros): array
    {
        $periodo = $this->periodo($filtros);
        $consulta = fn () => $this->cuantificables($filtros, $periodo)
            ->join('puntos_monitoreo', 'puntos_monitoreo.id', '=', 'eventos.punto_monitoreo_id')
            ->join('tipos_punto_monitoreo', 'tipos_punto_monitoreo.id', '=', 'puntos_monitoreo.tipo_punto_monitoreo_id')
            ->whereNotNull('puntos_monitoreo.latitud')
            ->whereNotNull('puntos_monitoreo.longitud');

        $items = $consulta()
            ->orderByDesc('eventos.fecha_hora')
            ->orderByDesc('eventos.id')
            ->limit(self::MAX_EVENTOS)
            ->get([
                'eventos.id',
                'eventos.fecha_hora',
                'eventos.descripcion',
                'tipos_evento.nombre as tipo',
                'grupos_evento.codigo as categoria',
                'grupos_evento.nombre as categoria_nombre',
                'puntos_monitoreo.id as punto_id',
                'puntos_monitoreo.codigo as punto_codigo',
                'puntos_monitoreo.nombre as punto_nombre',
                'tipos_punto_monitoreo.codigo as tipo_punto',
                'puntos_monitoreo.latitud',
                'puntos_monitoreo.longitud',
            ])
            ->map(function ($e) {
                $fechaHora = Carbon::parse($e->fecha_hora);

                return [
                    'id' => $e->id,
                    'fecha' => $fechaHora->format('d/m/Y'),
                    'hora' => $fechaHora->format('H:i'),
                    'tipo' => $e->tipo,
                    // Código del GrupoEvento: elige el color del marcador.
                    'categoria' => $e->categoria,
                    'categoria_nombre' => $e->categoria_nombre,
                    'descripcion' => $e->descripcion,
                    'punto_id' => $e->punto_id,
                    'punto' => "{$e->punto_codigo} — {$e->punto_nombre}",
                    // municipal | provincial | alarma: elige la forma del marcador.
                    'tipo_punto' => $e->tipo_punto,
                    'latitud' => (float) $e->latitud,
                    'longitud' => (float) $e->longitud,
                ];
            })
            ->all();

        return [
            'items' => $items,
            'total' => count($items) < self::MAX_EVENTOS ? count($items) : $consulta()->count(),
            'limite' => self::MAX_EVENTOS,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array{desde: string, hasta: string}  $periodo
     * @return Builder<Evento>
     */
    private function cuantificables(array $filtros, array $periodo): Builder
    {
        return Evento::vigentes()
            ->join('tipos_evento', 'tipos_evento.id', '=', 'eventos.tipo_evento_id')
            ->join('grupos_evento', 'grupos_evento.id', '=', 'tipos_evento.grupo_evento_id')
            ->where('tipos_evento.es_cuantificable', true)
            ->where('eventos.fecha_hora', '>=', Carbon::parse($periodo['desde'])->startOfDay())
            ->where('eventos.fecha_hora', '<', Carbon::parse($periodo['hasta'])->addDay()->startOfDay())
            ->when($filtros['categorias'] ?? null, fn (Builder $q, array $c) => $q->whereIn('grupos_evento.codigo', $c))
            ->when($filtros['tipo_evento_id'] ?? null, fn (Builder $q, $id) => $q->where('eventos.tipo_evento_id', (int) $id))
            // Tablero Mapa de calor: uno, varios o (vacío) todos los tipos.
            ->when($filtros['tipos'] ?? null, fn (Builder $q, array $ids) => $q->whereIn('eventos.tipo_evento_id', array_map('intval', $ids)));
    }
}
