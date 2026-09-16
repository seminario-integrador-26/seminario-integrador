<?php

namespace Tests\Feature\Dashboard;

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
 * Consola táctica (Dashboard): turno vigente, feed de eventos y cartografía.
 */
class ConsolaTacticaTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
    }

    private function turnoAbierto(): Turno
    {
        return Turno::factory()->create([
            'supervisor_id' => $this->supervisor->id,
            'hora_fin' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function evento(Turno $turno, array $atributos = []): Evento
    {
        return Evento::factory()->create([
            'turno_id' => $turno->id,
            'usuario_id' => $this->supervisor->id,
            ...$atributos,
        ]);
    }

    public function test_el_invitado_no_accede_al_panel(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_el_panel_muestra_el_turno_vigente(): void
    {
        $turno = $this->turnoAbierto();

        $this->actingAs($this->supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('turno.id', $turno->id)
                ->where('turno.supervisor', $this->supervisor->name)
            );
    }

    public function test_sin_turno_abierto_el_turno_viene_nulo(): void
    {
        Turno::factory()->create([
            'supervisor_id' => $this->supervisor->id,
            'hora_fin' => '06:00:00',
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('turno', null));
    }

    public function test_el_feed_trae_solo_los_eventos_del_turno_vigente(): void
    {
        $turnoViejo = Turno::factory()->create([
            'supervisor_id' => $this->supervisor->id,
            'hora_fin' => '06:00:00',
        ]);
        $this->evento($turnoViejo);

        $turno = $this->turnoAbierto();
        $delTurno = $this->evento($turno);

        $this->actingAs($this->supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('eventos', 1)
                ->where('eventos.0.id', $delTurno->id)
            );
    }

    /**
     * RN-04: los tipos no cuantificables (Informativos) no entran en estadísticas.
     */
    public function test_los_no_cuantificables_quedan_fuera_del_conteo(): void
    {
        $turno = $this->turnoAbierto();

        $prevencion = TipoEvento::factory()->create([
            'categoria' => 'prevencion',
            'es_cuantificable' => true,
        ]);
        $informativo = TipoEvento::factory()->create([
            'categoria' => 'informativo',
            'es_cuantificable' => false,
        ]);

        $this->evento($turno, ['tipo_evento_id' => $prevencion->id]);
        $this->evento($turno, ['tipo_evento_id' => $informativo->id]);

        $this->actingAs($this->supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('eventos', 2)
                ->where('conteoPorCategoria.Prevención', 1)
                ->where('conteoPorCategoria.Informativo', 0)
            );
    }

    public function test_el_panel_expone_los_puntos_de_monitoreo_para_el_mapa(): void
    {
        $this->turnoAbierto();

        PuntoMonitoreo::factory()->create(['jurisdiccion' => 'municipal']);
        PuntoMonitoreo::factory()->count(2)->create(['jurisdiccion' => 'provincial']);

        $this->actingAs($this->supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('puntosMonitoreo', 3)
                ->where('resumenPuntos.total', 3)
                ->where('resumenPuntos.por_jurisdiccion.municipal', 1)
                ->where('resumenPuntos.por_jurisdiccion.provincial', 2)
            );
    }
}
