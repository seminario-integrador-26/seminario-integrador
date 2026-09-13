<?php

namespace Tests\Feature\Eventos;

use App\Events\EventoRegistrado;
use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\Turno;
use App\Models\User;
use Database\Seeders\RoleSeeder;
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

    private Turno $turno;

    private TipoEvento $tipo;

    private PuntoMonitoreo $punto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-13 15:00:00'));

        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
        $this->turno = Turno::factory()->create(['supervisor_id' => $this->supervisor->id]);
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
            'timestamp_video' => '14:29:47',
            'descripcion' => 'Dos personas forcejean en la esquina.',
            ...$cambios,
        ];
    }

    public function test_el_supervisor_ve_el_formulario_con_tipos_puntos_y_turno_activo(): void
    {
        PuntoMonitoreo::factory()->count(2)->create();

        $this->actingAs($this->supervisor)
            ->get(route('eventos.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Eventos/Create')
                ->has('tipos', 1)
                ->has('puntos', 3)
                ->has('categorias', 4)
                ->where('turnoActivo.id', $this->turno->id));
    }

    public function test_solo_el_supervisor_puede_registrar_eventos(): void
    {
        foreach (['Administrativo', 'Operador'] as $rol) {
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

    public function test_registra_el_evento_con_punto_de_monitoreo_turno_y_autor(): void
    {
        Event::fake([EventoRegistrado::class]);

        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('eventos.create'));

        $evento = Evento::sole();

        $this->assertSame($this->tipo->id, $evento->tipo_evento_id);
        $this->assertSame($this->punto->id, $evento->punto_monitoreo_id);
        $this->assertSame($this->turno->id, $evento->turno_id);
        $this->assertSame($this->supervisor->id, $evento->usuario_id);
        $this->assertSame('2026-09-13 14:30:00', $evento->fecha_hora->format('Y-m-d H:i:s'));
        $this->assertSame('14:29:47', $evento->timestamp_video);
        $this->assertSame('Dos personas forcejean en la esquina.', $evento->descripcion);
        $this->assertNotNull($evento->created_at);

        Event::assertDispatched(EventoRegistrado::class, fn ($e) => $e->evento->is($evento));
    }

    public function test_el_autor_no_se_puede_falsear_desde_el_request(): void
    {
        $otro = User::factory()->create();

        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos(['usuario_id' => $otro->id, 'turno_id' => 999]));

        $evento = Evento::sole();
        $this->assertSame($this->supervisor->id, $evento->usuario_id);
        $this->assertSame($this->turno->id, $evento->turno_id);
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

    public function test_el_timestamp_del_video_es_obligatorio_cuando_hay_punto_de_monitoreo(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos(['timestamp_video' => '']))
            ->assertSessionHasErrors('timestamp_video');

        $this->assertSame(0, Evento::count());
    }

    public function test_el_timestamp_del_video_debe_tener_formato_hh_mm_ss(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos(['timestamp_video' => '25:61:00']))
            ->assertSessionHasErrors('timestamp_video');
    }

    public function test_acepta_horas_sin_segundos_del_input_time(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos(['hora' => '14:30', 'timestamp_video' => '14:29']))
            ->assertSessionHasNoErrors();

        $this->assertSame('14:29:00', Evento::sole()->timestamp_video);
    }

    public function test_el_tipo_que_requiere_ubicacion_exige_punto_de_monitoreo(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos(['punto_monitoreo_id' => null]))
            ->assertSessionHasErrors('punto_monitoreo_id');

        $this->assertSame(0, Evento::count());
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

    public function test_sin_turno_abierto_no_se_registra_el_evento(): void
    {
        $this->turno->update(['hora_fin' => '14:45:00']);

        $this->actingAs($this->supervisor)
            ->post(route('eventos.store'), $this->datosValidos())
            ->assertSessionHasErrors('turno');

        $this->assertSame(0, Evento::count());
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
