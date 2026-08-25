<?php

namespace App\Http\Controllers;

use App\Services\EventoService;

/**
 * Web (Inertia). Controller FINO: sólo orquesta, la lógica vive en EventoService.
 * REGLA DE NEGOCIO: no hay update() -> los eventos son inmutables (usar Errata).
 */
class EventoController extends Controller
{
    public function __construct(
        private EventoService $eventos,
    ) {
    }

    // TODO: index()  -> Inertia::render('Eventos/Index', [...])
    // TODO: create() -> Inertia::render('Eventos/Create', [...])
    // TODO: store(StoreEventoRequest $request) -> $this->eventos->registrar(...)
    // TODO: show(Evento $evento)
}
