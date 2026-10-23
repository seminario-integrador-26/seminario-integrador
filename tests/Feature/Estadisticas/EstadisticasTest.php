<?php

namespace Tests\Feature\Estadisticas;

use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use App\Services\EstadisticaService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-014: estadísticas mensuales y anuales (RN4, RN11).
 */
class EstadisticasTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private TipoEvento $robo;

    private TipoEvento $ruidos;

    private TipoEvento $informativo;

    private PuntoMonitoreo $municipal;

    private PuntoMonitoreo $provincial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));

        $this->supervisor = User::factory()->create();
        $this->robo = TipoEvento::factory()->delGrupo('policia')->create(['nombre' => 'Robo']);
        $this->ruidos = TipoEvento::factory()->delGrupo('guardia_local')->create(['nombre' => 'Ruidos']);
        $this->informativo = TipoEvento::factory()->sinUbicacion()->delGrupo('internos')->create([
            'nombre' => 'Novedad', 'es_cuantificable' => false,
        ]);
        $this->municipal = PuntoMonitoreo::factory()->delTipo('municipal')->create(['codigo' => 'PM-M-001']);
        $this->provincial = PuntoMonitoreo::factory()->delTipo('provincial')->create(['codigo' => 'PM-P-001']);
    }

    private function evento(TipoEvento $tipo, string $fechaHora, ?PuntoMonitoreo $punto = null): Evento
    {
        return Evento::factory()->create([
            'tipo_evento_id' => $tipo->id,
            'punto_monitoreo_id' => $punto?->id,
            'usuario_id' => $this->supervisor->id,
            'fecha_hora' => $fechaHora,
        ]);
    }

    private function cargarOctubre(): void
    {
        $this->evento($this->robo, '2026-10-01 00:00:00', $this->municipal);   // sem 1, primer instante
        $this->evento($this->robo, '2026-10-03 10:00:00', $this->municipal);   // sem 1
        $this->evento($this->ruidos, '2026-10-09 22:00:00', $this->provincial); // sem 2
        $this->evento($this->ruidos, '2026-10-31 23:59:59');                    // sem 5, sin ubicación
        $this->evento($this->informativo, '2026-10-05 08:00:00');               // no cuantificable
        $this->evento($this->robo, '2026-11-01 00:00:00', $this->municipal);    // fuera del mes
        $this->evento($this->robo, '2026-09-30 23:59:59', $this->municipal);    // fuera del mes
    }

    public function test_mensual_cuenta_solo_el_mes_y_excluye_no_cuantificables(): void
    {
        $this->cargarOctubre();

        $e = app(EstadisticaService::class)->mensual(2026, 10);

        $this->assertSame('2026-10-01', $e['periodo']['desde']);
        $this->assertSame('2026-10-31', $e['periodo']['hasta']);
        $this->assertSame(4, $e['total']);
        $this->assertSame(1, $e['sin_ubicacion']);
        $this->assertSame(3, $e['con_ubicacion']);
        $this->assertSame(1, $e['no_cuantificables']);
    }

    public function test_distribucion_por_categoria_y_por_tipo(): void
    {
        $this->cargarOctubre();

        $e = app(EstadisticaService::class)->mensual(2026, 10);
        $porCategoria = collect($e['por_categoria'])->pluck('cantidad', 'clave')->all();

        $this->assertSame(['policia' => 2, 'guardia_local' => 2, 'internos' => 0], $porCategoria);
        $this->assertSame(
            [['nombre' => 'Robo', 'categoria' => 'Policía', 'cantidad' => 2],
                ['nombre' => 'Ruidos', 'categoria' => 'Guardia Local', 'cantidad' => 2]],
            $e['por_tipo'],
        );
    }

    public function test_la_ubicacion_distingue_municipal_de_provincial_y_omite_los_sin_ubicacion(): void
    {
        $this->cargarOctubre();

        $e = app(EstadisticaService::class)->mensual(2026, 10);

        $this->assertSame(
            ['municipal' => 2, 'provincial' => 1, 'alarma' => 0],
            collect($e['por_tipo_punto'])->pluck('cantidad', 'clave')->all(),
        );
        $this->assertSame(2, $e['por_punto']['puntos_con_eventos']);
        $this->assertSame('PM-M-001', $e['por_punto']['ranking'][0]['codigo']);
        $this->assertSame(2, $e['por_punto']['ranking'][0]['cantidad']);
        $this->assertSame('Municipal', $e['por_punto']['ranking'][0]['tipo_punto']);
    }

    public function test_mensual_agrupa_semana_a_semana(): void
    {
        $this->cargarOctubre();

        $evolucion = app(EstadisticaService::class)->mensual(2026, 10)['evolucion'];

        $this->assertSame(
            ['Sem 1 (1–7)', 'Sem 2 (8–14)', 'Sem 3 (15–21)', 'Sem 4 (22–28)', 'Sem 5 (29–31)'],
            array_column($evolucion, 'etiqueta'),
        );
        $this->assertSame([2, 1, 0, 0, 1], array_column($evolucion, 'total'));
        $this->assertSame(2, $evolucion[0]['policia']);
        $this->assertSame(1, $evolucion[4]['guardia_local']);
    }

    public function test_anual_agrupa_mes_a_mes(): void
    {
        $this->cargarOctubre();

        $e = app(EstadisticaService::class)->anual(2026);

        $this->assertSame(6, $e['total']);
        $this->assertCount(12, $e['evolucion']);
        $totales = array_column($e['evolucion'], 'total', 'clave');
        $this->assertSame(1, $totales['2026-09']);
        $this->assertSame(4, $totales['2026-10']);
        $this->assertSame(1, $totales['2026-11']);
        $this->assertSame(0, $totales['2026-01']);
    }

    public function test_un_periodo_sin_eventos_devuelve_ceros(): void
    {
        $e = app(EstadisticaService::class)->mensual(2026, 2);

        $this->assertSame(0, $e['total']);
        $this->assertCount(4, $e['evolucion']); // febrero 2026: 28 días
        $this->assertSame([], $e['por_punto']['ranking']);
    }

    public function test_el_administrativo_ve_las_estadisticas_del_mes_actual_por_defecto(): void
    {
        $this->cargarOctubre();

        $this->actingAs(User::factory()->create()->assignRole('Administrativo'))
            ->get(route('estadisticas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estadisticas/Index')
                ->where('filtros.periodo', 'mensual')
                ->where('filtros.mes', 10)
                ->where('estadisticas.total', 4));
    }

    public function test_se_puede_pedir_el_anual(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Administrador de sistema'))
            ->get(route('estadisticas.index', ['periodo' => 'anual', 'anio' => 2025]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('estadisticas.periodo.titulo', '2025')
                ->has('estadisticas.evolucion', 12));
    }

    public function test_el_supervisor_no_ve_estadisticas(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Supervisor'))
            ->get(route('estadisticas.index'))
            ->assertForbidden();
    }

    public function test_rechaza_un_periodo_invalido(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Administrativo'))
            ->from(route('estadisticas.index'))
            ->get(route('estadisticas.index', ['mes' => 13]))
            ->assertSessionHasErrors('mes');
    }
}
