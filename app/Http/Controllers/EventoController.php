<?php

namespace App\Http\Controllers;

use App\Exceptions\SinTurnoActivoException;
use App\Http\Requests\BuscarEventosRequest;
use App\Http\Requests\StoreEventoRequest;
use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Services\EventoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Web (Inertia). Controller FINO: sólo orquesta, la lógica vive en EventoService.
 * REGLA DE NEGOCIO: no hay update() -> los eventos son inmutables (usar Errata).
 */
class EventoController extends Controller
{
    public function __construct(private readonly EventoService $eventos) {}

    /**
     * CU04: consulta de eventos registrados con filtros (eventos.consultar).
     */
    public function index(BuscarEventosRequest $request): Response
    {
        $filtros = $request->validated();

        $eventos = $this->eventos->buscar($filtros)->through(fn (Evento $e) => [
            'id' => $e->id,
            'fecha_hora' => $e->fecha_hora->format('d/m/Y H:i'),
            'tipo' => $e->tipoEvento->nombre,
            'categoria' => TipoEvento::CATEGORIAS[$e->tipoEvento->categoria] ?? $e->tipoEvento->categoria,
            'punto' => $e->puntoMonitoreo
                ? "{$e->puntoMonitoreo->codigo} · {$e->puntoMonitoreo->nombre}"
                : null,
            'timestamp_video' => $e->timestamp_video,
            'descripcion' => $e->descripcion,
            'autor' => $e->usuario?->name,
            'turno_id' => $e->turno_id,
        ]);

        return Inertia::render('Eventos/Index', [
            'eventos' => $eventos,
            'filtros' => (object) $filtros,
            'tipos' => $this->eventos->tiposEvento(),
            'categorias' => TipoEvento::CATEGORIAS,
            'puntos' => $this->eventos->puntosMonitoreo(),
        ]);
    }

    /**
     * US-003: formulario de registro de evento (Supervisor).
     */
    public function create(): Response
    {
        $turno = $this->eventos->turnoActivo();

        return Inertia::render('Eventos/Create', [
            'tipos' => $this->eventos->tiposEvento(),
            'categorias' => TipoEvento::CATEGORIAS,
            'puntos' => $this->eventos->puntosMonitoreo(),
            'jurisdicciones' => PuntoMonitoreo::JURISDICCIONES,
            'turnoActivo' => $turno ? [
                'id' => $turno->id,
                'fecha' => $turno->fecha->format('d/m/Y'),
                'hora_inicio' => substr($turno->hora_inicio, 0, 5),
                'supervisor' => $turno->supervisor?->name,
            ] : null,
        ]);
    }

    public function store(StoreEventoRequest $request): RedirectResponse
    {
        try {
            $evento = $this->eventos->registrar($request->validated(), $request->user());
        } catch (SinTurnoActivoException $e) {
            throw ValidationException::withMessages(['turno' => $e->getMessage()]);
        }

        return redirect()->route('eventos.create')
            ->with('success', "Evento #{$evento->id} registrado correctamente.");
    }

    // TODO: show(Evento $evento)
}
