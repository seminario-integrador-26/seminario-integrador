<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaAcceso;
use App\Models\GrupoEvento;
use App\Models\GrupoInteresado;
use App\Models\TipoEvento;
use App\Services\Auth\AuditoriaAccesoService;
use App\Services\GrupoInteresadoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US-028: grupos destinatarios (Telegram / WhatsApp) y correspondencias tipo de evento → grupo
 * (permiso notificaciones.configurar, Administrador de sistema).
 * Cada cambio queda en la auditoría.
 */
class GrupoInteresadoController extends Controller
{
    public function __construct(
        private readonly GrupoInteresadoService $grupos,
        private readonly AuditoriaAccesoService $auditoria,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Notificaciones/Index', [
            'grupos' => GrupoInteresado::query()
                ->withCount(['contactos', 'tiposEvento', 'notificaciones'])
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'identificador_whatsapp', 'telegram_chat_id']),
            'tipos' => $this->tipos(),
            'correspondencias' => DB::table('grupo_interesado_tipo_evento')
                ->get(['tipo_evento_id', 'grupo_interesado_id']),
            'categorias' => GrupoEvento::opciones(),
        ]);
    }

    public function create(): Response
    {
        return $this->formulario(null);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate(GrupoInteresadoService::reglas(), GrupoInteresadoService::mensajes());
        $grupo = new GrupoInteresado;
        $this->grupos->guardar($grupo, $datos);

        $this->auditar($request, AuditoriaAcceso::EVENTO_GRUPO_ALTA, "Grupo {$grupo->nombre} creado.");

        return redirect()->route('grupos.index')->with('success', "Grupo {$grupo->nombre} creado.");
    }

    public function edit(GrupoInteresado $grupo): Response
    {
        return $this->formulario($grupo);
    }

    public function update(Request $request, GrupoInteresado $grupo): RedirectResponse
    {
        $datos = $request->validate(GrupoInteresadoService::reglas($grupo->id), GrupoInteresadoService::mensajes());
        $cambios = $this->grupos->guardar($grupo, $datos);

        $this->auditar($request, AuditoriaAcceso::EVENTO_GRUPO_MOD, "Grupo {$grupo->nombre} actualizado".($cambios ? ": {$cambios}." : '.'));

        return redirect()->route('grupos.index')->with('success', "Grupo {$grupo->nombre} actualizado.");
    }

    /**
     * Un grupo con notificaciones enviadas no se borra: son el registro del
     * resultado de cada evento.
     */
    public function destroy(Request $request, GrupoInteresado $grupo): RedirectResponse
    {
        if ($grupo->notificaciones()->exists()) {
            return back()->with('error', "El grupo {$grupo->nombre} ya recibió notificaciones: no se puede eliminar. Quitale los tipos de novedad para que deje de recibirlas.");
        }

        $grupo->delete();
        $this->grupos->sincronizarNotifica();
        $this->auditar($request, AuditoriaAcceso::EVENTO_GRUPO_BAJA, "Grupo {$grupo->nombre} eliminado.");

        return back()->with('success', "Grupo {$grupo->nombre} eliminado.");
    }

    /**
     * Guarda la matriz completa de correspondencias.
     */
    public function correspondencias(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'correspondencias' => ['present', 'array'],
            'correspondencias.*.tipo_evento_id' => ['required', 'integer', 'exists:tipos_evento,id'],
            'correspondencias.*.grupo_interesado_id' => ['required', 'integer', 'exists:grupos_interesados,id'],
        ]);

        $detalle = $this->grupos->actualizarCorrespondencias($datos['correspondencias']);

        if ($detalle === '') {
            return back()->with('success', 'No había cambios para guardar.');
        }

        $this->auditar($request, AuditoriaAcceso::EVENTO_CORRESPONDENCIAS, $detalle);

        return back()->with('success', 'Correspondencias guardadas: rigen desde la próxima novedad.');
    }

    private function formulario(?GrupoInteresado $grupo): Response
    {
        $grupo?->load(['contactos:id,grupo_interesado_id,nombre,numero_whatsapp', 'tiposEvento:id']);

        return Inertia::render('Notificaciones/Form', [
            'grupo' => $grupo ? [
                'id' => $grupo->id,
                'nombre' => $grupo->nombre,
                'identificador_whatsapp' => $grupo->identificador_whatsapp,
                'telegram_chat_id' => $grupo->telegram_chat_id,
                'contactos' => $grupo->contactos->map->only(['nombre', 'numero_whatsapp'])->values(),
                'tipos_evento' => $grupo->tiposEvento->pluck('id'),
            ] : null,
            // Un grupo nuevo recibe por defecto la Apertura, el Cierre de turno y la Nota.
            'tiposPorDefecto' => TipoEvento::sinCamaraIds(),
            'tipos' => $this->tipos(),
            'categorias' => GrupoEvento::opciones(),
        ]);
    }

    /**
     * Tipos con `categoria` = código de su GrupoEvento (para agruparlos en la UI).
     */
    private function tipos()
    {
        return TipoEvento::with('grupoEvento:id,codigo')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'grupo_evento_id'])
            ->map(fn (TipoEvento $t) => ['id' => $t->id, 'nombre' => $t->nombre, 'categoria' => $t->grupoEvento->codigo]);
    }

    private function auditar(Request $request, string $accion, string $detalle): void
    {
        $this->auditoria->registrarAccion($accion, $request->user(), mb_strimwidth($detalle, 0, 500, '…'));
    }
}
