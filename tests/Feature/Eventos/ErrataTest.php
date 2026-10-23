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
 * Regla de negocio 1: los eventos se corrigen por fe de errata; el original
 * nunca se modifica.
 */
class ErrataTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private TipoEvento $robo;

    private PuntoMonitoreo $plaza;

    private Evento $evento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, TipoEventoSeeder::class]);
        $this->travelTo(Carbon::parse('2026-10-15 15:00:00'));

        $this->supervisor = User::factory()->create(['name' => 'Ana Supervisora'])->assignRole('Supervisor');
        $this->robo = TipoEvento::factory()->create(['nombre' => 'Robo', 'requiere_ubicacion' => true]);
        $this->plaza = PuntoMonitoreo::factory()->create(['codigo' => 'PM-M-001', 'nombre' => 'Plaza']);

        $this->evento = Evento::factory()->create([
            'tipo_evento_id' => $this->robo->id,
            'punto_monitoreo_id' => $this->plaza->id,
            'usuario_id' => $this->supervisor->id,
            'fecha_hora' => '2026-10-15 10:00:00',
            'timestamp_video' => '09:59:30',
            'descripcion' => 'Arrebato de celular.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'tipo_evento_id' => $this->robo->id,
            'fecha' => '2026-10-15',
            'hora' => '10:00:00',
            'punto_monitoreo_id' => $this->plaza->id,
            'timestamp_video' => '09:59:30',
            'descripcion' => 'Arrebato de celular.',
            'motivo' => 'Se cargó mal el dato en la guardia.',
            ...$cambios,
        ];
    }

    public function test_el_formulario_se_precarga_con_los_valores_vigentes(): void
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

    public function test_registra_una_errata_por_campo_cambiado_sin_tocar_el_original(): void
    {
        $otroPunto = PuntoMonitoreo::factory()->create(['codigo' => 'PM-M-002', 'nombre' => 'Terminal']);

        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), $this->datos([
                'punto_monitoreo_id' => $otroPunto->id,
                'hora' => '10:05',
                'descripcion' => 'Arrebato de celular a una mujer.',
            ]))
            ->assertRedirect(route('eventos.show', $this->evento))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('erratas', 3);
        $this->assertDatabaseHas('erratas', [
            'evento_id' => $this->evento->id,
            'usuario_id' => $this->supervisor->id,
            'campo_corregido' => 'fecha_hora',
            'valor_anterior' => '2026-10-15 10:00:00',
            'valor_nuevo' => '2026-10-15 10:05:00',
            'motivo' => 'Se cargó mal el dato en la guardia.',
        ]);
        $this->assertDatabaseHas('erratas', [
            'campo_corregido' => 'punto_monitoreo_id',
            'valor_anterior' => (string) $this->plaza->id,
            'valor_nuevo' => (string) $otroPunto->id,
        ]);

        // El original sigue intacto.
        $original = $this->evento->fresh();
        $this->assertSame($this->plaza->id, $original->punto_monitoreo_id);
        $this->assertSame('Arrebato de celular.', $original->descripcion);

        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_ERRATA,
            'user_id' => $this->supervisor->id,
        ]);
    }

    public function test_el_detalle_muestra_los_valores_vigentes_y_el_historial(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), $this->datos(['descripcion' => 'Primera corrección.']));
        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), $this->datos(['descripcion' => 'Segunda corrección.']));

        $this->actingAs($this->supervisor)
            ->get(route('eventos.show', $this->evento))
            ->assertInertia(fn (Assert $page) => $page
                ->where('evento.descripcion', 'Segunda corrección.')
                ->has('evento.erratas', 2)
                ->where('evento.erratas.1.anterior', 'Primera corrección.')
                ->where('evento.erratas.1.nuevo', 'Segunda corrección.')
                ->where('evento.erratas.0.autor', 'Ana Supervisora')
                ->where('puedeCorregir', true));

        // Filtrado por tipo: las constancias de errata también figuran en el libro.
        $this->actingAs($this->supervisor)
            ->get(route('eventos.index', ['tipo_evento_id' => $this->robo->id]))
            ->assertInertia(fn (Assert $page) => $page->where('eventos.data.0.corregido', true));
    }

    public function test_los_valores_legibles_muestran_nombres_y_fechas(): void
    {
        $otroPunto = PuntoMonitoreo::factory()->create(['codigo' => 'PM-M-002', 'nombre' => 'Terminal']);

        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), $this->datos(['punto_monitoreo_id' => $otroPunto->id, 'hora' => '09:59:59']));

        $this->actingAs($this->supervisor)
            ->get(route('eventos.show', $this->evento))
            ->assertInertia(fn (Assert $page) => $page
                ->where('evento.erratas.0.anterior', '15/10/2026 10:00:00')
                ->where('evento.erratas.0.nuevo', '15/10/2026 09:59:59')
                ->where('evento.erratas.1.anterior', 'PM-M-001 — Plaza')
                ->where('evento.erratas.1.nuevo', 'PM-M-002 — Terminal')
                ->where('evento.punto.codigo', 'PM-M-002'));
    }

    public function test_sin_cambios_no_se_registra_nada(): void
    {
        $this->actingAs($this->supervisor)
            ->from(route('erratas.create', $this->evento))
            ->post(route('erratas.store', $this->evento), $this->datos())
            ->assertRedirect(route('erratas.create', $this->evento))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('erratas', 0);
    }

    public function test_el_motivo_es_obligatorio(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), $this->datos(['descripcion' => 'Otra', 'motivo' => '']))
            ->assertSessionHasErrors('motivo');

        $this->assertDatabaseCount('erratas', 0);
    }

    public function test_la_correccion_cumple_las_mismas_reglas_que_el_registro(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), $this->datos(['punto_monitoreo_id' => null]))
            ->assertSessionHasErrors('punto_monitoreo_id'); // el tipo requiere ubicación

        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), $this->datos(['timestamp_video' => '10:30:00']))
            ->assertSessionHasErrors('timestamp_video');

        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), $this->datos(['fecha' => '2026-10-16']))
            ->assertSessionHasErrors('fecha'); // futura

        $this->assertDatabaseCount('erratas', 0);
    }

    public function test_quitar_el_pm_descarta_tambien_el_timestamp(): void
    {
        $sinUbicacion = TipoEvento::factory()->sinUbicacion()->create();

        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), $this->datos([
                'tipo_evento_id' => $sinUbicacion->id,
                'punto_monitoreo_id' => null,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('erratas', ['campo_corregido' => 'punto_monitoreo_id', 'valor_nuevo' => null]);
        $this->assertDatabaseHas('erratas', ['campo_corregido' => 'timestamp_video', 'valor_anterior' => '09:59:30', 'valor_nuevo' => null]);
    }

    public function test_solo_el_supervisor_puede_corregir(): void
    {
        foreach (['Administrativo', 'Administrador de sistema'] as $rol) {
            $usuario = User::factory()->create()->assignRole($rol);

            $this->actingAs($usuario)
                ->get(route('erratas.create', $this->evento))
                ->assertForbidden();

            $this->actingAs($usuario)
                ->post(route('erratas.store', $this->evento), $this->datos(['descripcion' => 'X']))
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
            ->post(route('erratas.store', $this->evento), $this->datos(['descripcion' => 'Arrebato de mochila.']))
            ->assertRedirect(route('eventos.show', $this->evento));

        $this->assertDatabaseHas('erratas', [
            'evento_id' => $this->evento->id,
            'usuario_id' => $otroSupervisor->id,
            'campo_corregido' => 'descripcion',
        ]);

        $auditoria = AuditoriaAcceso::where('evento', AuditoriaAcceso::EVENTO_ERRATA)->latest('id')->firstOrFail();
        $this->assertSame($otroSupervisor->id, $auditoria->user_id);
        $this->assertStringContainsString('cargado por Ana Supervisora', $auditoria->descripcion);

        // Ve el detalle (para revisar la corrección)...
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
            ->assertInertia(fn (Assert $page) => $page->where('eventos.total', 1));
    }

    // --- Constancia en el libro de guardia (evento interno Errata) ----------

    public function test_la_fe_de_errata_deja_una_constancia_en_el_libro_y_se_notifica(): void
    {
        Event::fake([EventoRegistrado::class]);
        $hurto = TipoEvento::factory()->create(['nombre' => 'Hurto', 'requiere_ubicacion' => true]);

        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), $this->datos(['tipo_evento_id' => $hurto->id]))
            ->assertRedirect(route('eventos.show', $this->evento));

        $constancia = Evento::where('tipo_evento_id', TipoEvento::interno(TipoEvento::ERRATA))->sole();
        $this->assertSame($this->supervisor->id, $constancia->usuario_id);
        $this->assertNull($constancia->punto_monitoreo_id);
        $this->assertSame('2026-10-15 15:00:00', $constancia->fecha_hora->format('Y-m-d H:i:s'));
        $this->assertSame(
            "Corrige AT-2026-{$this->formato($this->evento->id)}: Tipo de evento, de «Robo» a «Hurto». Motivo: Se cargó mal el dato en la guardia.",
            $constancia->descripcion,
        );

        Event::assertDispatched(EventoRegistrado::class, fn ($e) => $e->evento->is($constancia));
    }

    public function test_sin_cambios_no_hay_constancia(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), $this->datos());

        $this->assertSame(1, Evento::count());
    }

    public function test_a_una_constancia_no_se_le_hace_errata(): void
    {
        $constancia = Evento::factory()->create([
            'tipo_evento_id' => TipoEvento::interno(TipoEvento::ERRATA),
            'usuario_id' => $this->supervisor->id,
        ]);

        $this->actingAs($this->supervisor)->get(route('erratas.create', $constancia))->assertForbidden();
        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $constancia), $this->datos(['descripcion' => 'Otra cosa']))
            ->assertSessionHas('error');
        $this->actingAs($this->supervisor)
            ->get(route('eventos.show', $constancia))
            ->assertInertia(fn (Assert $page) => $page->where('puedeCorregir', false));

        $this->assertDatabaseCount('erratas', 0);
    }

    public function test_la_errata_no_cambia_el_tipo_a_errata(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('erratas.store', $this->evento), $this->datos(['tipo_evento_id' => TipoEvento::interno(TipoEvento::ERRATA)]))
            ->assertSessionHasErrors('tipo_evento_id');

        $this->assertDatabaseCount('erratas', 0);
    }

    private function formato(int $id): string
    {
        return sprintf('%04d', $id);
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
