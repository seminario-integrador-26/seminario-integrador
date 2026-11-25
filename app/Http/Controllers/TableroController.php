<?php

namespace App\Http\Controllers;

use App\Models\GrupoEvento;
use App\Models\Parametro;
use App\Models\TipoEvento;
use App\Services\MapaService;
use App\Services\Tableros\TableroEstrategicoService;
use App\Services\Tableros\TableroOperativoService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tableros: Control Operativo (tiempo real), Análisis Estratégico (rango de
 * fechas) y Mapa de calor. Controller FINO: los datos los arman los services.
 */
class TableroController extends Controller
{
    /**
     * Tablero operativo: se refresca solo pidiendo la prop `tablero`
     * (intervalo = parámetro del sistema, US-029).
     */
    public function operativo(TableroOperativoService $tablero): Response
    {
        return Inertia::render('Tableros/Operativo', [
            'tablero' => fn () => $tablero->calcular(),
            'intervaloRefresco' => Parametro::valor(Parametro::REFRESCO_DASHBOARDS),
        ]);
    }

    /**
     * Tablero estratégico: se calcula bajo demanda, al cambiar el rango o las categorías.
     */
    public function estrategico(Request $request, TableroEstrategicoService $tablero): Response
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'categorias' => ['nullable', 'array'],
            'categorias.*' => ['string', 'exists:grupos_evento,codigo'],
        ]);

        return Inertia::render('Tableros/Estrategico', [
            'filtros' => (object) $filtros,
            'tablero' => $tablero->calcular($filtros),
            'categorias' => GrupoEvento::opciones(),
        ]);
    }

    /**
     * Mapa de calor: uno, varios o todos los tipos en un rango; por defecto,
     * todas las novedades en todos los puntos hasta la fecha.
     */
    public function calor(Request $request, MapaService $mapa): Response
    {
        $filtros = $request->validate([
            'tipos' => ['nullable', 'array'],
            'tipos.*' => ['integer', 'exists:tipos_evento,id'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $filtros['desde'] ??= $mapa->primeraFecha();
        $filtros['hasta'] ??= now()->toDateString();

        return Inertia::render('Tableros/Calor', [
            'filtros' => $filtros,
            'densidad' => $mapa->densidad($filtros),
            'primeraFecha' => $mapa->primeraFecha(),
            // Solo los tipos que entran en el mapa (cuantificables, RN4).
            'tipos' => TipoEvento::with('grupoEvento:id,codigo')
                ->where('es_cuantificable', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'grupo_evento_id'])
                ->map(fn (TipoEvento $t) => ['id' => $t->id, 'nombre' => $t->nombre, 'categoria' => $t->grupoEvento->codigo]),
            'categorias' => GrupoEvento::opciones(),
        ]);
    }
}
