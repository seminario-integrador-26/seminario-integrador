<?php

namespace Tests\Feature\Eventos;

use App\Models\Barrio;
use App\Models\Cuadrante;
use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * CU04: consulta de eventos registrados (permiso eventos.consultar).
 */
class ConsultaEventosTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
    }

    private function usuarioConRol(string $rol): User
    {
        return User::factory()->create()->assignRole($rol);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function evento(array $atributos = []): Evento
    {
        return Evento::factory()->create([
            'usuario_id' => $this->supervisor->id,
            ...$atributos,
        ]);
    }

    public function test_el_administrativo_ve_el_listado_de_eventos(): void
    {
        $this->evento();
        $this->evento();

        $this->actingAs($this->usuarioConRol('Administrativo'))
            ->get(route('eventos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Eventos/Index')
                ->has('eventos.data', 2)
                ->has('eventos.data.0', fn (Assert $e) => $e
                    ->hasAll(['id', 'fecha_hora', 'tipo', 'categoria', 'informativo', 'punto', 'timestamp_video', 'descripcion', 'autor', 'notificaciones', 'corregido'])));
    }

    public function test_el_supervisor_tambien_consulta_eventos(): void
    {
        $this->actingAs($this->usuarioConRol('Supervisor'))
            ->get(route('eventos.index'))
            ->assertOk();
    }

    public function test_un_usuario_sin_permiso_no_consulta_el_listado(): void
    {
        // Sin el permiso eventos.consultar (usuario sin rol) -> 403.
        $this->actingAs(User::factory()->create())
            ->get(route('eventos.index'))
            ->assertForbidden();
    }

    public function test_un_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('eventos.index'))->assertRedirect(route('login'));
    }

    public function test_los_eventos_se_listan_del_mas_reciente_al_mas_antiguo(): void
    {
        $viejo = $this->evento(['fecha_hora' => '2026-09-01 10:00:00']);
        $nuevo = $this->evento(['fecha_hora' => '2026-09-10 10:00:00']);

        $this->actingAs($this->usuarioConRol('Administrativo'))
            ->get(route('eventos.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('eventos.data.0.id', $nuevo->id)
                ->where('eventos.data.1.id', $viejo->id));
    }

    public function test_filtra_por_tipo_categoria_y_punto(): void
    {
        $robo = TipoEvento::factory()->delGrupo('policia')->create();
        $ruido = TipoEvento::factory()->delGrupo('guardia_local')->create();
        $punto = PuntoMonitoreo::factory()->create();

        $conPunto = $this->evento(['tipo_evento_id' => $robo->id, 'punto_monitoreo_id' => $punto->id, 'timestamp_video' => '10:00:00']);
        $this->evento(['tipo_evento_id' => $robo->id]);
        $this->evento(['tipo_evento_id' => $ruido->id]);

        $admin = $this->usuarioConRol('Administrativo');

        $this->actingAs($admin)
            ->get(route('eventos.index', ['tipo_evento_id' => $robo->id]))
            ->assertInertia(fn (Assert $page) => $page->has('eventos.data', 2));

        $this->actingAs($admin)
            ->get(route('eventos.index', ['categorias' => ['guardia_local']]))
            ->assertInertia(fn (Assert $page) => $page->has('eventos.data', 1));

        $this->actingAs($admin)
            ->get(route('eventos.index', ['punto_monitoreo_id' => $punto->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('eventos.data', 1)
                ->where('eventos.data.0.id', $conPunto->id)
                ->where('filtros.punto_monitoreo_id', (string) $punto->id));
    }

    public function test_filtra_por_rango_de_fechas_incluyendo_el_dia_hasta(): void
    {
        $this->evento(['fecha_hora' => '2026-09-01 23:59:00']);
        $dentro = $this->evento(['fecha_hora' => '2026-09-05 08:00:00']);
        $borde = $this->evento(['fecha_hora' => '2026-09-07 23:30:00']);
        $this->evento(['fecha_hora' => '2026-09-08 00:00:00']);

        $this->actingAs($this->usuarioConRol('Administrativo'))
            ->get(route('eventos.index', ['desde' => '2026-09-02', 'hasta' => '2026-09-07']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('eventos.data', 2)
                ->where('eventos.data.0.id', $borde->id)
                ->where('eventos.data.1.id', $dentro->id));
    }

    public function test_rechaza_un_rango_de_fechas_invertido(): void
    {
        $this->actingAs($this->usuarioConRol('Administrativo'))
            ->from(route('eventos.index'))
            ->get(route('eventos.index', ['desde' => '2026-09-10', 'hasta' => '2026-09-01']))
            ->assertSessionHasErrors('hasta');
    }

    // --- US-008: filtros combinables --------------------------------------

    /**
     * @return list<int>
     */
    private function idsListados(array $parametros): array
    {
        $ids = [];

        $this->actingAs($this->usuarioConRol('Administrativo'))
            ->get(route('eventos.index', $parametros))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$ids) {
                $ids = collect($page->toArray()['props']['eventos']['data'])->pluck('id')->sort()->values()->all();
            });

        return $ids;
    }

    public function test_filtra_por_varias_categorias_a_la_vez(): void
    {
        $policia = $this->evento(['tipo_evento_id' => TipoEvento::factory()->delGrupo('policia')->create()->id]);
        $oficio = $this->evento(['tipo_evento_id' => TipoEvento::factory()->delGrupo('internos')->create()->id]);
        $this->evento(['tipo_evento_id' => TipoEvento::factory()->delGrupo('guardia_local')->create()->id]);

        $this->assertSame(
            [$policia->id, $oficio->id],
            $this->idsListados(['categorias' => ['policia', 'internos']]),
        );
    }

    public function test_filtra_por_franja_horaria_incluyendo_el_minuto_final(): void
    {
        $this->evento(['fecha_hora' => '2026-09-10 07:59:59']);
        $adentro = $this->evento(['fecha_hora' => '2026-09-10 08:00:00']);
        $borde = $this->evento(['fecha_hora' => '2026-09-11 12:00:45']);
        $this->evento(['fecha_hora' => '2026-09-11 12:01:00']);

        $this->assertSame(
            [$adentro->id, $borde->id],
            $this->idsListados(['hora_desde' => '08:00', 'hora_hasta' => '12:00']),
        );
    }

    public function test_una_franja_horaria_puede_cruzar_la_medianoche(): void
    {
        $noche = $this->evento(['fecha_hora' => '2026-09-10 23:30:00']);
        $madrugada = $this->evento(['fecha_hora' => '2026-09-11 05:10:00']);
        $this->evento(['fecha_hora' => '2026-09-11 14:00:00']);

        $this->assertSame(
            [$noche->id, $madrugada->id],
            $this->idsListados(['hora_desde' => '22:00', 'hora_hasta' => '06:00']),
        );
    }

    public function test_busca_texto_en_la_descripcion_sin_distinguir_mayusculas(): void
    {
        $moto = $this->evento(['descripcion' => 'Dos personas en una MOTO sin patente.']);
        $this->evento(['descripcion' => 'Ruidos molestos en la plaza.']);

        $this->assertSame([$moto->id], $this->idsListados(['texto' => 'moto']));
    }

    public function test_el_texto_buscado_no_se_interpreta_como_comodin(): void
    {
        $this->evento(['descripcion' => 'Sin porcentaje']);
        $conPorcentaje = $this->evento(['descripcion' => 'Subió un 50% el tránsito']);

        $this->assertSame([$conPorcentaje->id], $this->idsListados(['texto' => '%']));
    }

    public function test_filtra_por_tipo_de_punto(): void
    {
        $municipal = $this->evento(['punto_monitoreo_id' => PuntoMonitoreo::factory()->delTipo('municipal')->create()->id]);
        $this->evento(['punto_monitoreo_id' => PuntoMonitoreo::factory()->delTipo('provincial')->create()->id]);
        $this->evento();

        $this->assertSame([$municipal->id], $this->idsListados(['tipo_punto' => 'municipal']));
    }

    public function test_filtra_por_barrio_y_cuadrante_del_punto(): void
    {
        $centro = Barrio::create(['nombre' => 'Centro']);
        $norte = Cuadrante::create(['nombre' => 'Norte']);
        $enCentro = $this->evento(['punto_monitoreo_id' => PuntoMonitoreo::factory()->create(['barrio_id' => $centro->id])->id]);
        $enNorte = $this->evento(['punto_monitoreo_id' => PuntoMonitoreo::factory()->create(['cuadrante_id' => $norte->id])->id]);
        $this->evento(['punto_monitoreo_id' => PuntoMonitoreo::factory()->create()->id]);

        $this->assertSame([$enCentro->id], $this->idsListados(['barrio_id' => $centro->id]));
        $this->assertSame([$enNorte->id], $this->idsListados(['cuadrante_id' => $norte->id]));
    }

    // --- US-011: informativos y sin ubicación ---------------------------------

    public function test_filtra_eventos_informativos_y_sin_ubicacion(): void
    {
        $cuantificable = TipoEvento::factory()->create();
        $informativo = TipoEvento::factory()->sinUbicacion()->create(['es_cuantificable' => false]);
        $punto = PuntoMonitoreo::factory()->create();

        $conUbicacion = $this->evento(['tipo_evento_id' => $cuantificable->id, 'punto_monitoreo_id' => $punto->id]);
        $sinUbicacion = $this->evento(['tipo_evento_id' => $cuantificable->id, 'punto_monitoreo_id' => null]);
        $infoSinUbicacion = $this->evento(['tipo_evento_id' => $informativo->id, 'punto_monitoreo_id' => null]);
        $infoConUbicacion = $this->evento(['tipo_evento_id' => $informativo->id, 'punto_monitoreo_id' => $punto->id]);

        $this->assertSame([$conUbicacion->id, $infoConUbicacion->id], $this->idsListados(['alcance' => 'con_ubicacion']));
        $this->assertSame([$sinUbicacion->id, $infoSinUbicacion->id], $this->idsListados(['alcance' => 'sin_ubicacion']));
        $this->assertSame([$infoSinUbicacion->id, $infoConUbicacion->id], $this->idsListados(['alcance' => 'informativos']));
        $this->assertSame([$conUbicacion->id, $sinUbicacion->id], $this->idsListados(['alcance' => 'sin_informativos']));
        $this->assertCount(4, $this->idsListados([]));
    }

    public function test_rechaza_un_alcance_desconocido(): void
    {
        $this->actingAs($this->usuarioConRol('Administrativo'))
            ->get(route('eventos.index', ['alcance' => 'todo']))
            ->assertSessionHasErrors('alcance');
    }

    public function test_la_tabla_marca_los_eventos_informativos(): void
    {
        $this->evento(['tipo_evento_id' => TipoEvento::factory()->create(['es_cuantificable' => false])->id]);

        $this->actingAs($this->usuarioConRol('Administrativo'))
            ->get(route('eventos.index'))
            ->assertInertia(fn (Assert $page) => $page->where('eventos.data.0.informativo', true));
    }

    public function test_la_exportacion_respeta_el_filtro_de_informativos(): void
    {
        $this->evento(['tipo_evento_id' => TipoEvento::factory()->create()->id]);
        $this->evento(['tipo_evento_id' => TipoEvento::factory()->create(['es_cuantificable' => false])->id]);

        $this->actingAs($this->usuarioConRol('Administrativo'))
            ->get(route('reportes.eventos.planilla', ['formato' => 'xlsx', 'alcance' => 'informativos']))
            ->assertOk()
            ->assertHeader('X-Cantidad-Eventos', '1');
    }

    public function test_los_filtros_se_combinan(): void
    {
        $tipo = TipoEvento::factory()->delGrupo('policia')->create();
        $buscado = $this->evento(['tipo_evento_id' => $tipo->id, 'fecha_hora' => '2026-09-10 09:00:00', 'descripcion' => 'Moto sospechosa']);
        $this->evento(['tipo_evento_id' => $tipo->id, 'fecha_hora' => '2026-09-10 20:00:00', 'descripcion' => 'Moto sospechosa']);
        $this->evento(['tipo_evento_id' => $tipo->id, 'fecha_hora' => '2026-09-10 09:30:00', 'descripcion' => 'Auto mal estacionado']);

        $this->assertSame([$buscado->id], $this->idsListados([
            'categorias' => ['policia'],
            'hora_desde' => '08:00',
            'hora_hasta' => '10:00',
            'texto' => 'moto',
        ]));
    }

    // --- US-009: orden por columna -------------------------------------------

    public function test_ordena_por_columna_en_ambas_direcciones(): void
    {
        $zeta = $this->evento(['tipo_evento_id' => TipoEvento::factory()->create(['nombre' => 'Zona liberada'])->id]);
        $alfa = $this->evento(['tipo_evento_id' => TipoEvento::factory()->create(['nombre' => 'Accidente'])->id]);

        $admin = $this->usuarioConRol('Administrativo');

        $this->actingAs($admin)
            ->get(route('eventos.index', ['orden' => 'tipo', 'direccion' => 'asc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('eventos.data.0.id', $alfa->id)
                ->where('eventos.data.1.id', $zeta->id));

        $this->actingAs($admin)
            ->get(route('eventos.index', ['orden' => 'tipo', 'direccion' => 'desc']))
            ->assertInertia(fn (Assert $page) => $page->where('eventos.data.0.id', $zeta->id));

        $this->actingAs($admin)
            ->get(route('eventos.index', ['orden' => 'id', 'direccion' => 'asc']))
            ->assertInertia(fn (Assert $page) => $page->where('eventos.data.0.id', $zeta->id));
    }

    public function test_rechaza_un_orden_desconocido(): void
    {
        $this->actingAs($this->usuarioConRol('Administrativo'))
            ->from(route('eventos.index'))
            ->get(route('eventos.index', ['orden' => 'password']))
            ->assertSessionHasErrors('orden');
    }

    // --- Alcance: todos ven el libro completo (2026-10-23) ------------------

    public function test_todos_los_roles_ven_los_eventos_de_cualquier_supervisor(): void
    {
        $this->evento();
        $otro = $this->usuarioConRol('Supervisor');
        $this->evento(['usuario_id' => $otro->id]);

        foreach (['Supervisor', 'Administrativo', 'Administrador de sistema'] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->get(route('eventos.index'))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('eventos.data', 2)
                    ->missing('alcanceTurno'));
        }
    }

    public function test_el_supervisor_abre_el_detalle_de_un_evento_ajeno(): void
    {
        $ajeno = $this->evento(['usuario_id' => $this->usuarioConRol('Supervisor')->id]);

        $this->actingAs($this->supervisor)
            ->get(route('eventos.show', $ajeno))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('puedeCorregir', true));
    }
}
