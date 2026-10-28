<?php

namespace Tests\Feature\Eventos;

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
 * US-010: detalle completo de un evento (permiso eventos.consultar).
 */
class DetalleEventoTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->supervisor = User::factory()->create(['name' => 'Ana Supervisora'])->assignRole('Supervisor');
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

    public function test_muestra_todos_los_datos_de_un_evento_con_ubicacion(): void
    {
        $tipo = TipoEvento::factory()->delGrupo('policia')->create(['nombre' => 'Robo / hurto']);
        $punto = PuntoMonitoreo::factory()->delTipo('provincial')->create([
            'codigo' => 'PM-P-010',
            'nombre' => 'Ruta 9 y Av. Sabattini',
        ]);
        $evento = $this->evento([
            'tipo_evento_id' => $tipo->id,
            'punto_monitoreo_id' => $punto->id,
            'fecha_hora' => '2026-10-10 22:15:30',
            'timestamp_video' => '22:15:12',
            'descripcion' => 'Sustracción de un celular.',
        ]);

        $this->actingAs(User::factory()->create()->assignRole('Administrativo'))
            ->get(route('eventos.show', $evento))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Eventos/Show')
                ->where('evento.id', $evento->id)
                ->where('evento.tipo', 'Robo / hurto')
                ->where('evento.categoria', 'Policía')
                ->where('evento.fecha', '10/10/2026')
                ->where('evento.hora', '22:15:30')
                ->missing('evento.timestamp_video')
                ->where('evento.descripcion', 'Sustracción de un celular.')
                ->where('evento.punto.codigo', 'PM-P-010')
                ->where('evento.punto.tipo', 'Provincial')
                ->where('evento.autor', 'Ana Supervisora')
                ->where('evento.informativo', false)
                ->has('evento.registrado')
                ->has('evento.notificaciones', 0));
    }

    public function test_un_evento_sin_ubicacion_lo_indica(): void
    {
        $informativo = TipoEvento::factory()->sinUbicacion()->delGrupo('internos')->create([
            'es_cuantificable' => false,
        ]);
        $evento = $this->evento(['tipo_evento_id' => $informativo->id]);

        $this->actingAs($this->supervisor)
            ->get(route('eventos.show', $evento))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('evento.punto', null)
                ->where('evento.informativo', true)
                ->where('evento.cuantificable', false));
    }

    public function test_un_usuario_sin_permiso_no_ve_el_detalle(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('eventos.show', $this->evento()))
            ->assertForbidden();
    }

    public function test_un_evento_inexistente_da_404(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('eventos.show', 9999))
            ->assertNotFound();
    }

    public function test_la_ruta_de_registro_no_se_confunde_con_el_detalle(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('eventos.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Eventos/Create'));
    }
}
