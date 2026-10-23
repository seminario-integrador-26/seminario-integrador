<?php

namespace Tests\Feature\Analisis;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Barrio;
use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use App\Services\AnalisisService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-015 (KPIs), US-018 (tendencias), US-019 (ranking), US-020 (franja
 * horaria) y US-022 (refresco parcial).
 */
class AnalisisTest extends TestCase
{
    use RefreshDatabase;

    private TipoEvento $robo;

    private TipoEvento $ruidos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));

        $this->robo = TipoEvento::factory()->delGrupo('policia')->create();
        $this->ruidos = TipoEvento::factory()->delGrupo('guardia_local')->create();
    }

    private function evento(string $fechaHora, ?TipoEvento $tipo = null, ?PuntoMonitoreo $punto = null): void
    {
        Evento::factory()->create([
            'tipo_evento_id' => ($tipo ?? $this->robo)->id,
            'punto_monitoreo_id' => $punto?->id,
            'fecha_hora' => $fechaHora,
        ]);
    }

    private function calcular(array $filtros = []): array
    {
        return app(AnalisisService::class)->calcular($filtros);
    }

    public function test_kpis_del_mes_y_del_anio_con_variacion(): void
    {
        $this->evento('2026-10-02 10:00:00');
        $this->evento('2026-10-14 10:00:00');
        $this->evento('2026-10-15 11:00:00');
        $this->evento('2026-09-10 10:00:00');
        $this->evento('2026-09-16 10:00:00'); // después del día 15: no entra en el tramo comparable
        $this->evento('2025-03-01 10:00:00');

        $k = $this->calcular()['kpis'];

        $this->assertSame(['total' => 3, 'anterior' => 1, 'variacion' => 200.0], $k['mes']);
        $this->assertSame(5, $k['anio']['total']);
        $this->assertSame(1, $k['anio']['anterior']);
        $this->assertSame(400.0, $k['anio']['variacion']);
    }

    public function test_sin_periodo_anterior_la_variacion_es_nula(): void
    {
        $this->evento('2026-10-02 10:00:00');

        $this->assertNull($this->calcular()['kpis']['anio']['variacion']);
    }

    public function test_porcentaje_por_categoria_del_periodo_excluye_informativos(): void
    {
        $informativo = TipoEvento::factory()->delGrupo('internos')->create(['es_cuantificable' => false]);
        $this->evento('2026-10-10 10:00:00', $this->robo);
        $this->evento('2026-10-11 10:00:00', $this->robo);
        $this->evento('2026-10-12 10:00:00', $this->robo);
        $this->evento('2026-10-12 11:00:00', $this->ruidos);
        $this->evento('2026-10-12 12:00:00', $informativo);

        $k = $this->calcular()['kpis'];
        $porcentajes = collect($k['por_categoria'])->pluck('porcentaje', 'clave')->all();

        $this->assertSame(4, $k['periodo']);
        $this->assertSame(['policia' => 75.0, 'guardia_local' => 25.0, 'internos' => 0.0], $porcentajes);
    }

    public function test_ultimos_seis_meses_por_categoria(): void
    {
        $this->evento('2026-05-20 10:00:00'); // mayo: 6 meses atrás contando octubre
        $this->evento('2026-04-30 10:00:00'); // afuera
        $this->evento('2026-10-01 10:00:00', $this->ruidos);

        $meses = $this->calcular()['ultimos_meses'];

        $this->assertCount(6, $meses);
        $this->assertSame('2026-05', $meses[0]['clave']);
        $this->assertSame(1, $meses[0]['policia']);
        $this->assertSame(1, $meses[5]['guardia_local']);
        $this->assertSame('2026-10-31', $meses[5]['hasta']);
    }

    public function test_tendencia_por_dia_semana_y_mes(): void
    {
        $this->evento('2026-10-14 10:00:00');
        $this->evento('2026-10-14 18:00:00');
        $this->evento('2026-10-01 10:00:00');

        $porDia = $this->calcular(['periodo' => 'mes'])['tendencia'];
        $this->assertCount(30, $porDia);
        $this->assertSame(2, collect($porDia)->firstWhere('clave', '2026-10-14')['total']);

        $porSemana = $this->calcular(['periodo' => 'mes', 'granularidad' => 'semana'])['tendencia'];
        $this->assertSame(3, array_sum(array_column($porSemana, 'total')));
        $this->assertSame('2026-09-16', $porSemana[0]['desde']); // el tramo no excede el período

        $porMes = $this->calcular(['periodo' => 'anio', 'granularidad' => 'mes'])['tendencia'];
        $this->assertSame(3, collect($porMes)->firstWhere('clave', '2026-10-01')['total']);
    }

    public function test_ranking_por_pm_y_por_barrio(): void
    {
        $centro = Barrio::create(['nombre' => 'Centro']);
        $plaza = PuntoMonitoreo::factory()->create(['codigo' => 'PM-1', 'barrio_id' => $centro->id]);
        $terminal = PuntoMonitoreo::factory()->create(['codigo' => 'PM-2', 'barrio_id' => $centro->id]);
        $ruta = PuntoMonitoreo::factory()->create(['codigo' => 'PM-3', 'barrio_id' => null]);

        $this->evento('2026-10-10 10:00:00', punto: $plaza);
        $this->evento('2026-10-10 11:00:00', punto: $plaza);
        $this->evento('2026-10-10 12:00:00', punto: $terminal);
        $this->evento('2026-10-10 13:00:00', punto: $ruta);
        $this->evento('2026-10-10 14:00:00'); // sin ubicación

        $ranking = $this->calcular()['ranking'];

        $this->assertSame('PM-1', explode(' — ', $ranking['punto'][0]['nombre'])[0]);
        $this->assertSame(2, $ranking['punto'][0]['cantidad']);
        $this->assertSame(50.0, $ranking['punto'][0]['porcentaje']);
        $this->assertSame([['clave' => (string) $centro->id, 'nombre' => 'Centro', 'cantidad' => 3, 'porcentaje' => 100.0]], $ranking['barrio']);
    }

    public function test_franja_horaria_con_las_tres_horas_destacadas(): void
    {
        foreach (['22:10', '22:40', '22:50', '03:00', '03:30', '14:00', '09:00'] as $hora) {
            $this->evento("2026-10-10 {$hora}:00");
        }

        $franja = $this->calcular()['franja_horaria'];

        $this->assertCount(24, $franja);
        $this->assertSame(3, $franja[22]['cantidad']);
        $this->assertSame(42.9, $franja[22]['porcentaje']);
        $this->assertTrue($franja[22]['destacada']);
        $this->assertTrue($franja[3]['destacada']);
        $this->assertSame(3, collect($franja)->where('destacada', true)->count());
        $this->assertFalse($franja[5]['destacada']);
    }

    public function test_el_periodo_personalizado_y_el_filtro_de_categoria(): void
    {
        $this->evento('2026-08-05 10:00:00', $this->robo);
        $this->evento('2026-08-06 10:00:00', $this->ruidos);

        $a = $this->calcular([
            'periodo' => 'personalizado', 'desde' => '2026-08-01', 'hasta' => '2026-08-31',
            'categorias' => ['guardia_local'],
        ]);

        $this->assertSame(1, $a['kpis']['periodo']);
        $this->assertSame('2026-08-01', $a['periodo']['desde']);
    }

    public function test_la_pantalla_y_el_refresco_parcial(): void
    {
        $admin = User::factory()->create()->assignRole('Administrativo');

        $this->actingAs($admin)
            ->get(route('analisis.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Analisis/Index')
                ->where('intervaloRefresco', 60)
                ->has('analisis.kpis')
                ->has('analisis.franja_horaria', 24));

        // US-022: el refresco pide solo la prop 'analisis'.
        $version = app(HandleInertiaRequests::class)->version(request());
        $this->actingAs($admin)
            ->get(route('analisis.index'), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) $version,
                'X-Inertia-Partial-Component' => 'Analisis/Index',
                'X-Inertia-Partial-Data' => 'analisis',
            ])
            ->assertOk()
            ->assertJsonPath('component', 'Analisis/Index')
            ->assertJsonMissingPath('props.categorias')
            ->assertJsonStructure(['props' => ['analisis' => ['actualizado']]]);
    }

    public function test_el_supervisor_no_accede(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Supervisor'))
            ->get(route('analisis.index'))
            ->assertForbidden();
    }
}
