<?php

namespace Tests\Feature\Tableros;

use App\Models\Evento;
use App\Models\GrupoInteresado;
use App\Models\Notificacion;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use App\Services\Tableros\TableroEstrategicoService;
use App\Services\Tableros\TableroOperativoService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TipoEventoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tableros: Control Operativo, Análisis Estratégico y Mapa de calor.
 */
class TablerosTest extends TestCase
{
    use RefreshDatabase;

    private TipoEvento $robo;

    private TipoEvento $ruidos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, TipoEventoSeeder::class]);
        // Martes 2026-10-20, 15:30: turno tarde.
        $this->travelTo(Carbon::parse('2026-10-20 15:30:00'));

        $this->robo = TipoEvento::factory()->delGrupo('policia')->create();
        $this->ruidos = TipoEvento::factory()->delGrupo('guardia_local')->create();
    }

    private function evento(string $fechaHora, ?TipoEvento $tipo = null, ?PuntoMonitoreo $punto = null): Evento
    {
        return Evento::factory()->create([
            'tipo_evento_id' => ($tipo ?? $this->robo)->id,
            'punto_monitoreo_id' => $punto?->id,
            'fecha_hora' => $fechaHora,
        ]);
    }

    private function operativo(): array
    {
        return app(TableroOperativoService::class)->calcular();
    }

    private function estrategico(array $filtros = []): array
    {
        return app(TableroEstrategicoService::class)->calcular($filtros);
    }

    // ---------------------------------------------------------------- Operativo

    public function test_ultima_hora_alerta_pico_si_supera_el_promedio_en_50_por_ciento(): void
    {
        // Promedio de la misma hora en los 7 días anteriores: 7 / 7 = 1.
        foreach (range(1, 7) as $d) {
            $this->evento(now()->subDays($d)->subMinutes(10)->toDateTimeString());
        }
        $this->evento(now()->subMinutes(5)->toDateTimeString());
        $this->evento(now()->subMinutes(20)->toDateTimeString());
        // Fuera de la última hora.
        $this->evento(now()->subMinutes(70)->toDateTimeString());

        $kpi = $this->operativo()['ultima_hora'];

        $this->assertSame(2, $kpi['cantidad']);
        $this->assertEquals(1.0, $kpi['promedio']);
        $this->assertEquals(100.0, $kpi['variacion']);
        $this->assertTrue($kpi['pico']);
    }

    public function test_sin_historial_no_hay_alerta_de_pico(): void
    {
        $this->evento(now()->subMinutes(5)->toDateTimeString());

        $kpi = $this->operativo()['ultima_hora'];

        $this->assertSame(1, $kpi['cantidad']);
        $this->assertNull($kpi['variacion']);
        $this->assertFalse($kpi['pico']);
    }

    public function test_turno_compara_con_la_misma_franja_y_dia_de_semanas_anteriores(): void
    {
        Evento::factory()->aperturaDeTurno()->create(['fecha_hora' => '2026-10-20 14:00:00', 'franja' => 'tarde', 'grupo_guardia' => 3]);
        $this->evento('2026-10-20 14:30:00');
        $this->evento('2026-10-20 15:00:00');

        // Martes anteriores, turno tarde (14 a 22): 4 y 2 → promedio 6 / 8 semanas.
        $this->evento('2026-10-13 14:10:00');
        $this->evento('2026-10-13 15:00:00');
        $this->evento('2026-10-13 18:00:00');
        $this->evento('2026-10-13 21:59:00');
        $this->evento('2026-10-06 16:00:00');
        $this->evento('2026-10-06 20:00:00');
        // Otra franja (noche) del martes anterior: no cuenta.
        $this->evento('2026-10-13 23:00:00');

        $turno = $this->operativo()['turno'];

        $this->assertTrue($turno['abierto']);
        $this->assertSame('Tarde', $turno['franja']);
        $this->assertSame(3, $turno['grupo_guardia']);
        $this->assertSame(2, $turno['cantidad']);
        $this->assertEquals(0.8, $turno['promedio']);
        // Hasta las 15:30 de cada martes: 14:10 y 15:00 → 2 / 8.
        $this->assertEquals(0.3, $turno['promedio_a_esta_altura']);
    }

    public function test_los_eventos_internos_no_cuantificables_no_cuentan(): void
    {
        Evento::factory()->aperturaDeTurno()->create(['fecha_hora' => now()->subMinutes(10)]);

        $t = $this->operativo();

        $this->assertSame(0, $t['ultima_hora']['cantidad']);
        $this->assertSame(0, $t['turno']['cantidad']);
        // El feed sí muestra todo lo registrado.
        $this->assertCount(1, $t['feed']);
    }

    public function test_distribucion_de_las_ultimas_24_horas_por_grupo(): void
    {
        $this->evento(now()->subHours(2)->toDateTimeString());
        $this->evento(now()->subHours(3)->toDateTimeString());
        $this->evento(now()->subHours(5)->toDateTimeString(), $this->ruidos);
        $this->evento(now()->subHours(30)->toDateTimeString(), $this->ruidos);

        $porGrupo = collect($this->operativo()['distribucion'])->keyBy('clave');

        $this->assertSame(2, $porGrupo['policia']['cantidad']);
        $this->assertEquals(66.7, $porGrupo['policia']['porcentaje']);
        $this->assertSame(1, $porGrupo['guardia_local']['cantidad']);
        $this->assertSame(0, $porGrupo['internos']['cantidad']);
    }

    public function test_notificaciones_del_dia_y_estado_en_el_feed(): void
    {
        $grupo = GrupoInteresado::create(['nombre' => 'Comisaría 1']);
        $ok = $this->evento(now()->subMinutes(30)->toDateTimeString());
        $mal = $this->evento(now()->subMinutes(20)->toDateTimeString());
        $sinNotificar = $this->evento(now()->subMinutes(10)->toDateTimeString());

        $notificar = fn (Evento $e, string $estado) => Notificacion::create([
            'evento_id' => $e->id, 'grupo_interesado_id' => $grupo->id, 'canal' => 'telegram',
            'mensaje' => 'x', 'estado' => $estado, 'intentos' => 1, 'error' => $estado === 'fallida' ? 'Timeout' : null,
        ]);
        $notificar($ok, Notificacion::ESTADO_ENVIADA);
        $notificar($ok, Notificacion::ESTADO_ENVIADA);
        $notificar($mal, Notificacion::ESTADO_ENVIADA);
        $notificar($mal, Notificacion::ESTADO_FALLIDA);
        // De ayer: no cuenta en el día.
        $viejo = $this->evento(now()->subDay()->toDateTimeString());
        $notificar($viejo, Notificacion::ESTADO_FALLIDA)->forceFill(['created_at' => now()->subDay()])->save();

        $t = $this->operativo();

        $this->assertSame(3, $t['notificaciones']['enviadas']);
        $this->assertSame(1, $t['notificaciones']['fallidas']);
        $this->assertEquals(75.0, $t['notificaciones']['efectividad']);
        $this->assertSame($mal->id, $t['notificaciones']['ultimas_fallidas'][0]['evento_id']);

        $feed = collect($t['feed'])->keyBy('id');
        $this->assertSame('enviada', $feed[$ok->id]['notificacion']);
        $this->assertSame('fallida', $feed[$mal->id]['notificacion']);
        $this->assertNull($feed[$sinNotificar->id]['notificacion']);
    }

    public function test_feed_muestra_las_ultimas_diez_ingresadas(): void
    {
        foreach (range(1, 12) as $i) {
            $this->evento(now()->subMinutes(60 - $i)->toDateTimeString());
        }

        $this->assertCount(TableroOperativoService::FEED, $this->operativo()['feed']);
    }

    public function test_inicio_de_franja_noche_de_madrugada_es_el_dia_anterior(): void
    {
        $inicio = TableroOperativoService::inicioFranja('noche', Carbon::parse('2026-10-20 02:00'));

        $this->assertSame('2026-10-19 22:00', $inicio->format('Y-m-d H:i'));
        $this->assertSame('noche', TableroOperativoService::franjaDe(Carbon::parse('2026-10-20 05:59')));
        $this->assertSame('manana', TableroOperativoService::franjaDe(Carbon::parse('2026-10-20 06:00')));
    }

    public function test_el_supervisor_ve_el_tablero_operativo_y_no_los_de_analisis(): void
    {
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($supervisor)->get(route('tableros.operativo'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tableros/Operativo')
                ->has('tablero.ultima_hora')
                ->has('tablero.feed')
                ->has('intervaloRefresco'));

        $this->actingAs($supervisor)->get(route('tableros.estrategico'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('tableros.calor'))->assertForbidden();
    }

    // -------------------------------------------------------------- Estratégico

    public function test_variacion_intermensual_e_interanual_de_un_mes_completo(): void
    {
        $this->evento('2026-09-01 10:00:00');
        $this->evento('2026-09-30 10:00:00');
        $this->evento('2026-10-05 10:00:00');
        $this->evento('2026-10-06 10:00:00');
        $this->evento('2026-10-07 10:00:00');
        $this->evento('2025-10-10 10:00:00');

        $v = $this->estrategico(['desde' => '2026-10-01', 'hasta' => '2026-10-31'])['variacion'];

        $this->assertSame(3, $v['total']);
        $this->assertSame('2026-09-01', $v['anterior']['desde']);
        $this->assertSame('2026-09-30', $v['anterior']['hasta']);
        $this->assertSame(2, $v['anterior']['total']);
        $this->assertEquals(50.0, $v['anterior']['variacion']);
        $this->assertSame(1, $v['interanual']['total']);
        $this->assertEquals(200.0, $v['interanual']['variacion']);
    }

    public function test_variacion_de_un_rango_cualquiera_usa_el_tramo_anterior_de_igual_duracion(): void
    {
        $v = $this->estrategico(['desde' => '2026-10-11', 'hasta' => '2026-10-20'])['variacion'];

        $this->assertSame(10, $v['dias']);
        $this->assertSame('2026-10-01', $v['anterior']['desde']);
        $this->assertSame('2026-10-10', $v['anterior']['hasta']);
    }

    public function test_perfil_temporal_por_dia_de_la_semana_y_hora(): void
    {
        $this->evento('2026-10-19 08:15:00'); // lunes
        $this->evento('2026-10-19 08:45:00'); // lunes
        $this->evento('2026-10-18 23:10:00'); // domingo

        $p = $this->estrategico(['desde' => '2026-10-01', 'hasta' => '2026-10-20'])['perfil_temporal'];

        $this->assertSame(2, $p['celdas'][0][8]);
        $this->assertSame(1, $p['celdas'][6][23]);
        $this->assertSame(2, $p['maximo']);
    }

    public function test_top_5_puntos_y_tendencia_diaria_por_grupo(): void
    {
        $puntos = PuntoMonitoreo::factory()->count(6)->create();
        foreach ($puntos as $i => $punto) {
            foreach (range(0, $i) as $_) {
                $this->evento('2026-10-19 10:00:00', $this->robo, $punto);
            }
        }
        $this->evento('2026-10-20 10:00:00', $this->ruidos);

        $t = $this->estrategico(['desde' => '2026-10-19', 'hasta' => '2026-10-20']);

        $this->assertCount(5, $t['top_puntos']);
        $this->assertSame($puntos[5]->id, $t['top_puntos'][0]['id']);
        $this->assertSame(6, $t['top_puntos'][0]['cantidad']);

        $this->assertCount(2, $t['tendencia']);
        $this->assertSame(21, $t['tendencia'][0]['policia']);
        $this->assertSame(0, $t['tendencia'][0]['guardia_local']);
        $this->assertSame(1, $t['tendencia'][1]['guardia_local']);
    }

    public function test_el_administrativo_ve_el_tablero_estrategico_filtrado_por_grupo(): void
    {
        $this->evento('2026-10-10 10:00:00');
        $this->evento('2026-10-10 11:00:00', $this->ruidos);

        $this->actingAs(User::factory()->create()->assignRole('Administrativo'))
            ->get(route('tableros.estrategico', ['categorias' => ['guardia_local']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tableros/Estrategico')
                ->where('tablero.periodo.desde', '2026-10-01')
                ->where('tablero.variacion.total', 1));
    }

    // ----------------------------------------------------------- Mapa de calor

    public function test_mapa_de_calor_por_defecto_muestra_todo_hasta_la_fecha(): void
    {
        $punto = PuntoMonitoreo::factory()->create();
        $this->evento('2024-03-01 10:00:00', $this->robo, $punto);
        $this->evento('2026-10-19 10:00:00', $this->ruidos, $punto);

        $this->actingAs(User::factory()->create()->assignRole('Administrativo'))
            ->get(route('tableros.calor'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tableros/Calor')
                ->where('filtros.desde', '2024-03-01')
                ->where('filtros.hasta', '2026-10-20')
                ->where('densidad.total', 2)
                ->has('densidad.puntos', 1));
    }

    public function test_mapa_de_calor_con_varios_tipos_elegidos(): void
    {
        $otro = TipoEvento::factory()->delGrupo('policia')->create();
        $punto = PuntoMonitoreo::factory()->create();
        $this->evento('2026-10-19 10:00:00', $this->robo, $punto);
        $this->evento('2026-10-19 11:00:00', $this->ruidos, $punto);
        $this->evento('2026-10-19 12:00:00', $otro, $punto);

        $this->actingAs(User::factory()->create()->assignRole('Administrativo'))
            ->get(route('tableros.calor', ['tipos' => [$this->robo->id, $this->ruidos->id]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('densidad.total', 2));
    }
}
