<?php

namespace Tests\Feature\Eventos;

use App\Events\EventoRegistrado;
use App\Models\AuditoriaAcceso;
use App\Models\Errata;
use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TipoEventoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

/**
 * Regla de negocio 1: los eventos se corrigen por fe de errata, que registra
 * solo el motivo; los datos del evento quedan bloqueados.
 */
class ErrataTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private TipoEvento $robo;

    private PuntoMonitoreo $plaza;

    private Evento $evento;

    private Evento $apertura;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, TipoEventoSeeder::class]);
        $this->travelTo(Carbon::parse('2026-10-15 15:00:00'));

        $this->supervisor = User::factory()->create(['name' => 'Ana Supervisora'])->assignRole('Supervisor');
        $this->robo = TipoEvento::factory()->create(['nombre' => 'Robo', 'requiere_ubicacion' => true]);
        $this->plaza = PuntoMonitoreo::factory()->create(['codigo' => 'PM-M-001', 'nombre' => 'Plaza']);

        $this->apertura = Evento::factory()->aperturaDeTurno()->create(['fecha_hora' => '2026-10-15 06:00:00']);
        $this->evento = Evento::factory()->create([
            'tipo_evento_id' => $this->robo->id,
            'punto_monitoreo_id' => $this->plaza->id,
            'usuario_id' => $this->supervisor->id,
            'fecha_hora' => '2026-10-15 10:00:00',
            'timestamp_video' => '09:59:30',
            'descripcion' => 'Arrebato de celular.',
        ]);
    }

    private const MOTIVO = 'Se cargó mal el PM: fue en la Terminal (PM-M-002).';

    public function test_el_formulario_muestra_los_datos_vigentes(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('erratas.create', $this->evento))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Errata/Create')
                ->where('evento.hora', '10:00:00')
                ->where('evento.punto_monitoreo_id', $this->plaza->id)
                ->where('evento.descripcion', 'Arrebato de celular.'));
    }

    public function test_registra_solo_el_motivo_sin_tocar_el_evento(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), ['motivo' => self::MOTIVO])
            ->assertRedirect(route('eventos.show', $this->evento))
            ->assertSessionHas('success');

        $errata = Errata::sole();
        $this->assertSame($this->evento->id, $errata->evento_id);
        $this->assertSame($this->supervisor->id, $errata->usuario_id);
        $this->assertSame(self::MOTIVO, $errata->motivo);
        $this->assertNull($errata->campo_corregido);
        $this->assertNull($errata->valor_anterior);
        $this->assertNull($errata->valor_nuevo);

        // El evento no cambia, ni el original ni los valores vigentes.
        $this->assertSame($this->plaza->id, $this->evento->fresh()->punto_monitoreo_id);
        $this->assertSame($this->plaza->id, Evento::vigentes()->find($this->evento->id)->punto_monitoreo_id);

        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_ERRATA,
            'user_id' => $this->supervisor->id,
        ]);
    }

    public function test_los_datos_del_evento_quedan_bloqueados(): void
    {
        $otroPunto = PuntoMonitoreo::factory()->create();

        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), [
                'motivo' => self::MOTIVO,
                'punto_monitoreo_id' => $otroPunto->id,
                'descripcion' => 'Otra cosa',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull(Errata::sole()->campo_corregido);
        $this->assertSame('Arrebato de celular.', Evento::vigentes()->find($this->evento->id)->descripcion);
    }

    public function test_el_detalle_muestra_el_motivo_en_el_historial(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), ['motivo' => self::MOTIVO]);

        $this->actingAs($this->supervisor)
            ->get(route('eventos.show', $this->evento))
            ->assertInertia(fn (Assert $page) => $page
                ->where('evento.descripcion', 'Arrebato de celular.')
                ->has('evento.erratas', 1)
                ->where('evento.erratas.0.campo', null)
                ->where('evento.erratas.0.motivo', self::MOTIVO)
                ->where('evento.erratas.0.autor', 'Ana Supervisora')
                ->where('puedeCorregir', true));

        $this->actingAs($this->supervisor)
            ->get(route('eventos.index', ['tipo_evento_id' => $this->robo->id]))
            ->assertInertia(fn (Assert $page) => $page->where('eventos.data.0.corregido', true));
    }

    public function test_el_historial_muestra_las_erratas_anteriores_con_valores(): void
    {
        $otroPunto = PuntoMonitoreo::factory()->create(['codigo' => 'PM-M-002', 'nombre' => 'Terminal']);
        Errata::create([
            'evento_id' => $this->evento->id,
            'usuario_id' => $this->supervisor->id,
            'campo_corregido' => 'punto_monitoreo_id',
            'valor_anterior' => (string) $this->plaza->id,
            'valor_nuevo' => (string) $otroPunto->id,
            'motivo' => 'Errata anterior al cambio.',
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('eventos.show', $this->evento))
            ->assertInertia(fn (Assert $page) => $page
                ->where('evento.erratas.0.campo', 'Punto de monitoreo')
                ->where('evento.erratas.0.anterior', 'PM-M-001 — Plaza')
                ->where('evento.erratas.0.nuevo', 'PM-M-002 — Terminal')
                ->where('evento.punto.codigo', 'PM-M-002'));
    }

    public function test_el_motivo_es_obligatorio(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), ['motivo' => 'mal'])
            ->assertSessionHasErrors('motivo'); // mínimo 5 caracteres

        $this->assertDatabaseCount('erratas', 0);
        $this->assertSame(1, Evento::whereKeyNot($this->apertura->id)->count());
    }

    public function test_solo_el_supervisor_puede_corregir(): void
    {
        foreach (['Administrativo', 'Administrador de sistema'] as $rol) {
            $usuario = User::factory()->create()->assignRole($rol);

            $this->actingAs($usuario)
                ->get(route('erratas.create', $this->evento))
                ->assertForbidden();

            $this->actingAs($usuario)
                ->post(route('erratas.store', $this->evento), ['motivo' => self::MOTIVO])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('erratas', 0);
    }

    public function test_otro_supervisor_corrige_un_evento_y_queda_auditado(): void
    {
        $otroSupervisor = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($otroSupervisor)
            ->get(route('erratas.create', $this->evento))
            ->assertOk();

        $this->actingAs($otroSupervisor)
            ->post(route('erratas.store', $this->evento), ['motivo' => self::MOTIVO])
            ->assertRedirect(route('eventos.show', $this->evento));

        $this->assertDatabaseHas('erratas', [
            'evento_id' => $this->evento->id,
            'usuario_id' => $otroSupervisor->id,
        ]);

        $auditoria = AuditoriaAcceso::where('evento', AuditoriaAcceso::EVENTO_ERRATA)->latest('id')->firstOrFail();
        $this->assertSame($otroSupervisor->id, $auditoria->user_id);
        $this->assertStringContainsString('cargada por Ana Supervisora', $auditoria->descripcion);

        $this->actingAs($otroSupervisor)
            ->get(route('eventos.show', $this->evento))
            ->assertOk();
    }

    public function test_otro_supervisor_ve_el_evento_en_el_listado(): void
    {
        $otroSupervisor = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($otroSupervisor)
            ->get(route('eventos.index'))
            ->assertOk()
            // El evento y la Apertura de turno.
            ->assertInertia(fn (Assert $page) => $page->where('eventos.total', 2));
    }

    // --- Constancia en el libro de guardia (evento interno Errata) ----------

    public function test_la_fe_de_errata_deja_una_constancia_en_el_libro_y_se_notifica(): void
    {
        Event::fake([EventoRegistrado::class]);

        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), ['motivo' => self::MOTIVO])
            ->assertRedirect(route('eventos.show', $this->evento));

        $constancia = Evento::where('tipo_evento_id', TipoEvento::interno(TipoEvento::ERRATA))->sole();
        $this->assertSame($this->supervisor->id, $constancia->usuario_id);
        $this->assertNull($constancia->punto_monitoreo_id);
        $this->assertSame('2026-10-15 15:00:00', $constancia->fecha_hora->format('Y-m-d H:i:s'));
        $this->assertSame(
            sprintf('Errata sobre AT-2026-%04d: %s', $this->evento->id, self::MOTIVO),
            $constancia->descripcion,
        );

        Event::assertDispatched(EventoRegistrado::class, fn ($e) => $e->evento->is($constancia));
    }

    public function test_a_una_constancia_no_se_le_hace_errata(): void
    {
        $constancia = Evento::factory()->create([
            'tipo_evento_id' => TipoEvento::interno(TipoEvento::ERRATA),
            'usuario_id' => $this->supervisor->id,
        ]);

        $this->actingAs($this->supervisor)->get(route('erratas.create', $constancia))->assertForbidden();
        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $constancia), ['motivo' => self::MOTIVO])
            ->assertSessionHas('error');
        $this->actingAs($this->supervisor)
            ->get(route('eventos.show', $constancia))
            ->assertInertia(fn (Assert $page) => $page->where('puedeCorregir', false));

        $this->assertDatabaseCount('erratas', 0);
    }

    public function test_la_errata_es_inmutable(): void
    {
        $errata = Errata::create([
            'evento_id' => $this->evento->id,
            'usuario_id' => $this->supervisor->id,
            'campo_corregido' => 'descripcion',
            'valor_anterior' => 'a',
            'valor_nuevo' => 'b',
            'motivo' => 'prueba',
        ]);

        $this->expectException(LogicException::class);
        $errata->update(['motivo' => 'otro']);
    }
}
