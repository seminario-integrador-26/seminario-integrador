<?php

namespace App\Http\Controllers;

use App\Exceptions\TurnoYaAbiertoException;
use App\Http\Requests\AbrirTurnoRequest;
use App\Http\Requests\CerrarTurnoRequest;
use App\Models\AuditoriaAcceso;
use App\Models\Turno;
use App\Services\Auth\AuditoriaAccesoService;
use App\Services\TurnoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US-025: apertura, cierre y consulta de turnos de guardia (Supervisor).
 * Controller FINO: la lógica vive en TurnoService.
 */
class TurnoController extends Controller
{
    public function __construct(
        private readonly TurnoService $turnos,
        private readonly AuditoriaAccesoService $auditoria,
    ) {}

    /**
     * Historial de turnos + turno activo del supervisor.
     */
    public function index(): Response
    {
        $activo = $this->turnos->activoDe(Auth::user());

        $turnos = $this->turnos->historial()->through(fn (Turno $t) => [
            'id' => $t->id,
            'fecha' => $t->fecha->format('d/m/Y'),
            'hora_inicio' => substr((string) $t->hora_inicio, 0, 5),
            'fecha_fin' => $t->fecha_fin?->format('d/m/Y'),
            'hora_fin' => $t->hora_fin ? substr((string) $t->hora_fin, 0, 5) : null,
            'supervisor' => $t->supervisor?->name,
            'personal_presente' => $t->personal_presente ?? [],
            'novedades_pendientes' => $t->novedades_pendientes,
            'eventos_count' => $t->eventos_count,
            'abierto' => $t->estaAbierto(),
        ]);

        return Inertia::render('Turnos/Index', [
            'turnos' => $turnos,
            'turnoActivo' => $activo ? [
                'id' => $activo->id,
                'fecha' => $activo->fecha->format('d/m/Y'),
                'hora_inicio' => substr((string) $activo->hora_inicio, 0, 5),
                'personal_presente' => $activo->personal_presente ?? [],
            ] : null,
        ]);
    }

    /**
     * Abre un turno para el supervisor autenticado.
     */
    public function store(AbrirTurnoRequest $request): RedirectResponse
    {
        try {
            $turno = $this->turnos->abrir($request->user(), $request->validated());
        } catch (TurnoYaAbiertoException $e) {
            throw ValidationException::withMessages(['turno' => $e->getMessage()]);
        }

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_TURNO_APERTURA,
            $request->user(),
            "Turno #{$turno->id} abierto con ".count($turno->personal_presente).' persona(s) en guardia.',
        );

        return redirect()->route('turnos.index')
            ->with('success', "Turno #{$turno->id} abierto correctamente.");
    }

    /**
     * Cierra un turno del supervisor autenticado, registrando las novedades.
     */
    public function cerrar(CerrarTurnoRequest $request, Turno $turno): RedirectResponse
    {
        // Sólo el propio supervisor cierra su turno, y sólo si sigue abierto.
        if ($turno->supervisor_id !== $request->user()->id) {
            abort(403);
        }

        if (! $turno->estaAbierto()) {
            throw ValidationException::withMessages(['turno' => 'El turno ya está cerrado.']);
        }

        $this->turnos->cerrar($turno, $request->validated());

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_TURNO_CIERRE,
            $request->user(),
            "Turno #{$turno->id} cerrado.",
        );

        return redirect()->route('turnos.index')
            ->with('success', "Turno #{$turno->id} cerrado correctamente.");
    }
}
