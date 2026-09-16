<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\Turno;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Datos de la consola táctica (Dashboard): estado del turno vigente, feed de
 * eventos y capa cartográfica.
 *
 * SINGLE RESPONSIBILITY: sólo arma la foto de situación para la UI; no registra
 * ni modifica nada. Las estadísticas mensuales/anuales (RN-07) van aparte, en
 * EstadisticaCalculadorInterface.
 */
class DashboardService
{
    /**
     * Cantidad de eventos a mostrar cuando no hay ningún turno abierto.
     */
    private const EVENTOS_SIN_TURNO = 20;

    /**
     * Turno de guardia vigente con su supervisor, o null si no hay ninguno abierto.
     *
     * @return array{id: int, fecha: string, hora_inicio: string, supervisor: ?string, transcurrido: string}|null
     */
    public function turnoVigente(): ?array
    {
        $turno = Turno::activo()?->load('supervisor:id,name');

        if (! $turno) {
            return null;
        }

        return [
            'id' => $turno->id,
            'fecha' => $turno->fecha->format('d/m/Y'),
            'hora_inicio' => substr($turno->hora_inicio, 0, 5),
            'supervisor' => $turno->supervisor?->name,
            'transcurrido' => $this->transcurrido($turno),
        ];
    }

    /**
     * Eventos del turno vigente (o los últimos registrados si no hay turno abierto),
     * del más reciente al más antiguo, ya aplanados para la UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function eventosDelTurno(?int $turnoId): array
    {
        return Evento::query()
            ->with([
                'tipoEvento:id,nombre,categoria,es_cuantificable',
                'puntoMonitoreo:id,codigo,nombre,latitud,longitud',
                'usuario:id,name',
            ])
            ->when(
                $turnoId,
                fn ($q) => $q->where('turno_id', $turnoId),
                fn ($q) => $q->limit(self::EVENTOS_SIN_TURNO),
            )
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Evento $e) => [
                'id' => $e->id,
                // Código legible del evento: AT-<año>-<id con 4 dígitos>.
                'codigo' => sprintf('AT-%s-%04d', $e->fecha_hora->format('Y'), $e->id),
                'categoria' => TipoEvento::CATEGORIAS[$e->tipoEvento->categoria] ?? $e->tipoEvento->categoria,
                'es_cuantificable' => $e->tipoEvento->es_cuantificable,
                'tipo' => $e->tipoEvento->nombre,
                'ubicacion' => $e->puntoMonitoreo
                    ? "{$e->puntoMonitoreo->codigo} — {$e->puntoMonitoreo->nombre}"
                    : null,
                'latitud' => $e->puntoMonitoreo?->latitud,
                'longitud' => $e->puntoMonitoreo?->longitud,
                'fecha' => $e->fecha_hora->format('d/m/Y'),
                'hora' => $e->fecha_hora->format('H:i:s'),
                'timestamp_video' => $e->timestamp_video,
                'descripcion' => $e->descripcion,
                'autor' => $e->usuario?->name,
                'turno_id' => $e->turno_id,
                // TODO: erratas del evento cuando exista la tabla de fe de errata.
            ])
            ->all();
    }

    /**
     * Desglose por eje de clasificación. Los tipos no cuantificables
     * (Informativos, RN-04) quedan fuera del conteo estadístico.
     *
     * @param  array<int, array<string, mixed>>  $eventos
     * @return array<string, int>
     */
    public function conteoPorCategoria(array $eventos): array
    {
        $conteo = array_fill_keys(array_values(TipoEvento::CATEGORIAS), 0);

        foreach ($eventos as $evento) {
            if (! $evento['es_cuantificable']) {
                continue;
            }

            $conteo[$evento['categoria']] = ($conteo[$evento['categoria']] ?? 0) + 1;
        }

        return $conteo;
    }

    /**
     * Capa cartográfica: puntos de monitoreo georreferenciados.
     *
     * @return Collection<int, PuntoMonitoreo>
     */
    public function puntosMonitoreo(): Collection
    {
        return PuntoMonitoreo::orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'jurisdiccion', 'latitud', 'longitud']);
    }

    /**
     * Totales de la red de monitoreo, por jurisdicción.
     *
     * // TODO: el modelo no registra estado operativo de la cámara (en línea /
     * // mantenimiento). Confirmar con el equipo si se releva.
     *
     * @return array{total: int, por_jurisdiccion: array<string, int>}
     */
    public function resumenPuntosMonitoreo(Collection $puntos): array
    {
        return [
            'total' => $puntos->count(),
            'por_jurisdiccion' => $puntos
                ->groupBy('jurisdiccion')
                ->map->count()
                ->all(),
        ];
    }

    /**
     * Tiempo transcurrido desde la apertura del turno, en formato "04h 12m".
     */
    private function transcurrido(Turno $turno): string
    {
        $inicio = Carbon::parse("{$turno->fecha->format('Y-m-d')} {$turno->hora_inicio}");
        $minutos = max(0, (int) $inicio->diffInMinutes(now()));

        return sprintf('%02dh %02dm', intdiv($minutos, 60), $minutos % 60);
    }
}
