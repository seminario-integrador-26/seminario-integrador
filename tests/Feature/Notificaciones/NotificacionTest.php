<?php

namespace Tests\Feature\Notificaciones;

use App\Contracts\NotificadorInterface;
use App\Events\EventoRegistrado;
use App\Jobs\EnviarNotificacionWhatsapp;
use App\Listeners\NotificarInteresadosListener;
use App\Models\AuditoriaAcceso;
use App\Models\Evento;
use App\Models\GrupoInteresado;
use App\Models\Notificacion;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use App\Services\NotificacionService;
use App\Services\Notificadores\LogNotificador;
use App\Services\Notificadores\TelegramNotificador;
use App\Services\Notificadores\WhatsappNotificador;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/**
 * US-006 (notificación automática) y US-007 (reintentos, alerta y reenvío).
 */
class NotificacionTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private GrupoInteresado $guardiaLocal;

    private TipoEvento $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->travelTo(Carbon::parse('2026-10-14 15:00:00'));
        config(['notificaciones.canal' => 'log', 'notificaciones.simular_fallo' => false]);

        $this->supervisor = User::factory()->create()->assignRole('Supervisor');

        $this->tipo = TipoEvento::factory()->delGrupo('guardia_local')->create([
            'nombre' => 'Ruidos molestos',
        ]);

        $this->guardiaLocal = GrupoInteresado::create(['nombre' => 'Guardia Local']);
        $this->guardiaLocal->contactos()->create(['nombre' => 'Jefe de guardia', 'numero_whatsapp' => '5493530000001']);
        $this->guardiaLocal->tiposEvento()->attach($this->tipo);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function evento(array $atributos = []): Evento
    {
        return Evento::factory()->create([
            'tipo_evento_id' => $this->tipo->id,
            'usuario_id' => $this->supervisor->id,
            'fecha_hora' => '2026-10-14 14:30:15',
            'descripcion' => 'Música a alto volumen en la vereda.',
            ...$atributos,
        ]);
    }

    private function notificacion(string $estado = Notificacion::ESTADO_PENDIENTE): Notificacion
    {
        return Notificacion::create([
            'evento_id' => $this->evento()->id,
            'grupo_interesado_id' => $this->guardiaLocal->id,
            'canal' => 'log',
            'mensaje' => 'Mensaje de prueba',
            'estado' => $estado,
        ]);
    }

    // --- US-006: despacho ---------------------------------------------------

    public function test_registrar_un_evento_dispara_la_notificacion_en_cola(): void
    {
        Event::fake();

        Event::assertListening(EventoRegistrado::class, NotificarInteresadosListener::class);
    }

    public function test_se_crea_una_notificacion_por_grupo_del_tipo_y_se_encola_el_envio(): void
    {
        Queue::fake();

        $policia = GrupoInteresado::create(['nombre' => 'Policía']);
        $policia->tiposEvento()->attach($this->tipo);
        GrupoInteresado::create(['nombre' => 'Bomberos']); // no asociado al tipo

        $evento = $this->evento();

        $notificaciones = app(NotificacionService::class)->notificarEvento($evento);

        $this->assertCount(2, $notificaciones);
        $this->assertEqualsCanonicalizing(
            ['Guardia Local', 'Policía'],
            $notificaciones->map(fn ($n) => $n->grupoInteresado->nombre)->all(),
        );
        $this->assertDatabaseCount('notificaciones', 2);
        $this->assertDatabaseHas('notificaciones', [
            'evento_id' => $evento->id,
            'estado' => Notificacion::ESTADO_PENDIENTE,
            'canal' => 'log',
        ]);
        Queue::assertPushed(EnviarNotificacionWhatsapp::class, 2);
    }

    public function test_un_tipo_sin_grupos_no_genera_notificaciones(): void
    {
        Queue::fake();

        $informativo = TipoEvento::factory()->sinUbicacion()->delGrupo('internos')->create();

        app(NotificacionService::class)->notificarEvento($this->evento(['tipo_evento_id' => $informativo->id]));

        $this->assertDatabaseCount('notificaciones', 0);
        Queue::assertNothingPushed();
    }

    public function test_el_mensaje_incluye_tipo_fecha_horario_ubicacion_y_descripcion(): void
    {
        $punto = PuntoMonitoreo::factory()->delTipo('municipal')->create([
            'codigo' => 'PM-012',
            'nombre' => 'Bv. España y Mendoza',
        ]);

        $mensaje = app(NotificacionService::class)->armarMensaje(
            $this->evento(['punto_monitoreo_id' => $punto->id]),
        );

        $this->assertStringContainsString('Guardia Local: Ruidos molestos', $mensaje);
        $this->assertStringContainsString('Fecha: 14/10/2026', $mensaje);
        $this->assertStringContainsString('Horario: 14:30:15', $mensaje);
        $this->assertStringContainsString('PM-012 — Bv. España y Mendoza (Municipal)', $mensaje);
        $this->assertStringContainsString('Música a alto volumen en la vereda.', $mensaje);
    }

    public function test_el_mensaje_de_un_evento_sin_ubicacion_lo_indica(): void
    {
        $mensaje = app(NotificacionService::class)->armarMensaje($this->evento());

        $this->assertStringContainsString('Ubicación: Sin ubicación', $mensaje);
    }

    // --- Envío (Job) --------------------------------------------------------

    public function test_el_envio_exitoso_marca_la_notificacion_como_enviada(): void
    {
        $notificacion = $this->notificacion();

        EnviarNotificacionWhatsapp::dispatchSync($notificacion);

        $notificacion->refresh();
        $this->assertSame(Notificacion::ESTADO_ENVIADA, $notificacion->estado);
        $this->assertSame(1, $notificacion->intentos);
        $this->assertNotNull($notificacion->enviada_at);
        $this->assertNull($notificacion->error);
    }

    public function test_el_job_reintenta_3_veces_cada_30_segundos(): void
    {
        $job = new EnviarNotificacionWhatsapp($this->notificacion());

        $this->assertSame(4, $job->tries()); // intento original + 3 reintentos
        $this->assertSame(30, $job->backoff());
    }

    public function test_si_falla_el_envio_se_guarda_el_error_y_se_relanza_para_reintentar(): void
    {
        config(['notificaciones.simular_fallo' => true]);
        $notificacion = $this->notificacion();

        try {
            (new EnviarNotificacionWhatsapp($notificacion))
                ->handle(new LogNotificador, app(NotificacionService::class));
            $this->fail('Se esperaba una excepción para que la cola reintente.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo simulado del canal de notificación.', $e->getMessage());
        }

        $notificacion->refresh();
        $this->assertSame(Notificacion::ESTADO_PENDIENTE, $notificacion->estado);
        $this->assertSame(1, $notificacion->intentos);
        $this->assertSame('Fallo simulado del canal de notificación.', $notificacion->error);
    }

    public function test_agotados_los_reintentos_queda_fallida_y_se_audita(): void
    {
        $notificacion = $this->notificacion();

        (new EnviarNotificacionWhatsapp($notificacion))->failed(new RuntimeException('Timeout de la API'));

        $notificacion->refresh();
        $this->assertSame(Notificacion::ESTADO_FALLIDA, $notificacion->estado);
        $this->assertSame('Timeout de la API', $notificacion->error);

        $auditoria = AuditoriaAcceso::where('evento', AuditoriaAcceso::EVENTO_NOTIF_FALLIDA)->sole();
        $this->assertSame('sistema', $auditoria->email);
        $this->assertStringContainsString("Notificación #{$notificacion->id}", $auditoria->descripcion);
        $this->assertStringContainsString('Guardia Local', $auditoria->descripcion);
        $this->assertStringContainsString('Timeout de la API', $auditoria->descripcion);
    }

    public function test_un_grupo_sin_destinatarios_falla_sin_reintentar(): void
    {
        $this->guardiaLocal->contactos()->delete();
        $notificacion = $this->notificacion();

        EnviarNotificacionWhatsapp::dispatchSync($notificacion);

        $notificacion->refresh();
        $this->assertSame(Notificacion::ESTADO_FALLIDA, $notificacion->estado);
        $this->assertStringContainsString('no tiene destinatarios', $notificacion->error);
    }

    public function test_si_el_grupo_tiene_identificador_de_whatsapp_se_envia_al_grupo(): void
    {
        $this->guardiaLocal->update(['identificador_whatsapp' => 'grupo-guardia-local']);

        $notificador = new class implements NotificadorInterface
        {
            public array $destinatarios = [];

            public function notificar(array $destinatarios, string $mensaje): void
            {
                $this->destinatarios = $destinatarios;
            }
        };
        $this->app->instance(NotificadorInterface::class, $notificador);

        EnviarNotificacionWhatsapp::dispatchSync($this->notificacion());

        $this->assertSame(['grupo-guardia-local'], $notificador->destinatarios);
    }

    public function test_con_canal_telegram_se_envia_al_chat_del_grupo_y_se_registra_el_canal(): void
    {
        config(['notificaciones.canal' => 'telegram', 'notificaciones.telegram.token' => 'token-de-prueba']);
        $this->guardiaLocal->update(['telegram_chat_id' => '-1001234567890']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $notificacion = $this->notificacion();
        EnviarNotificacionWhatsapp::dispatchSync($notificacion);

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $r) => $r['chat_id'] === '-1001234567890');
        $notificacion->refresh();
        $this->assertSame(Notificacion::ESTADO_ENVIADA, $notificacion->estado);
        $this->assertSame('telegram', $notificacion->canal);
    }

    public function test_con_canal_telegram_un_grupo_sin_chat_falla_sin_reintentar(): void
    {
        config(['notificaciones.canal' => 'telegram', 'notificaciones.telegram.token' => 'token-de-prueba']);
        Http::fake();

        $notificacion = $this->notificacion();
        EnviarNotificacionWhatsapp::dispatchSync($notificacion);

        Http::assertNothingSent();
        $notificacion->refresh();
        $this->assertSame(Notificacion::ESTADO_FALLIDA, $notificacion->estado);
        $this->assertStringContainsString('no tiene destinatarios configurados para telegram', $notificacion->error);
    }

    // --- US-007: alerta y reenvío manual -----------------------------------

    public function test_el_supervisor_ve_la_alerta_de_notificaciones_fallidas_de_sus_eventos(): void
    {
        $this->notificacion(Notificacion::ESTADO_FALLIDA);
        $this->notificacion(Notificacion::ESTADO_ENVIADA);

        $this->actingAs($this->supervisor)
            ->get(route('eventos.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notificacionesFallidas', 1)
                ->where('eventos.data.0.notificaciones.0.grupo', 'Guardia Local'));

        $otroSupervisor = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($otroSupervisor)
            ->get(route('eventos.index'))
            ->assertInertia(fn (Assert $page) => $page->where('notificacionesFallidas', 0));
    }

    public function test_el_supervisor_reenvia_una_notificacion_fallida(): void
    {
        Queue::fake();
        $notificacion = $this->notificacion(Notificacion::ESTADO_FALLIDA);

        $this->actingAs($this->supervisor)
            ->from(route('eventos.index'))
            ->post(route('notificaciones.reenviar', $notificacion))
            ->assertRedirect(route('eventos.index'))
            ->assertSessionHas('success');

        $this->assertSame(Notificacion::ESTADO_PENDIENTE, $notificacion->fresh()->estado);
        Queue::assertPushed(EnviarNotificacionWhatsapp::class,
            fn ($job) => $job->notificacion->is($notificacion));
        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_NOTIF_REENVIO,
            'user_id' => $this->supervisor->id,
        ]);
    }

    public function test_no_se_reenvia_una_notificacion_que_no_fallo(): void
    {
        Queue::fake();
        $notificacion = $this->notificacion(Notificacion::ESTADO_ENVIADA);

        $this->actingAs($this->supervisor)
            ->from(route('eventos.index'))
            ->post(route('notificaciones.reenviar', $notificacion))
            ->assertSessionHas('error');

        $this->assertSame(Notificacion::ESTADO_ENVIADA, $notificacion->fresh()->estado);
        Queue::assertNothingPushed();
    }

    public function test_el_administrativo_no_puede_reenviar(): void
    {
        $notificacion = $this->notificacion(Notificacion::ESTADO_FALLIDA);

        $this->actingAs(User::factory()->create()->assignRole('Administrativo'))
            ->post(route('notificaciones.reenviar', $notificacion))
            ->assertForbidden();
    }

    // --- WhatsApp Cloud API -------------------------------------------------

    public function test_whatsapp_envia_un_mensaje_por_destinatario_a_la_cloud_api(): void
    {
        config([
            'notificaciones.whatsapp.url' => 'https://graph.facebook.com/v21.0',
            'notificaciones.whatsapp.phone_number_id' => '123456',
            'notificaciones.whatsapp.token' => 'token-de-prueba',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

        (new WhatsappNotificador)->notificar(['5493530000001', '5493530000002'], 'Hola');

        Http::assertSentCount(2);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://graph.facebook.com/v21.0/123456/messages'
            && $r->hasHeader('Authorization', 'Bearer token-de-prueba')
            && $r['to'] === '5493530000001'
            && $r['text']['body'] === 'Hola');
    }

    public function test_whatsapp_lanza_excepcion_si_la_api_rechaza_el_envio(): void
    {
        config([
            'notificaciones.whatsapp.phone_number_id' => '123456',
            'notificaciones.whatsapp.token' => 'token-de-prueba',
        ]);
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 401)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid OAuth access token');

        (new WhatsappNotificador)->notificar(['5493530000001'], 'Hola');
    }

    public function test_whatsapp_sin_credenciales_falla_con_mensaje_claro(): void
    {
        config(['notificaciones.whatsapp.phone_number_id' => null, 'notificaciones.whatsapp.token' => null]);
        Http::fake();

        $this->expectExceptionMessage('WhatsApp Cloud API sin configurar');

        (new WhatsappNotificador)->notificar(['5493530000001'], 'Hola');
    }

    // --- Telegram Bot API ---------------------------------------------------

    public function test_telegram_envia_un_mensaje_por_chat_a_la_bot_api(): void
    {
        config([
            'notificaciones.telegram.url' => 'https://api.telegram.org',
            'notificaciones.telegram.token' => 'token-de-prueba',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        (new TelegramNotificador)->notificar(['-1001', '-1002'], 'Hola');

        Http::assertSentCount(2);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.telegram.org/bottoken-de-prueba/sendMessage'
            && $r['chat_id'] === '-1001'
            && $r['text'] === 'Hola');
    }

    public function test_telegram_lanza_excepcion_si_la_api_rechaza_el_envio(): void
    {
        config(['notificaciones.telegram.token' => 'token-de-prueba']);
        Http::fake(['*' => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('chat not found');

        (new TelegramNotificador)->notificar(['-1001'], 'Hola');
    }

    public function test_telegram_sin_token_falla_con_mensaje_claro(): void
    {
        config(['notificaciones.telegram.token' => null]);
        Http::fake();

        $this->expectExceptionMessage('Telegram sin configurar');

        (new TelegramNotificador)->notificar(['-1001'], 'Hola');
    }
}
