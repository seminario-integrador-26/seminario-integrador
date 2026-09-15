<?php

namespace Tests\Feature\Eventos;

use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\Turno;
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

    private Turno $turno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $supervisor = User::factory()->create()->assignRole('Supervisor');
        $this->turno = Turno::factory()->create(['supervisor_id' => $supervisor->id]);
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
            'turno_id' => $this->turno->id,
            'usuario_id' => $this->turno->supervisor_id,
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
                    ->hasAll(['id', 'fecha_hora', 'tipo', 'categoria', 'punto', 'timestamp_video', 'descripcion', 'autor', 'turno_id'])));
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
        $robo = TipoEvento::factory()->create(['categoria' => 'seguridad_publica']);
        $ruido = TipoEvento::factory()->create(['categoria' => 'convivencia_urbana']);
        $punto = PuntoMonitoreo::factory()->create();

        $conPunto = $this->evento(['tipo_evento_id' => $robo->id, 'punto_monitoreo_id' => $punto->id, 'timestamp_video' => '10:00:00']);
        $this->evento(['tipo_evento_id' => $robo->id]);
        $this->evento(['tipo_evento_id' => $ruido->id]);

        $admin = $this->usuarioConRol('Administrativo');

        $this->actingAs($admin)
            ->get(route('eventos.index', ['tipo_evento_id' => $robo->id]))
            ->assertInertia(fn (Assert $page) => $page->has('eventos.data', 2));

        $this->actingAs($admin)
            ->get(route('eventos.index', ['categoria' => 'convivencia_urbana']))
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
}
