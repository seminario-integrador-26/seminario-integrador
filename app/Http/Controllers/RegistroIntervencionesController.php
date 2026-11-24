<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaAcceso;
use App\Services\Auth\AuditoriaAccesoService;
use App\Services\RegistroIntervencionesService;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Registro de Intervenciones mensual para el Portal de Transparencia
 * (permiso reportes.intervenciones: Supervisor, Administrativo y
 * Administrador de sistema). Controller fino: los conteos y el archivo los
 * arma RegistroIntervencionesService. Cada descarga queda auditada (US-026).
 */
class RegistroIntervencionesController extends Controller
{
    public function __construct(
        private readonly RegistroIntervencionesService $registro,
        private readonly AuditoriaAccesoService $auditoria,
    ) {}

    public function index(Request $request): Response
    {
        [$anio, $mes] = $this->periodo($request);

        try {
            $this->registro->validarMes($anio, $mes);
            $resumen = $this->registro->resumen($anio, $mes);
            $error = null;
        } catch (DomainException $e) {
            $resumen = null;
            $error = $e->getMessage();
        }

        return Inertia::render('Intervenciones/Index', [
            'filtros' => ['anio' => $anio, 'mes' => $mes],
            'resumen' => $resumen,
            'error' => $error,
            'enCurso' => $anio === now()->year && $mes === now()->month,
        ]);
    }

    public function descargar(Request $request, int $anio, int $mes): HttpResponse
    {
        abort_unless($mes >= 1 && $mes <= 12 && $anio >= 2000 && $anio <= 2100, 404);

        try {
            $this->registro->validarMes($anio, $mes);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $planilla = $this->registro->generar($anio, $mes);

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_EXPORTACION,
            $request->user(),
            "Registro de Intervenciones {$planilla['nombre']} ({$planilla['cantidad']} novedad(es)).",
        );

        return response($planilla['contenido'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$planilla['nombre'].'"',
        ]);
    }

    /**
     * Por defecto el mes anterior: la planilla se publica al cerrar el mes.
     *
     * @return array{0: int, 1: int}
     */
    private function periodo(Request $request): array
    {
        $datos = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $anterior = now()->startOfMonth()->subMonth();

        return [
            (int) ($datos['anio'] ?? $anterior->year),
            (int) ($datos['mes'] ?? $anterior->month),
        ];
    }
}
