<?php

namespace App\Services\Tableros;

use App\Models\Evento;
use App\Models\GrupoEvento;
use App\Models\Notificacion;
use App\Services\LibroGuardiaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tablero de Control Operativo (tiempo real): lo que está pasando en el turno.
 *
 * Mismos criterios que las estadísticas: solo novedades vigentes (con las
 * erratas aplicadas) de tipos cuantificables (RN4). El feed y la salud de las
 * notificaciones sí muestran todo lo registrado, también los eventos internos.
 * Las comparaciones históricas se agregan en PHP sobre una sola lectura, sin
 * funciones de fecha propias de un motor (igual que AnalisisService).
 */
class TableroOperativoService
{
    /** Días anteriores con los que se compara la última hora. */
    public const DIAS_COMPARACION = 7;

    /** Semanas anteriores con las que se compara el turno (mismo día y franja). */
    public const SEMANAS_COMPARACION = 8;

    /** Última hora ≥ promedio × este factor => alerta de pico (50 % más). */
    public const FACTOR_PICO = 1.5;

    public const FEED = 10;

    /** Hora de inicio de cada franja (Evento::FRANJAS); cada una dura 8 h. */
    public const INICIO_FRANJA = ['manana' => 6, 'tarde' => 14, 'noche' => 22];

    private const HORAS_FRANJA = 8;

    public function __construct(private readonly LibroGuardiaService $libro) {}

    /**
     * @return array<string, mixed>
     */
    public function calcular(): array
    {
        $ahora = now();
        // Una sola lectura para la última hora, el turno y las últimas 24 h.
        $historico = $this->cuantificables()
            ->where('eventos.fecha_hora', '>=', $ahora->copy()->subWeeks(self::SEMANAS_COMPARACION)->subDay())
            ->where('eventos.fecha_hora', '<=', $ahora)
            ->get(['eventos.fecha_hora', 'grupos_evento.codigo as categoria'])
            ->map(fn ($e) => ['fecha' => Carbon::parse($e->fecha_hora), 'categoria' => $e->categoria]);

        return [
            'ultima_hora' => $this->ultimaHora($historico, $ahora),
            'turno' => $this->turno($historico, $ahora),
            'distribucion' => $this->distribucion($historico, $ahora),
            'notificaciones' => $this->notificaciones($ahora),
            'feed' => $this->feed(),
            'actualizado' => $ahora->format('H:i:s'),
        ];
    }

    /**
     * KPI 1: novedades de los últimos 60 minutos contra el promedio de la misma
     * ventana horaria en los días anteriores.
     *
     * @param  Collection<int, array{fecha: Carbon, categoria: string}>  $historico
     */
    public function ultimaHora(Collection $historico, Carbon $ahora): array
    {
        $contar = fn (Carbon $hasta) => $historico
            ->filter(fn ($e) => $e['fecha']->gt($hasta->copy()->subHour()) && $e['fecha']->lte($hasta))
            ->count();

        $actual = $contar($ahora);
        $anteriores = collect(range(1, self::DIAS_COMPARACION))->map(fn (int $d) => $contar($ahora->copy()->subDays($d)));
        $promedio = round($anteriores->avg(), 1);

        return [
            'cantidad' => $actual,
            'promedio' => $promedio,
            'variacion' => $promedio > 0 ? round(($actual - $promedio) * 100 / $promedio, 1) : null,
            // Sin historial (promedio 0) no hay con qué comparar: no se alerta.
            'pico' => $promedio > 0 && $actual >= $promedio * self::FACTOR_PICO,
            'desde' => $ahora->copy()->subHour()->format('H:i'),
            'hasta' => $ahora->format('H:i'),
            'dias' => self::DIAS_COMPARACION,
        ];
    }

