<?php

namespace App\Services;

use App\Events\EventoRegistrado;
use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lógica de negocio del registro y consulta de eventos.
 *
 * SINGLE RESPONSIBILITY: sólo eventos. Correcciones -> ErrataService.
 * OBSERVER: NO notifica de forma directa. Al persistir un Evento dispara el
 * evento de dominio EventoRegistrado y un Listener en cola se encarga de la
 * notificación (si WhatsApp falla, el evento igual queda guardado).
 *
 * REGLA DE NEGOCIO: los eventos son INMUTABLES. Este service NO expone update().
 */
class EventoService
{
    /**
     * Columnas por las que se puede ordenar la consulta (US-009).
     */
    public const ORDENES = ['id', 'fecha_hora', 'tipo', 'punto', 'autor'];

    /**
     * Valores del filtro de US-011 (eventos informativos / sin ubicación).
     */
    public const ALCANCES = ['con_ubicacion', 'sin_ubicacion', 'informativos', 'sin_informativos'];

    /**
     * US-003: registra un evento a nombre de su autor. No hace falta un turno
     * abierto: abrir y cerrar el turno también son eventos (Apertura de turno /
     * Cierre de turno, ver LibroGuardiaService).
     *
     * @param  array{tipo_evento_id: int, fecha: string, hora: string, punto_monitoreo_id?: int|null, timestamp_video?: string, descripcion?: string|null}  $datos
     */
    public function registrar(array $datos, User $autor): Evento
    {
        $puntoMonitoreoId = $datos['punto_monitoreo_id'] ?? null;

        $evento = Evento::create([
            'tipo_evento_id' => $datos['tipo_evento_id'],
            'punto_monitoreo_id' => $puntoMonitoreoId,
            'usuario_id' => $autor->id,
            'fecha_hora' => Carbon::createFromFormat('Y-m-d H:i:s', "{$datos['fecha']} {$datos['hora']}"),
            'timestamp_video' => $puntoMonitoreoId ? $datos['timestamp_video'] : null,
            'descripcion' => $datos['descripcion'] ?? null,
        ]);

        // El listener en cola notifica (Observer).
        EventoRegistrado::dispatch($evento);

        return $evento;
    }

