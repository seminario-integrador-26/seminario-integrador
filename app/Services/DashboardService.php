<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\GrupoEvento;
use App\Models\PuntoMonitoreo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    public function __construct(private readonly LibroGuardiaService $libro) {}

    /**
     * Turno vigente: la última Apertura de turno sin Cierre posterior.
     */
    public function aperturaVigente(): ?Evento
    {
        return $this->libro->aperturaVigente()?->load('usuario:id,name');
    }

    /**
     * Turno vigente aplanado para la UI; `id` es el número del evento de apertura.
     *
     * @return array{id: int, fecha: string, hora_inicio: string, supervisor: ?string, transcurrido: string}|null
     */
    public function turnoVigente(?Evento $apertura): ?array
    {
        if (! $apertura) {
            return null;
        }

        return [
            'id' => $apertura->id,
            'fecha' => $apertura->fecha_hora->format('d/m/Y'),
            'hora_inicio' => $apertura->fecha_hora->format('H:i'),
            'supervisor' => $apertura->usuario?->name,
            'transcurrido' => $this->transcurrido($apertura),
        ];
    }

    /**
     * Eventos desde la apertura vigente (o los últimos registrados si no hay
     * turno abierto), del más reciente al más antiguo, ya aplanados para la UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function eventosDelTurno(?Evento $apertura): array
    {
        return ($apertura ? $this->libro->eventosDe($apertura) : Evento::vigentes()->limit(self::EVENTOS_SIN_TURNO))
            ->with([
                'tipoEvento:id,nombre,grupo_evento_id,es_cuantificable',
                'tipoEvento.grupoEvento:id,nombre',
                'puntoMonitoreo:id,codigo,nombre,latitud,longitud',
                'barrio:id,nombre',
                'usuario:id,name',
            ])
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Evento $e) => [
                'id' => $e->id,
                'codigo' => $e->codigo(),
                'categoria' => $e->tipoEvento->grupoEvento->nombre,
                'es_cuantificable' => $e->tipoEvento->es_cuantificable,
                'tipo' => $e->tipoEvento->nombre,
                'ubicacion' => $e->ubicacion(),
                'latitud' => $e->puntoMonitoreo?->latitud,
                'longitud' => $e->puntoMonitoreo?->longitud,
                'fecha' => $e->fecha_hora->format('d/m/Y'),
                'hora' => $e->fecha_hora->format('H:i:s'),
                'timestamp_video' => $e->timestamp_video,
                'descripcion' => $e->descripcion,
                'autor' => $e->usuario?->name,
            ])
            ->all();
    }

    /**
     * Desglose por grupo de evento. Los tipos no cuantificables (RN-04)
     * quedan fuera del conteo estadístico.
     *
     * @param  array<int, array<string, mixed>>  $eventos
     * @return array<string, int>
     */
    public function conteoPorCategoria(array $eventos): array
    {
        $conteo = array_fill_keys(array_values(GrupoEvento::opciones()), 0);

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
     * @return Collection<int, array<string, mixed>>
     */
    public function puntosMonitoreo(): Collection
    {
        return PuntoMonitoreo::paraMapa();
    }

    /**
     * Totales de la red de monitoreo, por tipo de punto.
     *
     * // TODO: el modelo no registra estado operativo de la cámara (en línea /
     * // mantenimiento). Confirmar con el equipo si se releva.
     *
     * @return array{total: int, por_tipo: array<string, int>}
     */
    public function resumenPuntosMonitoreo(Collection $puntos): array
    {
        return [
            'total' => $puntos->count(),
            'por_tipo' => $puntos
                ->groupBy('tipo')
                ->map->count()
                ->all(),
        ];
    }

    /**
     * Tiempo transcurrido desde la apertura del turno, en formato "04h 12m".
     */
    private function transcurrido(Evento $apertura): string
    {
        $minutos = max(0, (int) $apertura->fecha_hora->diffInMinutes(now()));

        return sprintf('%02dh %02dm', intdiv($minutos, 60), $minutos % 60);
    }
}