    /**
     * KPI 2: novedades del turno en curso contra el promedio del mismo turno
     * (misma franja y mismo día de la semana) en las semanas anteriores, tanto
     * del turno completo como hasta la misma altura del turno.
     *
     * Con Apertura vigente se usa su franja y su hora; sin turno abierto, la
     * franja que corresponde a la hora actual.
     *
     * @param  Collection<int, array{fecha: Carbon, categoria: string}>  $historico
     */
    public function turno(Collection $historico, Carbon $ahora): array
    {
        $apertura = $this->libro->aperturaVigente();
        $franja = $apertura?->franja ?? self::franjaDe($apertura?->fecha_hora ?? $ahora);
        $inicio = self::inicioFranja($franja, $apertura?->fecha_hora ?? $ahora);
        // El turno real empieza en la Apertura (puede abrirse antes o después del horario).
        $desde = $apertura?->fecha_hora ?? $inicio;
        $transcurrido = max(0, (int) $inicio->diffInSeconds($ahora, false));

        $enRango = fn (Carbon $d, Carbon $h) => $historico
            ->filter(fn ($e) => $e['fecha']->gte($d) && $e['fecha']->lt($h))
            ->count();

        $anteriores = collect(range(1, self::SEMANAS_COMPARACION))->map(function (int $s) use ($inicio, $transcurrido, $enRango) {
            $i = $inicio->copy()->subWeeks($s);

            return [
                'total' => $enRango($i, $i->copy()->addHours(self::HORAS_FRANJA)),
                'a_esta_altura' => $enRango($i, $i->copy()->addSeconds($transcurrido)),
            ];
        });

        $actual = $historico->filter(fn ($e) => $e['fecha']->gte($desde) && $e['fecha']->lte($ahora))->count();
        $promedio = round($anteriores->avg('total'), 1);

        return [
            'abierto' => $apertura !== null,
            'franja' => Evento::FRANJAS[$franja],
            'dia' => ucfirst($inicio->translatedFormat('l')),
            'grupo_guardia' => $apertura?->grupo_guardia,
            'desde' => $desde->format('d/m H:i'),
            'cantidad' => $actual,
            'promedio' => $promedio,
            'promedio_a_esta_altura' => round($anteriores->avg('a_esta_altura'), 1),
            'porcentaje' => $promedio > 0 ? round($actual * 100 / $promedio, 1) : null,
            'semanas' => self::SEMANAS_COMPARACION,
        ];
    }

