<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAmpliacionRequest;
use App\Models\AuditoriaAcceso;
use App\Models\Evento;
use App\Services\AmpliacionService;
use App\Services\Auth\AuditoriaAccesoService;
use App\Services\EventoService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Web (Inertia). Ampliación de una novedad (Supervisor, ampliaciones.crear).
 * Controller fino: la lógica vive en AmpliacionService. Como la fe de errata,
 * el Supervisor amplía novedades de cualquier turno (auditado).
 */
class AmpliacionController extends Controller
{
    public function __construct(
        private readonly AmpliacionService $ampliaciones,
        private readonly EventoService $eventos,
        private readonly AuditoriaAccesoService $auditoria,
    ) {}

    public function create(Request $request, Evento $evento): Response
    {
        abort_unless($this->eventos->puedeAmpliar($request->user()), 403);
        abort_if($this->eventos->esConstancia($evento), 403);

        return Inertia::render('Ampliacion/Create', $this->eventos->formularioSobre($evento));
    }

    public function store(StoreAmpliacionRequest $request, Evento $evento): RedirectResponse
    {
        abort_unless($this->eventos->puedeAmpliar($request->user()), 403);

        try {
            $this->ampliaciones->ampliar($evento, $request->validated('detalle'), $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_AMPLIACION,
            $request->user(),
            "Ampliación de la novedad #{$evento->id}; cargada por {$evento->usuario->name}.",
        );

        return redirect()->route('eventos.show', $evento)
            ->with('success', 'Ampliación registrada.');
    }
}
