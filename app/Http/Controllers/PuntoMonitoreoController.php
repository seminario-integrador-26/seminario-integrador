<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaAcceso;
use App\Models\Barrio;
use App\Models\Cuadrante;
use App\Models\PuntoMonitoreo;
use App\Models\TipoPuntoMonitoreo;
use App\Services\Auth\AuditoriaAccesoService;
use App\Services\PuntoMonitoreoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * US-024: gestión de Puntos de Monitoreo (permiso puntos.gestionar,
 * Administrador de sistema): ABM, coordenadas e importación masiva.
 * Cada cambio queda en la auditoría.
 */
class PuntoMonitoreoController extends Controller
{
    public function __construct(
        private readonly PuntoMonitoreoService $puntos,
        private readonly AuditoriaAccesoService $auditoria,
    ) {}

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', 'string', 'exists:tipos_punto_monitoreo,codigo'],
            'barrio_id' => ['nullable', 'integer', 'exists:barrios,id'],
            'sin_coordenadas' => ['nullable', 'boolean'],
        ]);

        $listado = PuntoMonitoreo::query()
            ->with(['tipoPuntoMonitoreo:id,codigo,nombre', 'barrio:id,nombre', 'cuadrante:id,nombre'])
            ->withCount('eventos')
            ->when($filtros['buscar'] ?? null, fn (Builder $q, $texto) => $q->where(fn (Builder $w) => $w
                ->whereRaw('lower(codigo) like ?', ['%'.mb_strtolower($texto).'%'])
                ->orWhereRaw('lower(nombre) like ?', ['%'.mb_strtolower($texto).'%'])
                ->orWhereRaw('lower(descripcion) like ?', ['%'.mb_strtolower($texto).'%'])
                ->orWhereHas('barrio', fn (Builder $b) => $b->whereRaw('lower(nombre) like ?', ['%'.mb_strtolower($texto).'%']))))
            ->when($filtros['tipo'] ?? null, fn (Builder $q, $t) => $q->whereHas('tipoPuntoMonitoreo', fn (Builder $w) => $w->where('codigo', $t)))
            ->when($filtros['barrio_id'] ?? null, fn (Builder $q, $id) => $q->where('barrio_id', $id))
            ->when($filtros['sin_coordenadas'] ?? false, fn (Builder $q) => $q->whereNull('latitud'))
            ->orderBy('codigo')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('PuntosMonitoreo/Index', [
            'puntos' => $listado,
            'filtros' => (object) $filtros,
            'resumen' => [
                'total' => PuntoMonitoreo::count(),
                // Cantidad por tipo de PM: [codigo => cantidad].
                'por_tipo' => PuntoMonitoreo::join('tipos_punto_monitoreo', 'tipos_punto_monitoreo.id', '=', 'puntos_monitoreo.tipo_punto_monitoreo_id')
                    ->groupBy('tipos_punto_monitoreo.codigo')
                    ->selectRaw('tipos_punto_monitoreo.codigo, count(*) as cantidad')
                    ->pluck('cantidad', 'codigo'),
                'sin_coordenadas' => PuntoMonitoreo::whereNull('latitud')->count(),
            ],
            // Capa del mapa: todos los PMs georreferenciados.
            'mapa' => PuntoMonitoreo::paraMapa()->whereNotNull('latitud')->values(),
            ...$this->catalogos(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('PuntosMonitoreo/Form', [
            'punto' => null,
            ...$this->catalogos(),
            'limites' => PuntoMonitoreo::LIMITES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate(PuntoMonitoreoService::reglas(), PuntoMonitoreoService::mensajes());
        $punto = PuntoMonitoreo::create($datos);

        $this->auditar($request, AuditoriaAcceso::EVENTO_PM_ALTA, "PM {$punto->codigo} creado.");

        return redirect()->route('puntos.index')->with('success', "PM {$punto->codigo} creado.");
    }

    public function edit(PuntoMonitoreo $punto): Response
    {
        return Inertia::render('PuntosMonitoreo/Form', [
            'punto' => $punto->only([
                'id', 'codigo', 'nombre', 'tipo_punto_monitoreo_id', 'barrio_id', 'cuadrante_id', 'descripcion', 'latitud', 'longitud',
            ]),
            ...$this->catalogos(),
            'limites' => PuntoMonitoreo::LIMITES,
        ]);
    }

    public function update(Request $request, PuntoMonitoreo $punto): RedirectResponse
    {
        $datos = $request->validate(PuntoMonitoreoService::reglas($punto->id), PuntoMonitoreoService::mensajes());
        $punto->fill($datos);
        $cambios = implode(', ', array_keys($punto->getDirty()));
        $punto->save();

        $this->auditar($request, AuditoriaAcceso::EVENTO_PM_MOD, "PM {$punto->codigo} actualizado".($cambios ? " ({$cambios})." : '.'));

        return redirect()->route('puntos.index')->with('success', "PM {$punto->codigo} actualizado.");
    }

    /**
     * Un PM con eventos no se borra: los eventos son inmutables y lo referencian.
     */
    public function destroy(Request $request, PuntoMonitoreo $punto): RedirectResponse
    {
        if ($punto->eventos()->exists()) {
            return back()->with('error', "El PM {$punto->codigo} tiene novedades registradas: no se puede eliminar.");
        }

        $punto->delete();
        $this->auditar($request, AuditoriaAcceso::EVENTO_PM_BAJA, "PM {$punto->codigo} eliminado.");

        return back()->with('success', "PM {$punto->codigo} eliminado.");
    }

    public function importar(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx,xls'],
        ], ['archivo.mimes' => 'El archivo tiene que ser .csv, .xlsx o .xls.']);

        $archivo = $request->file('archivo');
        $resultado = $this->puntos->importar($archivo->getRealPath(), $archivo->getClientOriginalExtension());

        if (! $resultado['ok']) {
            return back()->with('error', 'No se importó ningún PM: corregí el archivo y volvé a subirlo.')
                ->with('erroresImportacion', array_slice($resultado['errores'], 0, 50));
        }

        $mensaje = "Importación completa: {$resultado['nuevos']} PM nuevos y {$resultado['actualizados']} actualizados.";
        $this->auditar($request, AuditoriaAcceso::EVENTO_PM_IMPORT, $mensaje);

        return back()->with('success', $mensaje);
    }

    /**
     * Plantilla CSV (UTF-8 con BOM para que Excel respete las tildes).
     */
    public function plantilla(): StreamedResponse
    {
        return response()->streamDownload(function () {
            echo "\u{FEFF}codigo,nombre,tipo,barrio,cuadrante,descripcion,latitud,longitud\n";
            echo "PM-M-001,Bv. España y Mendoza,municipal,Centro,,,-32.4075,-63.2403\n";
        }, 'plantilla_puntos_monitoreo.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Opciones de los selects: tipos de PM y listas de barrios / cuadrantes.
     *
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'tiposPunto' => TipoPuntoMonitoreo::orderBy('id')->get(['id', 'codigo', 'nombre']),
            'barrios' => Barrio::orderBy('nombre')->get(['id', 'nombre']),
            'cuadrantes' => Cuadrante::orderBy('nombre')->get(['id', 'nombre']),
        ];
    }

    private function auditar(Request $request, string $accion, string $detalle): void
    {
        $this->auditoria->registrarAccion($accion, $request->user(), $detalle);
    }
}
