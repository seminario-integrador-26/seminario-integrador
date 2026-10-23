<?php

namespace Tests\Feature\Eventos;

use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use App\Services\ErrataService;
use App\Services\EstadisticaService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Decisión del equipo (2026-10-15): estadísticas, análisis, mapa, Excel, API
 * y la consulta de eventos usan los valores CORREGIDOS por fe de errata
 * (vista eventos_vigentes); el original en `eventos` no cambia.
 */
class ValoresVigentesTest extends TestCase
{
    use RefreshDatabase;

    private User $administrativo;

    private TipoEvento $robo;

    private TipoEvento $ruidos;

    private PuntoMonitoreo $plaza;

    private PuntoMonitoreo $ruta;

    private Evento $evento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));

        $this->administrativo = User::factory()->create()->assignRole('Administrativo');
        $this->robo = TipoEvento::factory()->delGrupo('policia')->create(['nombre' => 'Robo']);
        $this->ruidos = TipoEvento::factory()->delGrupo('guardia_local')->create(['nombre' => 'Ruidos']);
        $this->plaza = PuntoMonitoreo::factory()->delTipo('municipal')->create(['codigo' => 'PM-M-001']);
        $this->ruta = PuntoMonitoreo::factory()->delTipo('provincial')->create(['codigo' => 'PM-P-001']);

        // Se cargó como Robo en la Plaza el 30/09; en realidad fue Ruidos en
        // la Ruta el 02/10.
        $this->evento = Evento::factory()->create([
            'tipo_evento_id' => $this->robo->id,
            'punto_monitoreo_id' => $this->plaza->id,
            'fecha_hora' => '2026-09-30 23:00:00',
            'timestamp_video' => '22:59:00',
            'descripcion' => 'Original',
        ]);

        app(ErrataService::class)->registrar($this->evento, [
            'tipo_evento_id' => $this->ruidos->id,
            'fecha' => '2026-10-02',
            'hora' => '01:00:00',
            'punto_monitoreo_id' => $this->ruta->id,
            'timestamp_video' => '00:59:00',
            'descripcion' => 'Corregida',
        ], 'Se cargó mal en la guardia.', User::factory()->create()->assignRole('Supervisor'));
    }

    public function test_el_original_no_cambia(): void
    {
        $original = Evento::find($this->evento->id);

        $this->assertSame($this->robo->id, $original->tipo_evento_id);
        $this->assertSame('2026-09-30 23:00:00', $original->fecha_hora->format('Y-m-d H:i:s'));
        $this->assertSame('Original', $original->descripcion);
    }

    public function test_las_estadisticas_cuentan_el_valor_corregido(): void
    {
        $setiembre = app(EstadisticaService::class)->mensual(2026, 9);
        $octubre = app(EstadisticaService::class)->mensual(2026, 10);

        $this->assertSame(0, $setiembre['total']);
        $this->assertSame(1, $octubre['total']);
        $this->assertSame([['nombre' => 'Ruidos', 'categoria' => 'Guardia Local', 'cantidad' => 1]], $octubre['por_tipo']);
    }

    public function test_el_mapa_ubica_el_evento_en_el_pm_corregido(): void
    {
        $this->actingAs($this->administrativo)
            ->get(route('mapa.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('densidad.total', 1)
                ->where('densidad.puntos.0.codigo', 'PM-P-001'));
    }

    public function test_la_consulta_lista_y_filtra_por_el_valor_corregido(): void
    {
        $this->actingAs($this->administrativo)
            ->get(route('eventos.index', ['tipo_evento_id' => $this->ruidos->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('eventos.data', 1)
                ->where('eventos.data.0.tipo', 'Ruidos')
                ->where('eventos.data.0.descripcion', 'Corregida')
                ->where('eventos.data.0.corregido', true));

        $this->actingAs($this->administrativo)
            ->get(route('eventos.index', ['tipo_evento_id' => $this->robo->id]))
            ->assertInertia(fn (Assert $page) => $page->has('eventos.data', 0));
    }

    public function test_el_excel_exporta_el_valor_corregido(): void
    {
        $this->actingAs($this->administrativo)
            ->get(route('reportes.eventos.planilla', ['formato' => 'xlsx', 'desde' => '2026-10-01', 'hasta' => '2026-10-31']))
            ->assertOk()
            ->assertHeader('X-Cantidad-Eventos', '1');
    }

    public function test_la_api_publica_devuelve_el_valor_corregido(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['datos-abiertos:leer']);

        $this->getJson('/api/v1/eventos')
            ->assertOk()
            ->assertJsonPath('data.0.fecha', '2026-10-02')
            ->assertJsonPath('data.0.tipo', 'Ruidos')
            ->assertJsonPath('data.0.punto_monitoreo.codigo', 'PM-P-001')
            ->assertJsonPath('data.0.corregido', true);

        $this->getJson("/api/v1/eventos/{$this->evento->id}")
            ->assertOk()
            ->assertJsonPath('data.tipo', 'Ruidos');
    }
}