    /**
     * KPI 3: distribución por grupo de evento en las últimas 24 horas.
     *
     * @param  Collection<int, array{fecha: Carbon, categoria: string}>  $historico
     * @return list<array{clave: string, nombre: string, cantidad: int, porcentaje: float}>
     */
    public function distribucion(Collection $historico, Carbon $ahora): array
    {
        $ultimas = $historico->filter(fn ($e) => $e['fecha']->gt($ahora->copy()->subDay()));
        $total = $ultimas->count();
        $porGrupo = $ultimas->countBy('categoria');

        return collect(GrupoEvento::opciones())
            ->map(fn (string $nombre, string $clave) => [
                'clave' => $clave,
                'nombre' => $nombre,
                'cantidad' => (int) ($porGrupo[$clave] ?? 0),
                'porcentaje' => $total > 0 ? round(($porGrupo[$clave] ?? 0) * 100 / $total, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * KPI 4: salud de las notificaciones del día (US-006/US-007), con las
     * fallidas para reenviarlas a mano desde el detalle de la novedad.
     */
    public function notificaciones(Carbon $ahora): array
    {
        $delDia = Notificacion::query()->where('created_at', '>=', $ahora->copy()->startOfDay());
        $porEstado = (clone $delDia)->toBase()
            ->selectRaw('estado, count(*) as cantidad')
            ->groupBy('estado')
            ->pluck('cantidad', 'estado');

        $enviadas = (int) ($porEstado[Notificacion::ESTADO_ENVIADA] ?? 0);
        $fallidas = (int) ($porEstado[Notificacion::ESTADO_FALLIDA] ?? 0);
        $resueltas = $enviadas + $fallidas;

        return [
            'enviadas' => $enviadas,
            'fallidas' => $fallidas,
            'pendientes' => (int) ($porEstado[Notificacion::ESTADO_PENDIENTE] ?? 0),
            'efectividad' => $resueltas > 0 ? round($enviadas * 100 / $resueltas, 1) : null,
            'canal' => config('notificaciones.canal'),
            'ultimas_fallidas' => (clone $delDia)
                ->where('estado', Notificacion::ESTADO_FALLIDA)
                ->with(['grupoInteresado:id,nombre', 'evento:id,fecha_hora'])
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Notificacion $n) => [
                    'id' => $n->id,
                    'evento_id' => $n->evento_id,
                    'codigo' => $n->evento?->codigo(),
                    'grupo' => $n->grupoInteresado?->nombre,
                    'error' => $n->error,
                    'hora' => $n->created_at->format('H:i'),
                ])
                ->all(),
        ];
    }

    /**
     * Feed: las últimas novedades ingresadas (de cualquier tipo) con el estado
     * resumido de su notificación.
     *
     * @return list<array<string, mixed>>
     */
    public function feed(): array
    {
        return Evento::vigentes()
            ->with([
                'tipoEvento:id,nombre,grupo_evento_id',
                'tipoEvento.grupoEvento:id,codigo,nombre',
                'puntoMonitoreo:id,codigo,nombre',
                'notificaciones:id,evento_id,estado',
            ])
            ->orderByDesc('id')
            ->limit(self::FEED)
            ->get()
            ->map(fn (Evento $e) => [
                'id' => $e->id,
                'codigo' => $e->codigo(),
                'fecha' => $e->fecha_hora->format('d/m'),
                'hora' => $e->fecha_hora->format('H:i'),
                'tipo' => $e->tipoEvento->nombre,
                'categoria' => $e->tipoEvento->grupoEvento->codigo,
                'categoria_nombre' => $e->tipoEvento->grupoEvento->nombre,
                'punto' => $e->puntoMonitoreo?->codigo,
                'punto_nombre' => $e->puntoMonitoreo?->nombre,
                'notificacion' => self::estadoResumido($e->notificaciones->pluck('estado')),
            ])
            ->all();
    }

    /**
     * Una sola marca por novedad: si algún grupo falló, fallida; si alguno
     * sigue en cola, pendiente; si todos llegaron, enviada; null si no se notificó.
     *
     * @param  Collection<int, string>  $estados
     */
    public static function estadoResumido(Collection $estados): ?string
    {
        return match (true) {
            $estados->isEmpty() => null,
            $estados->contains(Notificacion::ESTADO_FALLIDA) => Notificacion::ESTADO_FALLIDA,
            $estados->contains(Notificacion::ESTADO_PENDIENTE) => Notificacion::ESTADO_PENDIENTE,
            default => Notificacion::ESTADO_ENVIADA,
        };
    }

    /** Franja que corresponde a una hora: mañana 6–14, tarde 14–22, noche 22–6. */
    public static function franjaDe(Carbon $momento): string
    {
        return match (true) {
            $momento->hour >= 6 && $momento->hour < 14 => 'manana',
            $momento->hour >= 14 && $momento->hour < 22 => 'tarde',
            default => 'noche',
        };
    }

    /**
     * Comienzo horario de la franja que contiene (o empieza cerca de) un
     * momento: la noche que se abre a las 02:00 empezó a las 22:00 del día anterior.
     */
    public static function inicioFranja(string $franja, Carbon $momento): Carbon
    {
        $inicio = $momento->copy()->setTime(self::INICIO_FRANJA[$franja] ?? 6, 0);

        // Noche abierta de madrugada: arrancó el día anterior.
        if ($franja === 'noche' && $momento->hour < 12) {
            $inicio->subDay();
        }

        return $inicio;
    }

    /**
     * @return Builder<Evento>
     */
    private function cuantificables(): Builder
    {
        return Evento::vigentes()
            ->join('tipos_evento', 'tipos_evento.id', '=', 'eventos.tipo_evento_id')
            ->join('grupos_evento', 'grupos_evento.id', '=', 'tipos_evento.grupo_evento_id')
            ->where('tipos_evento.es_cuantificable', true);
    }
}
