<?php

namespace Tests\Feature\Eventos;

use App\Events\EventoRegistrado;
use App\Models\Barrio;
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
 * US-003: registro de evento con ubicación (Punto de Monitoreo).
 */
class RegistroEventoTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private TipoEvento $tipo;

    private PuntoMonitoreo $punto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-13 15:00:00'));

        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
        $this->tipo = TipoEvento::factory()->create(['requiere_ubicacion' => true]);
        $this->punto = PuntoMonitoreo::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datosValidos(array $cambios = []): array
    {
        return [
            'tipo_evento_id' => $this->tipo->id,
            'fecha' => '2026-09-13',
            'hora' => '14:30:00',
            'punto_monitoreo_id' => $this->punto->id,
            'descripcion' => 'Dos personas forcejean en la esquina.',
            ...$cambios,
        ];
    }

    public function test_el_supervisor_ve_el_formulario_con_tipos_y_puntos(): void
    {
        PuntoMonitoreo::factory()->count(2)->create();

        $this->actingAs($this->supervisor)
            ->get(route('eventos.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Eventos/Create')
                ->has('tipos', 1)
                ->has('puntos', 3)
                ->has('categorias', 3)
                ->missing('turnoActivo'));
    }

    public function test_el_formulario_precarga_el_punto_de_monitoreo_elegido_en_el_mapa(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('eventos.create', ['punto_monitoreo_id' => $this->punto->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('puntoInicial', $this->punto->id));

        // Un PM inexistente se ignora.
        $this->actingAs($this->supervisor)
            ->get(route('eventos.create', ['punto_monitoreo_id' => 99999]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('puntoInicial', null));
    }

    public function test_solo_el_supervisor_puede_registrar_eventos(): void
    {
        foreach (['Administrativo', 'Administrador de sistema'] as $rol) {
            $usuario = User::factory()->create()->assignRole($rol);

            $this->actingAs($usuario)->get(route('eventos.create'))->assertForbidden();
            $this->actingAs($usuario)->post(route('eventos.store'), $this->datosValidos())->assertForbidden();
        }

        $this->assertSame(0, Evento::count());
    }

    public function test_un_invitado_es_redirigido_al_login(): void
    {
        $this->post(route('eventos.store'), $this->datosValidos())->assertRedirect(route('login'));
    }

    public function test_registra_el_evento_con_punto_de_monitoreo_y_autor(): void
    {
        Event::fake([EventoRegistrado::class]);

        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('eventos.create'));

        $evento = Evento::sole();

        $this->assertSame($this->tipo->id, $evento->tipo_evento_id);
        $this->assertSame($this->punto->id, $evento->punto_monitoreo_id);
        $this->assertSame($this->supervisor->id, $evento->usuario_id);
        $this->assertSame('2026-09-13 14:30:00', $evento->fecha_hora->format('Y-m-d H:i:s'));
        // El timestamp del video ya no se carga.
        $this->assertNull($evento->timestamp_video);
        $this->assertSame('Dos personas forcejean en la esquina.', $evento->descripcion);
        $this->assertNotNull($evento->created_at);

        Event::assertDispatched(EventoRegistrado::class, fn ($e) => $e->evento->is($evento));
    }

    public function test_confirma_el_registro_con_el_numero_del_evento(): void
    {
        Event::fake([EventoRegistrado::class]);

        $respuesta = $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos());

        $evento = Evento::sole();

        $respuesta->assertSessionHas('success', "Evento #{$evento->id} registrado correctamente.")
            ->assertSessionHas('eventoRegistrado', $evento->id);
    }

    public function test_el_autor_no_se_puede_falsear_desde_el_request(): void
    {
        $otro = User::factory()->create();

        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos(['usuario_id' => $otro->id, 'turno_id' => 999]));

        $evento = Evento::sole();
        $this->assertSame($this->supervisor->id, $evento->usuario_id);
        $this->assertArrayNotHasKey('turno_id', $evento->getAttributes());
    }

    public function test_valida_los_campos_obligatorios(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), [])
            ->assertSessionHasErrors(['tipo_evento_id', 'fecha', 'hora']);

        $this->assertSame(0, Evento::count());
    }

    public function test_el_punto_de_monitoreo_debe_existir(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos(['punto_monitoreo_id' => 99999]))
            ->assertSessionHasErrors(['punto_monitoreo_id' => 'El punto de monitoreo seleccionado no existe.']);

        $this->assertSame(0, Evento::count());
    }

    public function test_el_tipo_de_evento_debe_existir(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos(['tipo_evento_id' => 99999]))
            ->assertSessionHasErrors('tipo_evento_id');
    }

    public function test_acepta_horas_sin_segundos_del_input_time(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos(['hora' => '14:30']))
            ->assertSessionHasNoErrors();

        $this->assertSame('14:30:00', Evento::sole()->fecha_hora->format('H:i:s'));
    }

    public function test_el_tipo_que_requiere_ubicacion_sin_pm_exige_barrio_y_direccion(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos(['punto_monitoreo_id' => null]))
            ->assertSessionHasErrors(['barrio_id', 'direccion']);

        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos([
                'punto_monitoreo_id' => null,
                'barrio_id' => Barrio::create(['nombre' => 'Barrio de prueba'])->id,
            ]))
            ->assertSessionHasErrors('direccion');

        $this->assertSame(0, Evento::count());
    }

    public function test_el_tipo_que_requiere_ubicacion_se_registra_con_barrio_y_direccion_sin_pm(): void
    {
        $this->seed(PermissionSeeder::class);
        $barrio = Barrio::create(['nombre' => 'Centro']);

        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos([
                'punto_monitoreo_id' => null,
                'barrio_id' => $barrio->id,
                'direccion' => '  San Martín 1234 ',
            ]))
            ->assertSessionHasNoErrors();

        $evento = Evento::sole();
        $this->assertNull($evento->punto_monitoreo_id);
        $this->assertNull($evento->timestamp_video);
        $this->assertSame($barrio->id, $evento->barrio_id);
        $this->assertSame('San Martín 1234', $evento->direccion);
        $this->assertSame('San Martín 1234, barrio Centro', $evento->ubicacion());

        // El detalle la muestra y el listado la usa como ubicación.
        $this->actingAs($this->supervisor)
            ->get(route('eventos.show', $evento))
            ->assertInertia(fn (Assert $page) => $page
                ->where('evento.barrio', 'Centro')
                ->where('evento.direccion', 'San Martín 1234'));
        $this->actingAs($this->supervisor)
            ->get(route('eventos.index', ['barrio_id' => $barrio->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('eventos.total', 1)
                ->where('eventos.data.0.punto', 'San Martín 1234, barrio Centro'));
    }

    public function test_con_pm_se_descartan_barrio_y_direccion(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos([
                'barrio_id' => Barrio::create(['nombre' => 'Barrio de prueba'])->id,
                'direccion' => 'San Martín 1234',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Evento::sole()->barrio_id);
        $this->assertNull(Evento::sole()->direccion);
    }

    public function test_tipo_con_ubicacion_opcional_acepta_barrio_y_direccion_opcionales(): void
    {
        $tipo = TipoEvento::factory()->sinUbicacion()->create();

        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos([
                'tipo_evento_id' => $tipo->id,
                'punto_monitoreo_id' => null,
                'direccion' => 'Ruta 9 km 560',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Ruta 9 km 560', Evento::sole()->direccion);
        $this->assertNull(Evento::sole()->barrio_id);
    }

    public function test_tipo_sin_ubicacion_se_registra_sin_punto_ni_timestamp(): void
    {
        $tipo = TipoEvento::factory()->sinUbicacion()->create();

        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos([
                'tipo_evento_id' => $tipo->id,
                'punto_monitoreo_id' => null,
                'timestamp_video' => '10:00:00',
            ]))
            ->assertSessionHasNoErrors();

        $evento = Evento::sole();
        $this->assertNull($evento->punto_monitoreo_id);
        $this->assertNull($evento->timestamp_video);
    }

    public function test_la_fecha_y_hora_del_hecho_no_pueden_ser_futuras(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos(['hora' => '15:30:00']))
            ->assertSessionHasErrors('fecha');

        $this->assertSame(0, Evento::count());
    }

    public function test_la_descripcion_es_obligatoria(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos(['descripcion' => '']))
            ->assertSessionHasErrors(['descripcion' => 'La descripción no puede estar vacía.']);

        $this->assertDatabaseCount('eventos', 0);
    }

    public function test_se_registra_sin_una_apertura_de_turno_vigente(): void
    {
        Event::fake([EventoRegistrado::class]);

        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Evento::count());
    }

    public function test_la_constancia_errata_no_se_registra_a_mano(): void
    {
        $this->seed(TipoEventoSeeder::class);
        $errata = TipoEvento::interno(TipoEvento::ERRATA);

        $this->actingAs($this->supervisor)
            ->get(route('eventos.create'))
            ->assertInertia(fn (Assert $page) => $page->where(
                'tipos',
                fn ($tipos) => collect($tipos)->pluck('id')->doesntContain($errata),
            ));

        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos(['tipo_evento_id' => $errata, 'punto_monitoreo_id' => null]))
            ->assertSessionHasErrors('tipo_evento_id');

        $this->assertSame(0, Evento::count());
    }

    public function test_apertura_y_cierre_de_turno_se_registran_como_cualquier_evento(): void
    {
        Event::fake([EventoRegistrado::class]);
        $this->seed(TipoEventoSeeder::class);

        foreach ([TipoEvento::APERTURA_TURNO, TipoEvento::CIERRE_TURNO] as $tipo) {
            $this->actingAs($this->supervisor)
                ->post(route('eventos.store'), $this->datosValidos([
                    'tipo_evento_id' => TipoEvento::interno($tipo),
                    'punto_monitoreo_id' => null,
                    'descripcion' => 'Personal presente: Juan y María.',
                ]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Evento::count());
        Event::assertDispatchedTimes(EventoRegistrado::class, 2);
    }

    public function test_apertura_y_cierre_de_turno_no_llevan_punto_ni_timestamp(): void
    {
        Event::fake([EventoRegistrado::class]);
        $this->seed(TipoEventoSeeder::class);

        // Aunque lleguen un PM y un timestamp (o un timestamp inválido), se descartan.
        foreach ([TipoEvento::APERTURA_TURNO, TipoEvento::CIERRE_TURNO] as $tipo) {
            $this->actingAs($this->supervisor)
                ->post(route('eventos.store'), $this->datosValidos([
                    'tipo_evento_id' => TipoEvento::interno($tipo),
                    'timestamp_video' => 'x',
                ]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(0, Evento::whereNotNull('punto_monitoreo_id')->orWhereNotNull('timestamp_video')->count());

        $this->actingAs($this->supervisor)
            ->get(route('eventos.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tipos', fn ($tipos) => collect($tipos)->firstWhere('nombre', TipoEvento::APERTURA_TURNO)['sin_camara'] === true
                    && collect($tipos)->firstWhere('id', $this->tipo->id)['sin_camara'] === false));
    }

    public function test_el_evento_registrado_es_inmutable(): void
    {
        $evento = Evento::factory()->create();

        $this->expectException(LogicException::class);

        $evento->update(['descripcion' => 'otra cosa']);
    }

    public function test_el_evento_registrado_no_se_puede_eliminar(): void
    {
        $evento = Evento::factory()->create();

        $this->expectException(LogicException::class);

        $evento->delete();
    }
}
