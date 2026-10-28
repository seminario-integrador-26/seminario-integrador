<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuscarEventosRequest;
use App\Models\AuditoriaAcceso;
use App\Models\Evento;
use App\Services\Auth\AuditoriaAccesoService;
use App\Services\LibroGuardiaService;
use App\Services\ReporteService;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * US-012 (Libro de guardia en PDF) y US-013 (planilla de eventos). Permiso
 * reportes.exportar (Administrativo y Administrador de sistema).
 * Controller fino: los datos los arma ReporteService y el archivo lo genera
 * ExportacionService (ExportadorFactory). Cada descarga queda auditada (US-026).
 */
class ReporteController extends Controller
{
    public function __construct(
        private readonly ReporteService $reportes,
        private readonly AuditoriaAccesoService $auditoria,
        private readonly LibroGuardiaService $libro,
    ) {}

    /**
     * Aperturas de turno para elegir qué guardia imprimir.
     */
    public function index(Request $request): Response
    {
        $fecha = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']])['fecha'] ?? null;

        return Inertia::render('Reportes/Index', [
            'turnos' => $this->libro->aperturas($fecha),
            'filtros' => ['fecha' => $fecha],
        ]);
    }

    public function guardiaPdf(Request $request, int $apertura): HttpResponse
    {
        $evento = Evento::vigentes()->findOrFail($apertura);

        try {
            $reporte = $this->reportes->pdfDeGuardia($evento, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_REPORTE_PDF,
            $request->user(),
            "Libro de guardia en PDF desde la apertura #{$evento->id}.",
        );

        return response($reporte['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$reporte['nombre'].'"',
        ]);
    }

    /**
     * US-013: planilla de eventos en .xlsx o .xls (la ruta restringe el formato).
     */
    public function eventosPlanilla(BuscarEventosRequest $request, string $formato): HttpResponse
    {
        $filtros = $request->validated();

        try {
            $planilla = $this->reportes->planillaDeEventos($filtros, $request->user(), $formato);
        } catch (DomainException $e) {
            return response($e->getMessage(), 422);
        }

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_EXPORTACION,
            $request->user(),
            mb_substr("Exportación .{$formato} de {$planilla['cantidad']} novedad(es). Filtros: "
                .($filtros ? json_encode($filtros, JSON_UNESCAPED_UNICODE) : 'ninguno'), 0, 500),
        );

        return response($planilla['contenido'], 200, [
            'Content-Type' => $formato === 'xls'
                ? 'application/vnd.ms-excel'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$planilla['nombre'].'"',
            'X-Cantidad-Eventos' => (string) $planilla['cantidad'],
        ]);
    }
}
