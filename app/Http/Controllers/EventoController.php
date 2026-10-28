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
use App\Services\LibroGuardiaService;
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
            // PM o, sin PM, dirección y barrio cargados a mano.
            'punto' => $e->ubicacion(),
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
    public function create(Request $request, LibroGuardiaService $libro): Response
    {
        // Sin turno abierto solo se puede registrar la Apertura de turno.
        $apertura = $libro->aperturaVigente();
        $puntos = $this->eventos->puntosMonitoreo();

        // Registro desde un PM del mapa: ?punto_monitoreo_id=X precarga la ubicación.
        // Un id inexistente se ignora (el form arranca sin PM).
        $puntoInicial = $puntos->firstWhere('id', $request->integer('punto_monitoreo_id'))['id'] ?? null;

        return Inertia::render('Eventos/Create', [
            'tipos' => $this->eventos->tiposEvento(cargables: true),
            'categorias' => GrupoEvento::opciones(),
            'puntos' => $puntos,
            'puntoInicial' => $puntoInicial,
            'tiposPunto' => TipoPuntoMonitoreo::opciones(),
            'barrios' => Barrio::orderBy('nombre')->get(['id', 'nombre']),
            'turno' => $apertura ? [
                'id' => $apertura->id,
                'desde' => $apertura->fecha_hora->format('d/m/Y H:i'),
                'guardia' => $apertura->guardia(),
            ] : null,
            'franjas' => Evento::FRANJAS,
            'gruposGuardia' => Evento::GRUPOS_GUARDIA,
        ]);
    }

    public function store(StoreEventoRequest $request): RedirectResponse
    {
        $evento = $this->eventos->registrar($request->validated(), $request->user());

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_REGISTRO_EVENTO,
            $request->user(),
            "Novedad #{$evento->id} registrada ({$evento->tipoEvento->nombre}).",
        );

        return redirect()->route('eventos.create')
            ->with('success', "Novedad #{$evento->id} registrada correctamente.")
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
            'puedeCorregir' => $request->user()->can('erratas.crear') && ! $this->eventos->esConstancia($evento),
            'evento' => [
                'id' => $evento->id,
                'tipo' => $tipo->nombre,
                'categoria' => $tipo->grupoEvento->nombre,
                'informativo' => ! $tipo->es_cuantificable,
                'cuantificable' => $tipo->es_cuantificable,
                'fecha' => $fechaHora->format('d/m/Y'),
                'hora' => $fechaHora->format('H:i:s'),
                'descripcion' => $vigentes['descripcion'],
                'guardia' => $evento->guardia(),
                'punto' => $punto ? [
                    'codigo' => $punto->codigo,
                    'nombre' => $punto->nombre,
                    'tipo' => $punto->tipoPuntoMonitoreo->nombre,
                    'barrio' => $punto->barrio?->nombre,
                    'cuadrante' => $punto->cuadrante?->nombre,
                    'latitud' => $punto->latitud,
                    'longitud' => $punto->longitud,
                ] : null,
                // Sin PM: lo cargado a mano.
                'barrio' => $evento->barrio?->nombre,
                'direccion' => $evento->direccion,
                'erratas' => $evento->erratas->map(fn (Errata $e) => [
                    'id' => $e->id,
                    // Solo las erratas anteriores a 2026-10-23 corrigen un campo.
                    'campo' => $e->campo_corregido ? (Errata::CAMPOS[$e->campo_corregido] ?? $e->campo_corregido) : null,
                    'anterior' => $e->campo_corregido ? $this->erratasLegibles->legible($e->campo_corregido, $e->valor_anterior) : null,
                    'nuevo' => $e->campo_corregido ? $this->erratasLegibles->legible($e->campo_corregido, $e->valor_nuevo) : null,
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
