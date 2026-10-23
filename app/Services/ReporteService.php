<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\TipoEvento;
use App\Models\User;
use App\Services\Exportadores\XlsExportador;
use DomainException;
use Illuminate\Support\Carbon;

/**
 * Arma los datos de los reportes (US-012 Libro de guardia en PDF, US-013 planilla de
 * eventos) y delega la generación del archivo en ExportacionService, que no
 * sabe nada del dominio.
 */
class ReporteService
{
    public function __construct(
        private readonly ExportacionService $exportacion,
        private readonly EventoService $eventos,
        private readonly LibroGuardiaService $libro,
    ) {}

    /**
     * US-012: Libro de guardia en PDF. Se elige una Apertura de turno y el PDF
     * incluye todo lo registrado hasta el Cierre de turno siguiente (valores
     * vigentes, con fe de errata). El personal presente y las novedades van
     * en la descripción de la apertura y del cierre.
     *
     * @return array{nombre: string, contenido: string}
     *
     * @throws DomainException si el evento no es una apertura o el turno sigue abierto.
     */
    public function pdfDeGuardia(Evento $apertura, User $generadoPor): array
    {
        if ($apertura->tipo_evento_id !== TipoEvento::interno(TipoEvento::APERTURA_TURNO)) {
            throw new DomainException('El reporte se genera a partir de una Apertura de turno.');
        }

        $cierre = $this->libro->cierreSiguiente($apertura)
            ?? throw new DomainException('El turno sigue abierto: el reporte se genera al registrar el Cierre de turno.');

        $apertura->loadMissing('usuario:id,name');
        $cierre->loadMissing('usuario:id,name');

        $eventos = $this->libro->eventosDe($apertura, $cierre)
            ->with(['tipoEvento:id,nombre,grupo_evento_id', 'tipoEvento.grupoEvento:id,nombre', 'puntoMonitoreo:id,codigo,nombre', 'barrio:id,nombre'])
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get();

        $fecha = $apertura->fecha_hora->format('d/m/Y');

        $contenido = $this->exportacion->exportar('pdf', [
            'vista' => 'reportes.turno',
            'titulo' => "Libro de guardia #{$apertura->id}",
            'turno' => [
                'id' => $apertura->id,
                'fecha' => $fecha,
                'inicio' => $apertura->fecha_hora->format('H:i'),
                'fin' => ($cierre->fecha_hora->format('d/m/Y') !== $fecha
                    ? $cierre->fecha_hora->format('d/m/Y').' '
                    : '').$cierre->fecha_hora->format('H:i'),
                'supervisor' => $apertura->usuario?->name,
                'supervisor_cierre' => $cierre->usuario?->name,
                'apertura' => $apertura->descripcion,
                'cierre' => $cierre->descripcion,
            ],
            'eventos' => $eventos->map(fn (Evento $e) => [
                'id' => $e->id,
                'hora' => $e->fecha_hora->format('H:i:s'),
                'fecha' => $e->fecha_hora->format('d/m/Y'),
                'tipo' => $e->tipoEvento->nombre,
                'categoria' => $e->tipoEvento->grupoEvento->nombre,
                'descripcion' => $e->descripcion,
                'punto' => $e->ubicacion(),
                'timestamp_video' => $e->timestamp_video,
            ])->all(),
            'resumen' => $eventos
                ->groupBy(fn (Evento $e) => $e->tipoEvento->nombre)
                ->map(fn ($grupo, $tipo) => [
                    'tipo' => $tipo,
                    'categoria' => $grupo->first()->tipoEvento->grupoEvento->nombre,
                    'cantidad' => $grupo->count(),
                ])
                ->sortByDesc('cantidad')
                ->values()
                ->all(),
            'generado' => [
                'por' => $generadoPor->name,
                'fecha' => now()->format('d/m/Y H:i'),
            ],
        ]);

        return [
            'nombre' => sprintf('Libro_Guardia_%d_%s.pdf', $apertura->id, $apertura->fecha_hora->format('d-m-Y')),
            'contenido' => $contenido,
        ];
    }

    /**
     * US-013: planilla con TODOS los eventos que coinciden con los filtros
     * (sin límite), fechas DD/MM/AAAA y columna de evento informativo / sin
     * ubicación. Formato .xlsx (UTF-8, Datos Abiertos) o .xls (el que pide
     * el doc); el equipo decidió ofrecer ambos.
     *
     * @param  array<string, mixed>  $filtros
     * @param  'xlsx'|'xls'  $formato
     * @return array{nombre: string, contenido: string, cantidad: int}
     *
     * @throws DomainException si los eventos no entran en una hoja .xls.
     */
    public function planillaDeEventos(array $filtros, User $consultante, string $formato = 'xlsx'): array
    {
        $eventos = $this->eventos->paraExportar($filtros, $consultante);

        if ($formato === 'xls' && $eventos->count() > XlsExportador::MAX_FILAS) {
            throw new DomainException(
                "Son {$eventos->count()} eventos y el formato .xls admite hasta ".XlsExportador::MAX_FILAS
                .'. Exportá a .xlsx o acotá los filtros.'
            );
        }

        $filas = $eventos->map(fn (Evento $e) => [
            $e->id,
            $e->fecha_hora->format('d/m/Y'),
            $e->fecha_hora->format('H:i:s'),
            $e->tipoEvento->grupoEvento->nombre,
            $e->tipoEvento->nombre,
            $e->puntoMonitoreo?->codigo,
            $e->puntoMonitoreo?->nombre,
            $e->puntoMonitoreo?->tipoPuntoMonitoreo->nombre,
            $e->puntoMonitoreo?->barrio?->nombre ?? $e->barrio?->nombre,
            $e->direccion,
            $e->timestamp_video,
            $e->descripcion,
            $e->usuario?->name,
            $this->observacion($e),
        ])->all();

        [$desde, $hasta] = $this->rangoDelArchivo($filtros, $eventos);

        return [
            'nombre' => "Eventos_CMU_{$desde}_{$hasta}.{$formato}",
            'cantidad' => count($filas),
            'contenido' => $this->exportacion->exportar($formato, [
                'titulo' => 'Eventos CMU',
                'columnas' => [
                    'ID', 'Fecha', 'Horario', 'Categoría', 'Tipo', 'PM código', 'PM nombre',
                    'Tipo PM', 'Barrio', 'Dirección', 'Timestamp video', 'Descripción', 'Registró', 'Observación',
                ],
                'filas' => $filas,
            ]),
        ];
    }

    private function observacion(Evento $evento): ?string
    {
        if (! $evento->tipoEvento->es_cuantificable) {
            return 'Evento informativo';
        }

        if ($evento->punto_monitoreo_id) {
            return null;
        }

        return $evento->barrio_id || $evento->direccion ? 'Evento sin PM' : 'Evento sin ubicación';
    }

    /**
     * Fechas del nombre del archivo: las del filtro o, si no hay, las de los
     * eventos exportados.
     *
     * @return array{0: string, 1: string}
     */
    private function rangoDelArchivo(array $filtros, $eventos): array
    {
        $formato = fn ($fecha) => Carbon::parse($fecha)->format('d-m-Y');
        $fechas = $eventos->pluck('fecha_hora');

        return [
            $formato($filtros['desde'] ?? $fechas->min() ?? now()),
            $formato($filtros['hasta'] ?? $fechas->max() ?? now()),
        ];
    }
}
