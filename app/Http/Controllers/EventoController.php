<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuscarEventosRequest;
use App\Http\Requests\StoreEventoRequest;
use App\Models\AuditoriaAcceso;
use App\Models\Barrio;
use App\Models\Cuadrante;
use App\Models\Errata;
use App\Models\Evento;
use App\Models\GrupoEvento;
use App\Models\Notificacion;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\TipoPuntoMonitoreo;
use App\Services\Auth\AuditoriaAccesoService;
use App\Services\ErrataService;
use App\Services\EventoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Web (Inertia). Controller FINO: sólo orquesta, la lógica vive en EventoService.
 * REGLA DE NEGOCIO: no hay update() -> los eventos son inmutables (usar Errata).
 */
class EventoController extends Controller
{
    public function __construct(
        private readonly EventoService $eventos,
        private readonly AuditoriaAccesoService $auditoria,
        private readonly ErrataService $erratasLegibles,
    ) {}

    /**
     * CU04: consulta de eventos registrados con filtros (eventos.consultar).
     */
    public function index(BuscarEventosRequest $request): Response
    {
        $filtros = $request->validated();

        $eventos = $this->eventos->buscar($filtros, $request->user())->through(fn (Evento $e) => [
            'id' => $e->id,
            'fecha_hora' => $e->fecha_hora->format('d/m/Y H:i:s'),
            'tipo' => $e->tipoEvento->nombre,
            'categoria' => $e->tipoEvento->grupoEvento->nombre,
            'informativo' => ! $e->tipoEvento->es_cuantificable,
            'punto' => $e->puntoMonitoreo
                ? "{$e->puntoMonitoreo->codigo} — {$e->puntoMonitoreo->nombre}"
                : null,
            'timestamp_video' => $e->timestamp_video,
            'descripcion' => $e->descripcion,
            'autor' => $e->usuario?->name,
            'notificaciones' => $this->notificaciones($e),
            'corregido' => $e->erratas_count > 0,
        ]);

        return Inertia::render('Eventos/Index', [
            'eventos' => $eventos,
            'filtros' => (object) $filtros,
            'tipos' => $this->eventos->tiposEvento(),
            'categorias' => GrupoEvento::opciones(),
            'puntos' => $this->eventos->puntosMonitoreo(),
            'tiposPunto' => TipoPuntoMonitoreo::opciones(),
            'barrios' => Barrio::orderBy('nombre')->get(['id', 'nombre']),
            'cuadrantes' => Cuadrante::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /**
     * US-003: formulario de registro de evento (Supervisor).
     */
    public function create(Request $request): Response
    {
        $puntos = $this->eventos->puntosMonitoreo();

        // Registro desde un PM del mapa: ?punto_monitoreo_id=X precarga la ubicación.
        // Un id inexistente se ignora (el form arranca sin PM).
        $puntoInicial = $puntos->firstWhere('id', $request->integer('punto_monitoreo_id'))['id'] ?? null;

        return Inertia::render('Eventos/Create', [
            'tipos' => $this->eventos->tiposEvento(),
            'categorias' => GrupoEvento::opciones(),
            'puntos' => $puntos,
            'puntoInicial' => $puntoInicial,
            'tiposPunto' => TipoPuntoMonitoreo::opciones(),
        ]);
    }

    public function store(StoreEventoRequest $request): RedirectResponse
    {
        $evento = $this->eventos->registrar($request->validated(), $request->user());

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_REGISTRO_EVENTO,
            $request->user(),
            "Evento #{$evento->id} registrado ({$evento->tipoEvento->nombre}).",
        );

        return redirect()->route('eventos.create')
            ->with('success', "Evento #{$evento->id} registrado correctamente.")
            ->with('eventoRegistrado', $evento->id);
    }

    /**
     * US-010: detalle completo de un evento (eventos.consultar).
     */
    public function show(Request $request, Evento $evento): Response
    {
        abort_unless($this->eventos->puedeVerDetalle($request->user(), $evento), 403);

        $evento = $this->eventos->detalle($evento);

        // Se muestran los valores VIGENTES (original + fe de erratas); el
        // registro original queda en el historial de correcciones.
        $vigentes = $evento->valoresVigentes();
        $tipo = TipoEvento::with('grupoEvento:id,nombre')->find($vigentes['tipo_evento_id']);
        $punto = $vigentes['punto_monitoreo_id']
            ? PuntoMonitoreo::with(['tipoPuntoMonitoreo:id,nombre', 'barrio:id,nombre', 'cuadrante:id,nombre'])->find($vigentes['punto_monitoreo_id'])
            : null;
        $fechaHora = Carbon::parse($vigentes['fecha_hora']);

        return Inertia::render('Eventos/Show', [
            'puedeCorregir' => $request->user()->can('erratas.crear'),
            'evento' => [
                'id' => $evento->id,
                'tipo' => $tipo->nombre,
                'categoria' => $tipo->grupoEvento->nombre,
                'informativo' => ! $tipo->es_cuantificable,
                'cuantificable' => $tipo->es_cuantificable,
                'fecha' => $fechaHora->format('d/m/Y'),
                'hora' => $fechaHora->format('H:i:s'),
                'descripcion' => $vigentes['descripcion'],
                'punto' => $punto ? [
                    'codigo' => $punto->codigo,
                    'nombre' => $punto->nombre,
                    'tipo' => $punto->tipoPuntoMonitoreo->nombre,
                    'barrio' => $punto->barrio?->nombre,
                    'cuadrante' => $punto->cuadrante?->nombre,
                    'latitud' => $punto->latitud,
                    'longitud' => $punto->longitud,
                ] : null,
                'timestamp_video' => $vigentes['timestamp_video'],
                'erratas' => $evento->erratas->map(fn (Errata $e) => [
                    'id' => $e->id,
                    'campo' => Errata::CAMPOS[$e->campo_corregido] ?? $e->campo_corregido,
                    'anterior' => $this->erratasLegibles->legible($e->campo_corregido, $e->valor_anterior),
                    'nuevo' => $this->erratasLegibles->legible($e->campo_corregido, $e->valor_nuevo),
                    'motivo' => $e->motivo,
                    'autor' => $e->autor?->name,
                    'fecha' => $e->created_at?->format('d/m/Y H:i:s'),
                ]),
                'autor' => $evento->usuario?->name,
                'registrado' => $evento->created_at?->format('d/m/Y H:i:s'),
                'notificaciones' => $this->notificaciones($evento),
            ],
        ]);
    }

    /**
     * US-006/US-007: resultado del envío a cada grupo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function notificaciones(Evento $evento): Collection
    {
        return $evento->notificaciones->map(fn (Notificacion $n) => [
            'id' => $n->id,
            'grupo' => $n->grupoInteresado?->nombre,
            'estado' => $n->estado,
            'intentos' => $n->intentos,
            'error' => $n->error,
        ]);
    }
}
