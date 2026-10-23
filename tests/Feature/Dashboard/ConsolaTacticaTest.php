<?php

namespace Tests\Feature\Dashboard;

use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TipoEventoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Consola táctica (Dashboard): turno vigente, feed de eventos y cartografía.
 * El turno vigente es la última Apertura de turno sin Cierre posterior.
 */
class ConsolaTacticaTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, TipoEventoSeeder::class]);
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));

        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function evento(string $fechaHora, array $atributos = []): Evento
    {
        return Evento::factory()->create([
            'usuario_id' => $this->supervisor->id,
            'fecha_hora' => $fechaHora,
            ...$atributos,
        ]);
    }

    private function interno(string $tipo, string $fechaHora): Evento
    {
        return $this->evento($fechaHora, ['tipo_evento_id' => TipoEvento::interno($tipo)]);
    }

    public function test_el_invitado_no_accede_al_panel(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_el_panel_muestra_la_apertura_vigente(): void
    {
        $apertura = $this->interno(TipoEvento::APERTURA_TURNO, '2026-10-23 06:00:00');

        $this->actingAs($this->supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('turno.id', $apertura->id)
                ->where('turno.hora_inicio', '06:00')
                ->where('turno.supervisor', $this->supervisor->name)
                ->where('turno.transcurrido', '06h 00m')
            );
    }

    public function test_con_un_cierre_posterior_no_hay_turno_vigente(): void
    {
        $this->interno(TipoEvento::APERTURA_TURNO, '2026-10-23 06:00:00');
        $this->interno(TipoEvento::CIERRE_TURNO, '2026-10-23 11:00:00');

        $this->actingAs($this->supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('turno', null));
    }

    public function test_el_feed_trae_los_eventos_desde_la_apertura_vigente(): void
    {
        $this->interno(TipoEvento::APERTURA_TURNO, '2026-10-22 22:00:00');
        $this->evento('2026-10-23 01:00:00');
        $this->interno(TipoEvento::CIERRE_TURNO, '2026-10-23 05:59:00');

        $apertura = $this->interno(TipoEvento::APERTURA_TURNO, '2026-10-23 06:00:00');
        $delTurno = $this->evento('2026-10-23 08:00:00');

        $this->actingAs($this->supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('eventos', 2)
                ->where('eventos.0.id', $delTurno->id)
                ->where('eventos.1.id', $apertura->id)
                ->missing('eventos.0.turno_id')
            );
    }

    public function test_sin_turno_vigente_el_feed_trae_los_ultimos_eventos(): void
    {
        $this->evento('2026-10-23 08:00:00');
        $this->evento('2026-10-23 09:00:00');

        $this->actingAs($this->supervisor)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('turno', null)
                ->has('eventos', 2));
    }

    /**
     * RN-04: los tipos no cuantificables no entran en estadísticas.
     */
    public function test_los_no_cuantificables_quedan_fuera_del_conteo(): void
    {
        $this->interno(TipoEvento::APERTURA_TURNO, '2026-10-23 06:00:00');

        $cuantificable = TipoEvento::factory()->delGrupo('policia')->create(['es_cuantificable' => true]);
        $this->evento('2026-10-23 08:00:00', ['tipo_evento_id' => $cuantificable->id]);
        $this->interno('Nota', '2026-10-23 09:00:00');

        $this->actingAs($this->supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('eventos', 3)
                ->where('conteoPorCategoria.Policía', 1)
                ->where('conteoPorCategoria.Internos', 0)
            );
    }

    public function test_el_panel_expone_los_puntos_de_monitoreo_para_el_mapa(): void
    {
        PuntoMonitoreo::factory()->delTipo('municipal')->create();
        PuntoMonitoreo::factory()->count(2)->delTipo('provincial')->create();

        $this->actingAs($this->supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('puntosMonitoreo', 3)
                ->where('resumenPuntos.total', 3)
                ->where('resumenPuntos.por_tipo.municipal', 1)
                ->where('resumenPuntos.por_tipo.provincial', 2)
            );
    }
}
