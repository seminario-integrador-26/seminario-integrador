<?php

namespace Tests\Feature\Eventos;

use App\Events\EventoRegistrado;
use App\Models\Ampliacion;
use App\Models\AuditoriaAcceso;
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
 * Ampliación: suma información posterior a una novedad sin tocarla (regla de
 * negocio 1) y deja constancia en el libro de guardia, como la fe de errata.
 */
class AmpliacionTest extends TestCase
{
    use RefreshDatabase;

    private const DETALLE = 'La persona fue aprehendida por el móvil 12 en Bv. España y Santa Fe.';

    private User $supervisor;

    private Evento $evento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, TipoEventoSeeder::class]);
        $this->travelTo(Carbon::parse('2026-11-11 15:00:00'));

        $this->supervisor = User::factory()->create(['name' => 'Ana Supervisora'])->assignRole('Supervisor');
        $robo = TipoEvento::factory()->create(['nombre' => 'Robo', 'requiere_ubicacion' => true]);
        $plaza = PuntoMonitoreo::factory()->create(['codigo' => 'PM-M-001', 'nombre' => 'Plaza']);

        Evento::factory()->aperturaDeTurno()->create(['fecha_hora' => '2026-11-11 06:00:00']);
        $this->evento = Evento::factory()->create([
            'tipo_evento_id' => $robo->id,
            'punto_monitoreo_id' => $plaza->id,
            'usuario_id' => $this->supervisor->id,
            'fecha_hora' => '2026-11-11 10:00:00',
            'descripcion' => 'Arrebato de celular.',
        ]);
    }

    public function test_el_formulario_muestra_la_novedad_bloqueada(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('ampliaciones.create', $this->evento))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ampliacion/Create')
                ->where('evento.hora', '10:00:00')
                ->where('evento.descripcion', 'Arrebato de celular.'));
    }

    public function test_registra_la_ampliacion_sin_tocar_la_novedad(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('ampliaciones.store', $this->evento), ['detalle' => self::DETALLE, 'descripcion' => 'Otra cosa'])
            ->assertRedirect(route('eventos.show', $this->evento))
            ->assertSessionHas('success');

        $ampliacion = Ampliacion::sole();
        $this->assertSame($this->evento->id, $ampliacion->evento_id);
        $this->assertSame($this->supervisor->id, $ampliacion->usuario_id);
        $this->assertSame(self::DETALLE, $ampliacion->detalle);

        $this->assertSame('Arrebato de celular.', $this->evento->fresh()->descripcion);
        $this->assertSame('Arrebato de celular.', Evento::vigentes()->find($this->evento->id)->descripcion);
        $this->assertDatabaseCount('erratas', 0);

        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_AMPLIACION,
            'user_id' => $this->supervisor->id,
        ]);
    }

    public function test_deja_una_constancia_en_el_libro_y_se_notifica(): void
    {
        Event::fake([EventoRegistrado::class]);

        $this->actingAs($this->supervisor)
            ->post(route('ampliaciones.store', $this->evento), ['detalle' => self::DETALLE]);

        $constancia = Evento::where('tipo_evento_id', TipoEvento::interno(TipoEvento::AMPLIACION))->sole();
        $this->assertSame($this->supervisor->id, $constancia->usuario_id);
        $this->assertNull($constancia->punto_monitoreo_id);
        $this->assertSame('2026-11-11 15:00:00', $constancia->fecha_hora->format('Y-m-d H:i:s'));
        $this->assertSame(
            sprintf('Ampliación sobre AT-2026-%04d: %s', $this->evento->id, self::DETALLE),
            $constancia->descripcion,
        );

        Event::assertDispatched(EventoRegistrado::class, fn ($e) => $e->evento->is($constancia));
    }

    public function test_el_detalle_muestra_las_ampliaciones(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('ampliaciones.store', $this->evento), ['detalle' => self::DETALLE]);

        $this->actingAs($this->supervisor)
            ->get(route('eventos.show', $this->evento))
            ->assertInertia(fn (Assert $page) => $page
                ->has('evento.ampliaciones', 1)
                ->where('evento.ampliaciones.0.detalle', self::DETALLE)
                ->where('evento.ampliaciones.0.autor', 'Ana Supervisora')
                ->where('puedeAmpliar', true));
    }

    public function test_el_detalle_es_obligatorio(): void
    {
        foreach (['', 'algo'] as $detalle) {
            $this->actingAs($this->supervisor)
                ->post(route('ampliaciones.store', $this->evento), ['detalle' => $detalle])
                ->assertSessionHasErrors('detalle');
        }

        $this->assertDatabaseCount('ampliaciones', 0);
    }

    public function test_solo_el_supervisor_puede_ampliar(): void
    {
        foreach (['Administrativo', 'Administrador de sistema'] as $rol) {
            $usuario = User::factory()->create()->assignRole($rol);

            $this->actingAs($usuario)->get(route('ampliaciones.create', $this->evento))->assertForbidden();
            $this->actingAs($usuario)
                ->post(route('ampliaciones.store', $this->evento), ['detalle' => self::DETALLE])
                ->assertForbidden();
            $this->actingAs($usuario)
                ->get(route('eventos.show', $this->evento))
                ->assertInertia(fn (Assert $page) => $page->where('puedeAmpliar', false));
        }

        $this->assertDatabaseCount('ampliaciones', 0);
    }

    public function test_sin_turno_abierto_no_se_amplia(): void
    {
        Evento::factory()->create([
            'tipo_evento_id' => TipoEvento::interno(TipoEvento::CIERRE_TURNO),
            'usuario_id' => $this->supervisor->id,
            'fecha_hora' => '2026-11-11 14:00:00',
        ]);

        $this->actingAs($this->supervisor)
            ->post(route('ampliaciones.store', $this->evento), ['detalle' => self::DETALLE])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('ampliaciones', 0);
    }

    public function test_a_una_constancia_no_se_le_hace_ampliacion(): void
    {
        $constancia = Evento::factory()->create([
            'tipo_evento_id' => TipoEvento::interno(TipoEvento::AMPLIACION),
            'usuario_id' => $this->supervisor->id,
        ]);

        $this->actingAs($this->supervisor)->get(route('ampliaciones.create', $constancia))->assertForbidden();
        $this->actingAs($this->supervisor)
            ->post(route('ampliaciones.store', $constancia), ['detalle' => self::DETALLE])
            ->assertSessionHas('error');
        $this->actingAs($this->supervisor)
            ->get(route('eventos.show', $constancia))
            ->assertInertia(fn (Assert $page) => $page
                ->where('puedeAmpliar', false)
                // Sí admite fe de errata (ErrataTest).
                ->where('puedeCorregir', true));

        $this->assertDatabaseCount('ampliaciones', 0);
    }

    public function test_la_ampliacion_es_inmutable(): void
    {
        $ampliacion = Ampliacion::create([
            'evento_id' => $this->evento->id,
            'usuario_id' => $this->supervisor->id,
            'detalle' => 'prueba',
        ]);

        $this->expectException(LogicException::class);
        $ampliacion->update(['detalle' => 'otro']);
    }
}
