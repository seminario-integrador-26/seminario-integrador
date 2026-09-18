<?php

namespace Tests\Feature\Turnos;

use App\Models\AuditoriaAcceso;
use App\Models\Evento;
use App\Models\TipoEvento;
use App\Models\Turno;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-025: apertura y cierre de turno de guardia.
 */
class TurnoTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-17 08:30:00'));

        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
    }

    public function test_el_supervisor_ve_la_pantalla_de_turnos(): void
    {
        Turno::factory()->create(['supervisor_id' => $this->supervisor->id]);

        $this->actingAs($this->supervisor)
            ->get(route('turnos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Turnos/Index')
                ->has('turnos.data', 1)
                ->has('turnoActivo'));
    }

    public function test_solo_el_supervisor_accede_a_los_turnos(): void
    {
        foreach (['Administrativo', 'Administrador de sistema'] as $rol) {
            $usuario = User::factory()->create()->assignRole($rol);

            $this->actingAs($usuario)->get(route('turnos.index'))->assertForbidden();
            $this->actingAs($usuario)
                ->post(route('turnos.store'), ['personal_presente' => ['Juan']])
                ->assertForbidden();
        }
    }

    public function test_un_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('turnos.index'))->assertRedirect(route('login'));
    }

    public function test_abre_un_turno_con_el_personal_presente(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('turnos.store'), [
                'personal_presente' => ['Juan Pérez', 'María López'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('turnos.index'));

        $turno = Turno::sole();

        $this->assertSame($this->supervisor->id, $turno->supervisor_id);
        $this->assertSame('2026-09-17', $turno->fecha->format('Y-m-d'));
        $this->assertSame('08:30:00', $turno->hora_inicio);
        $this->assertSame(['Juan Pérez', 'María López'], $turno->personal_presente);
        $this->assertTrue($turno->estaAbierto());

        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_TURNO_APERTURA,
            'user_id' => $this->supervisor->id,
            'rol' => 'Supervisor',
        ]);
    }

    public function test_el_personal_presente_es_obligatorio(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('turnos.store'), ['personal_presente' => []])
            ->assertSessionHasErrors('personal_presente');

        $this->assertSame(0, Turno::count());
    }

    public function test_descarta_nombres_vacios_del_personal(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('turnos.store'), [
                'personal_presente' => ['  Ana  ', '', '   '],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Ana'], Turno::sole()->personal_presente);
    }

    public function test_no_puede_abrir_dos_turnos_a_la_vez(): void
    {
        Turno::factory()->create(['supervisor_id' => $this->supervisor->id]);

        $this->actingAs($this->supervisor)
            ->post(route('turnos.store'), ['personal_presente' => ['Juan']])
            ->assertSessionHasErrors('turno');

        $this->assertSame(1, Turno::count());
    }

    public function test_dos_supervisores_pueden_tener_turno_abierto_simultaneo(): void
    {
        Turno::factory()->create(['supervisor_id' => $this->supervisor->id]);
        $otro = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($otro)
            ->post(route('turnos.store'), ['personal_presente' => ['Otro']])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Turno::abierto()->count());
    }

    public function test_cierra_su_turno_con_las_novedades(): void
    {
        $turno = Turno::factory()->create(['supervisor_id' => $this->supervisor->id]);
        $this->travelTo(Carbon::parse('2026-09-17 16:45:00'));

        $this->actingAs($this->supervisor)
            ->patch(route('turnos.cerrar', $turno), [
                'novedades_pendientes' => 'Cámara 12 intermitente.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('turnos.index'));

        $turno->refresh();

        $this->assertFalse($turno->estaAbierto());
        $this->assertSame('16:45:00', $turno->hora_fin);
        $this->assertSame('2026-09-17', $turno->fecha_fin->format('Y-m-d'));
        $this->assertSame('Cámara 12 intermitente.', $turno->novedades_pendientes);

        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_TURNO_CIERRE,
            'user_id' => $this->supervisor->id,
        ]);
    }

    public function test_no_puede_cerrar_el_turno_de_otro_supervisor(): void
    {
        $otro = User::factory()->create()->assignRole('Supervisor');
        $turno = Turno::factory()->create(['supervisor_id' => $otro->id]);

        $this->actingAs($this->supervisor)
            ->patch(route('turnos.cerrar', $turno), [])
            ->assertForbidden();

        $this->assertTrue($turno->refresh()->estaAbierto());
    }

    public function test_no_puede_cerrar_un_turno_ya_cerrado(): void
    {
        $turno = Turno::factory()->cerrado()->create(['supervisor_id' => $this->supervisor->id]);

        $this->actingAs($this->supervisor)
            ->patch(route('turnos.cerrar', $turno), [])
            ->assertSessionHasErrors('turno');
    }

    public function test_el_evento_se_asocia_al_turno_del_supervisor_que_lo_carga(): void
    {
        // Otro supervisor abrió un turno después: el evento NO debe caer en ese.
        $propio = Turno::factory()->create(['supervisor_id' => $this->supervisor->id]);
        $otro = User::factory()->create()->assignRole('Supervisor');
        Turno::factory()->create(['supervisor_id' => $otro->id]);

        $tipo = TipoEvento::factory()->sinUbicacion()->create();

        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), [
                'tipo_evento_id' => $tipo->id,
                'fecha' => '2026-09-17',
                'hora' => '08:00:00',
                'punto_monitoreo_id' => null,
                'timestamp_video' => '08:00:00',
                'descripcion' => 'Test',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($propio->id, Evento::sole()->turno_id);
    }
}
