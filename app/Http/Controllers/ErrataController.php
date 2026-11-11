<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreErrataRequest;
use App\Models\AuditoriaAcceso;
use App\Models\Evento;
use App\Services\Auth\AuditoriaAccesoService;
use App\Services\ErrataService;
use App\Services\EventoService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Web (Inertia). Alta de "fe de errata" sobre un Evento (Supervisor,
 * erratas.crear). Controller fino: la lógica vive en ErrataService.
 *
 * Decisión del equipo (2026-10-15): el Supervisor corrige eventos de
 * cualquier turno, incluso de otros supervisores (auditado).
 */
class ErrataController extends Controller
{
    public function __construct(
        private readonly ErrataService $erratas,
        private readonly EventoService $eventos,
        private readonly AuditoriaAccesoService $auditoria,
    ) {}

    public function create(Request $request, Evento $evento): Response
    {
        abort_unless($this->eventos->puedeCorregir($request->user()), 403);

        return Inertia::render('Errata/Create', $this->eventos->formularioSobre($evento));
    }

    public function store(StoreErrataRequest $request, Evento $evento): RedirectResponse
    {
        abort_unless($this->eventos->puedeCorregir($request->user()), 403);

        try {
            $this->erratas->corregir($evento, $request->validated('motivo'), $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_ERRATA,
            $request->user(),
            // Autor del evento: deja a la vista correcciones sobre eventos
            // de otros supervisores.
            "Fe de errata sobre la novedad #{$evento->id}; cargada por {$evento->usuario->name}.",
        );

        return redirect()->route('eventos.show', $evento)
            ->with('success', 'Fe de errata registrada.');
    }
}
