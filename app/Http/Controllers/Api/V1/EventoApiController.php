<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\EventoResource;
use App\Models\Evento;
use App\Models\GrupoEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * API PÚBLICA versionada (/api/v1), SOLO LECTURA, para Datos Abiertos.
 * Convive con Inertia (que resuelve el frontend interno); esto es ADEMÁS.
 * Auth: token personal de Sanctum con la habilidad 'datos-abiertos:leer'.
 *
 * Decisión 2026-10-23: los eventos del grupo Internos (apertura y cierre de
 * turno, notas, erratas, oficios) son del libro de guardia y no se publican.
 */
class EventoApiController extends Controller
{
    private const RELACIONES = [
        'tipoEvento:id,nombre,grupo_evento_id,es_cuantificable',
        'tipoEvento.grupoEvento:id,codigo,nombre',
        'puntoMonitoreo:id,codigo,nombre,tipo_punto_monitoreo_id,barrio_id,latitud,longitud',
        'puntoMonitoreo.tipoPuntoMonitoreo:id,codigo,nombre',
        'puntoMonitoreo.barrio:id,nombre',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'categoria' => ['nullable', 'string', 'exists:grupos_evento,codigo'], // código de GrupoEvento
            'tipo_punto' => ['nullable', 'string', 'exists:tipos_punto_monitoreo,codigo'],
            'punto' => ['nullable', 'string', 'max:30'], // código del PM
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $eventos = $this->publicos()
            ->with(self::RELACIONES)
            ->select('eventos.*')
            ->withCount('erratas')
            ->when($filtros['desde'] ?? null, fn (Builder $q, $d) => $q->where('fecha_hora', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $h) => $q->where('fecha_hora', '<', Carbon::parse($h)->addDay()->startOfDay()))
            ->when($filtros['categoria'] ?? null, fn (Builder $q, $c) => $q->whereHas('tipoEvento.grupoEvento', fn (Builder $g) => $g->where('codigo', $c)))
            ->when($filtros['tipo_punto'] ?? null, fn (Builder $q, $t) => $q->whereHas('puntoMonitoreo.tipoPuntoMonitoreo', fn (Builder $p) => $p->where('codigo', $t)))
            ->when($filtros['punto'] ?? null, fn (Builder $q, $codigo) => $q->whereHas('puntoMonitoreo', fn (Builder $p) => $p->where('codigo', $codigo)))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->paginate($filtros['por_pagina'] ?? 50)
            ->withQueryString();

        return EventoResource::collection($eventos);
    }

    public function show(int $evento): EventoResource
    {
        // Valores vigentes (con fe de errata), igual que el listado.
        return new EventoResource(
            $this->publicos()
                ->select('eventos.*')
                ->with(self::RELACIONES)
                ->withCount('erratas')
                ->findOrFail($evento),
        );
    }

    /**
     * @return Builder<Evento>
     */
    private function publicos(): Builder
    {
        return Evento::vigentes()->whereDoesntHave(
            'tipoEvento.grupoEvento',
            fn (Builder $g) => $g->where('codigo', GrupoEvento::INTERNOS),
        );
    }
}