    /**
     * Tipos para selects; `categoria` es el código de su GrupoEvento.
     *
     * @return Collection<int, array{id: int, nombre: string, categoria: string, requiere_ubicacion: bool}>
     */
    public function tiposEvento(): Collection
    {
        return TipoEvento::with('grupoEvento:id,codigo')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'grupo_evento_id', 'requiere_ubicacion'])
            ->map(fn (TipoEvento $t) => [
                'id' => $t->id,
                'nombre' => $t->nombre,
                'categoria' => $t->grupoEvento->codigo,
                'requiere_ubicacion' => $t->requiere_ubicacion,
            ])
            ->toBase();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function puntosMonitoreo(): Collection
    {
        return PuntoMonitoreo::paraMapa();
    }

    /**
     * US-010: evento con todo lo necesario para mostrar su detalle.
     */
    public function detalle(Evento $evento): Evento
    {
        return $evento->load([
            'tipoEvento:id,nombre,grupo_evento_id,requiere_ubicacion,es_cuantificable',
            'tipoEvento.grupoEvento:id,codigo,nombre',
            'puntoMonitoreo:id,codigo,nombre,tipo_punto_monitoreo_id,barrio_id,cuadrante_id,latitud,longitud',
            'puntoMonitoreo.tipoPuntoMonitoreo:id,codigo,nombre',
            'puntoMonitoreo.barrio:id,nombre',
            'puntoMonitoreo.cuadrante:id,nombre',
            'usuario:id,name',
            'notificaciones.grupoInteresado:id,nombre',
            'erratas.autor:id,name',
        ]);
    }

    /**
     * CU04: eventos registrados, del más reciente al más antiguo.
     * `hasta` incluye el día completo.
     *
     * US-008: todos los filtros se combinan (AND).
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Evento>
     */
    public function buscar(array $filtros, User $consultante, int $porPagina = 20): LengthAwarePaginator
    {
        return $this->consulta($filtros, $consultante)
            ->select('eventos.*')
            ->withCount('erratas')
            ->with([
                'notificaciones:id,evento_id,grupo_interesado_id,estado,intentos,error',
                'notificaciones.grupoInteresado:id,nombre',
            ])
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * US-013: todos los eventos que coinciden con los filtros, sin límite ni
     * paginación, en el mismo orden que la consulta.
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, Evento>
     */
    public function paraExportar(array $filtros, User $consultante): Collection
    {
        return $this->consulta($filtros, $consultante)->get();
    }

    /**
     * Consulta filtrada y ordenada, respetando el alcance del usuario.
     *
     * @param  array<string, mixed>  $filtros
     * @return Builder<Evento>
     */
    private function consulta(array $filtros, User $consultante): Builder
    {
        return $this->visiblesPara($consultante)
            ->with([
                'tipoEvento:id,nombre,grupo_evento_id,es_cuantificable',
                'tipoEvento.grupoEvento:id,codigo,nombre',
                'puntoMonitoreo:id,codigo,nombre,tipo_punto_monitoreo_id,barrio_id',
                'puntoMonitoreo.tipoPuntoMonitoreo:id,codigo,nombre',
                'puntoMonitoreo.barrio:id,nombre',
                'usuario:id,name',
            ])
            ->when($filtros['tipo_evento_id'] ?? null, fn (Builder $q, $id) => $q->where('tipo_evento_id', (int) $id))
            ->when($filtros['categorias'] ?? null, fn (Builder $q, array $categorias) => $q
                ->whereHas('tipoEvento.grupoEvento', fn (Builder $g) => $g->whereIn('codigo', $categorias)))
            ->when($filtros['punto_monitoreo_id'] ?? null, fn (Builder $q, $id) => $q->where('punto_monitoreo_id', (int) $id))
            ->when($filtros['tipo_punto'] ?? null, fn (Builder $q, $tipo) => $q
                ->whereHas('puntoMonitoreo.tipoPuntoMonitoreo', fn (Builder $t) => $t->where('codigo', $tipo)))
            ->when($filtros['barrio_id'] ?? null, fn (Builder $q, $id) => $q
                ->whereHas('puntoMonitoreo', fn (Builder $p) => $p->where('barrio_id', $id)))
            ->when($filtros['cuadrante_id'] ?? null, fn (Builder $q, $id) => $q
                ->whereHas('puntoMonitoreo', fn (Builder $p) => $p->where('cuadrante_id', $id)))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $desde) => $q
                ->where('fecha_hora', '>=', Carbon::parse($desde)->startOfDay()))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $hasta) => $q
                ->where('fecha_hora', '<', Carbon::parse($hasta)->addDay()->startOfDay()))
            ->tap(fn (Builder $q) => $this->filtrarFranjaHoraria($q, $filtros['hora_desde'] ?? null, $filtros['hora_hasta'] ?? null))
            ->tap(fn (Builder $q) => $this->filtrarAlcance($q, $filtros['alcance'] ?? null))
            // Sin distinguir mayúsculas; % y _ del usuario se buscan literal.
            ->when($filtros['texto'] ?? null, fn (Builder $q, $texto) => $q
                ->whereRaw("lower(descripcion) like ? escape '\\'", [
                    '%'.addcslashes(mb_strtolower($texto), '%_\\').'%',
                ]))
            ->tap(fn (Builder $q) => $this->ordenar($q, $filtros['orden'] ?? 'fecha_hora', $filtros['direccion'] ?? 'desc'));
    }

    /**
     * US-008: franja horaria del hecho, independiente del día. Si `desde` es
     * mayor que `hasta` la franja cruza la medianoche (p. ej. 22:00 a 06:00).
     */
    private function filtrarFranjaHoraria(Builder $query, ?string $desde, ?string $hasta): void
    {
        // HH:MM -> se incluye el minuto completo de `hasta`.
        $desde = $desde ? substr($desde, 0, 5).':00' : null;
        $hasta = $hasta ? substr($hasta, 0, 5).':59' : null;

        if ($desde && $hasta && $desde > $hasta) {
            $query->where(fn (Builder $q) => $q
                ->whereTime('fecha_hora', '>=', $desde)
                ->orWhereTime('fecha_hora', '<=', $hasta));

            return;
        }

        $query
            ->when($desde, fn (Builder $q) => $q->whereTime('fecha_hora', '>=', $desde))
            ->when($hasta, fn (Builder $q) => $q->whereTime('fecha_hora', '<=', $hasta));
    }

    /**
     * US-011: eventos informativos (tipo no cuantificable) y sin ubicación,
     * por separado o excluidos.
     */
    private function filtrarAlcance(Builder $query, ?string $alcance): void
    {
        $informativo = fn (Builder $t) => $t->where('es_cuantificable', false);

        match ($alcance) {
            'con_ubicacion' => $query->whereNotNull('punto_monitoreo_id'),
            'sin_ubicacion' => $query->whereNull('punto_monitoreo_id'),
            'informativos' => $query->whereHas('tipoEvento', $informativo),
            'sin_informativos' => $query->whereDoesntHave('tipoEvento', $informativo),
            default => null,
        };
    }

    /**
     * US-009: orden por columna. Las columnas de relaciones se ordenan con una
     * subconsulta para no duplicar filas con joins.
     */
    private function ordenar(Builder $query, string $orden, string $direccion): void
    {
        $direccion = $direccion === 'asc' ? 'asc' : 'desc';

        match ($orden) {
            'id' => $query->orderBy('id', $direccion),
            'tipo' => $query->orderBy(
                TipoEvento::select('nombre')->whereColumn('tipos_evento.id', 'eventos.tipo_evento_id'),
                $direccion,
            ),
            'punto' => $query->orderBy(
                PuntoMonitoreo::select('codigo')->whereColumn('puntos_monitoreo.id', 'eventos.punto_monitoreo_id'),
                $direccion,
            ),
            'autor' => $query->orderBy(
                User::select('name')->whereColumn('users.id', 'eventos.usuario_id'),
                $direccion,
            ),
            default => $query->orderBy('fecha_hora', $direccion),
        };

        // Desempate estable para la paginación.
        $query->orderBy('id', $direccion);
    }

    /**
     * Alcance de la consulta: todos los roles con `eventos.consultar` ven el
     * libro completo (decisión 2026-10-23: sin turnos, el Supervisor ya no se
     * limita a su turno). Lista, filtra y exporta los valores vigentes (con fe
     * de errata).
     *
     * @return Builder<Evento>
     */
    public function visiblesPara(User $consultante): Builder
    {
        return Evento::vigentes();
    }

    public function puedeVer(User $consultante, Evento $evento): bool
    {
        return $this->visiblesPara($consultante)->whereKey($evento->id)->exists();
    }

    /**
     * Fe de errata (decisión del equipo 2026-10-15): el Supervisor corrige
     * cualquier evento, incluso de otros supervisores (auditado).
     */
    public function puedeCorregir(User $usuario): bool
    {
        return $usuario->can('erratas.crear');
    }

    /**
     * Detalle de un evento: lo que puede consultar o, si no, lo que puede
     * corregir (para ver qué está corrigiendo y su historial).
     */
    public function puedeVerDetalle(User $usuario, Evento $evento): bool
    {
        return $this->puedeCorregir($usuario) || $this->puedeVer($usuario, $evento);
    }
}
