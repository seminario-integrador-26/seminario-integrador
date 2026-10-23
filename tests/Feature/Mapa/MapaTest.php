<?php

namespace Tests\Feature\Mapa;

use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-016 (mapa de eventos) y US-017 (mapa de calor).
 */
class MapaTest extends TestCase
{
    use RefreshDatabase;

    private User $administrativo;

    private TipoEvento $robo;

    private TipoEvento $ruidos;

    private PuntoMonitoreo $plaza;

    private PuntoMonitoreo $ruta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));

        $this->administrativo = User::factory()->create()->assignRole('Administrativo');
        $this->robo = TipoEvento::factory()->delGrupo('policia')->create();
        $this->ruidos = TipoEvento::factory()->delGrupo('guardia_local')->create();
        $this->plaza = PuntoMonitoreo::factory()->delTipo('municipal')->create(['codigo' => 'PM-M-001', 'latitud' => -32.41, 'longitud' => -63.24]);
        $this->ruta = PuntoMonitoreo::factory()->delTipo('provincial')->create(['codigo' => 'PM-P-001', 'latitud' => -32.40, 'longitud' => -63.25]);
    }

    private function evento(TipoEvento $tipo, ?PuntoMonitoreo $punto, string $fechaHora = '2026-10-10 10:00:00'): void
    {
        Evento::factory()->create([
            'tipo_evento_id' => $tipo->id,
            'punto_monitoreo_id' => $punto?->id,
            'fecha_hora' => $fechaHora,
        ]);
    }

    public function test_agrupa_los_eventos_por_pm_con_sus_coordenadas(): void
    {
        $this->evento($this->robo, $this->plaza);
        $this->evento($this->robo, $this->plaza);
        $this->evento($this->ruidos, $this->ruta);

        $this->actingAs($this->administrativo)
            ->get(route('mapa.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mapa/Index')
                ->where('densidad.total', 3)
                ->has('densidad.puntos', 2)
                ->where('densidad.puntos.0.codigo', 'PM-M-001')
                ->where('densidad.puntos.0.cantidad', 2)
                ->where('densidad.puntos.0.latitud', -32.41)
                ->where('densidad.puntos.0.tipo', 'municipal')
                ->where('densidad.puntos.1.tipo', 'provincial')
                ->has('puntos', 2));
    }

    public function test_excluye_eventos_sin_ubicacion_e_informativos(): void
    {
        $informativo = TipoEvento::factory()->delGrupo('internos')->create(['es_cuantificable' => false]);
        $this->evento($this->robo, $this->plaza);
        $this->evento($this->robo, null);
        $this->evento($informativo, $this->plaza);

        $this->actingAs($this->administrativo)
            ->get(route('mapa.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('densidad.total', 1)
                ->where('densidad.sin_ubicacion', 1));
    }

    public function test_us016_un_marcador_por_evento_con_categoria_y_tipo_de_pm(): void
    {
        $informativo = TipoEvento::factory()->delGrupo('internos')->create(['es_cuantificable' => false]);
        $this->evento($this->robo, $this->plaza, '2026-10-10 10:00:00');
        $this->evento($this->ruidos, $this->ruta, '2026-10-11 09:30:00');
        $this->evento($this->robo, null, '2026-10-11 11:00:00');       // sin ubicación
        $this->evento($informativo, $this->plaza, '2026-10-12 08:00:00'); // no cuantificable

        $this->actingAs($this->administrativo)
            ->get(route('mapa.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('eventos.items', 2)
                ->where('eventos.total', 2)
                ->where('eventos.limite', 2000)
                // Del más reciente al más antiguo.
                ->where('eventos.items.0.fecha', '11/10/2026')
                ->where('eventos.items.0.hora', '09:30')
                ->where('eventos.items.0.categoria', 'guardia_local')
                ->where('eventos.items.0.tipo_punto', 'provincial')
                ->where('eventos.items.0.latitud', -32.4)
                ->where('eventos.items.1.categoria', 'policia')
                ->where('eventos.items.1.tipo_punto', 'municipal')
                ->where('eventos.items.1.punto', 'PM-M-001 — '.$this->plaza->nombre));

        // Respeta los filtros del mapa.
        $this->actingAs($this->administrativo)
            ->get(route('mapa.index', ['categorias' => ['policia']]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('eventos.items', 1)
                ->where('eventos.items.0.categoria', 'policia'));
    }

    public function test_excluye_los_pm_sin_coordenadas_y_los_cuenta_aparte(): void
    {
        $sinRelevar = PuntoMonitoreo::factory()->delTipo('alarma')->create([
            'codigo' => 'PM-A-001', 'latitud' => null, 'longitud' => null,
        ]);
        $this->evento($this->robo, $this->plaza);
        $this->evento($this->robo, $sinRelevar);
        $this->evento($this->ruidos, $sinRelevar);

        $this->actingAs($this->administrativo)
            ->get(route('mapa.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('densidad.total', 1)
                ->has('densidad.puntos', 1)
                ->where('densidad.puntos.0.codigo', 'PM-M-001')
                ->where('densidad.sin_coordenadas', 2)
                ->where('densidad.sin_ubicacion', 0)
                ->has('eventos.items', 1));
    }

    public function test_filtra_por_categoria_y_periodo(): void
    {
        $this->evento($this->robo, $this->plaza, '2026-10-10 10:00:00');
        $this->evento($this->ruidos, $this->ruta, '2026-10-10 10:00:00');
        $this->evento($this->robo, $this->ruta, '2026-08-01 10:00:00');

        $this->actingAs($this->administrativo)
            ->get(route('mapa.index', ['categorias' => ['policia']]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('densidad.total', 1)
                ->where('densidad.puntos.0.codigo', 'PM-M-001'));

        $this->actingAs($this->administrativo)
            ->get(route('mapa.index', ['desde' => '2026-07-01', 'hasta' => '2026-10-31']))
            ->assertInertia(fn (Assert $page) => $page->where('densidad.total', 3));
    }

    public function test_por_defecto_muestra_los_ultimos_30_dias(): void
    {
        $this->evento($this->robo, $this->plaza, '2026-09-16 00:00:00'); // día 30 contando hoy
        $this->evento($this->robo, $this->plaza, '2026-09-15 23:59:59'); // afuera

        $this->actingAs($this->administrativo)
            ->get(route('mapa.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filtros.desde', '2026-09-16')
                ->where('filtros.hasta', '2026-10-15')
                ->where('densidad.total', 1));
    }

    public function test_el_supervisor_no_ve_el_mapa_analitico(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Supervisor'))
            ->get(route('mapa.index'))
            ->assertForbidden();
    }
}
