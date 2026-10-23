<?php

namespace Tests\Feature\Notificaciones;

use App\Models\AuditoriaAcceso;
use App\Models\Evento;
use App\Models\GrupoInteresado;
use App\Models\Notificacion;
use App\Models\TipoEvento;
use App\Models\User;
use App\Services\NotificacionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-028: grupos de WhatsApp y correspondencias tipo de evento → grupo.
 */
class CorrespondenciasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TipoEvento $robo;

    private TipoEvento $incendio;

    private GrupoInteresado $policia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->admin = User::factory()->create()->assignRole('Administrador de sistema');

        $this->robo = TipoEvento::factory()->delGrupo('policia')->create(['nombre' => 'Robo']);
        $this->incendio = TipoEvento::factory()->delGrupo('policia')->create(['nombre' => 'Incendio']);

        $this->policia = GrupoInteresado::create(['nombre' => 'Policía', 'identificador_whatsapp' => '1203630001']);
        $this->policia->tiposEvento()->attach($this->robo);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosGrupo(array $cambios = []): array
    {
        return [
            'nombre' => 'Bomberos',
            'identificador_whatsapp' => '',
            'contactos' => [['nombre' => 'Cuartel central', 'numero_whatsapp' => '5493534000001']],
            'tipos_evento' => [$this->incendio->id],
            ...$cambios,
        ];
    }

    private function auditado(string $accion): ?AuditoriaAcceso
    {
        return AuditoriaAcceso::where('evento', $accion)->latest('id')->first();
    }

    // --- Acceso ---------------------------------------------------------------

    public function test_solo_el_administrador_de_sistema_accede(): void
    {
        foreach (['Supervisor', 'Administrativo'] as $rol) {
            $this->actingAs(User::factory()->create()->assignRole($rol))
                ->get(route('grupos.index'))
                ->assertForbidden();
        }

        $this->actingAs($this->admin)
            ->get(route('grupos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notificaciones/Index')
                ->has('grupos', 1)
                ->where('grupos.0.tipos_evento_count', 1)
                ->has('tipos', 2)
                ->has('correspondencias', 1));
    }

    // --- Grupos ---------------------------------------------------------------

    public function test_crea_un_grupo_con_contactos_y_tipos_y_lo_audita(): void
    {
        $this->actingAs($this->admin)
            ->post(route('grupos.store'), $this->datosGrupo())
            ->assertRedirect(route('grupos.index'));

        $grupo = GrupoInteresado::where('nombre', 'Bomberos')->firstOrFail();
        $this->assertSame(['5493534000001'], $grupo->destinatarios('whatsapp'));
        $this->assertEquals([$this->incendio->id], $grupo->tiposEvento->pluck('id')->all());
        $this->assertNotNull($this->auditado(AuditoriaAcceso::EVENTO_GRUPO_ALTA));
    }

    public function test_un_grupo_necesita_identificador_o_contactos(): void
    {
        $this->actingAs($this->admin)
            ->post(route('grupos.store'), $this->datosGrupo(['contactos' => []]))
            ->assertSessionHasErrors('identificador_whatsapp');
    }

    public function test_un_grupo_puede_tener_solo_el_chat_de_telegram(): void
    {
        $this->actingAs($this->admin)
            ->post(route('grupos.store'), $this->datosGrupo(['contactos' => [], 'telegram_chat_id' => '-1001234567890']))
            ->assertRedirect(route('grupos.index'));

        $grupo = GrupoInteresado::where('nombre', 'Bomberos')->firstOrFail();
        $this->assertSame(['-1001234567890'], $grupo->destinatarios('telegram'));
        $this->assertSame([], $grupo->destinatarios('whatsapp'));
    }

    public function test_valida_el_formato_del_chat_de_telegram(): void
    {
        $this->actingAs($this->admin)
            ->post(route('grupos.store'), $this->datosGrupo(['telegram_chat_id' => 'https://t.me/grupo']))
            ->assertSessionHasErrors('telegram_chat_id');
    }

    public function test_valida_el_formato_del_numero_y_el_nombre_unico(): void
    {
        $this->actingAs($this->admin)
            ->post(route('grupos.store'), $this->datosGrupo([
                'nombre' => 'Policía',
                'contactos' => [['nombre' => 'X', 'numero_whatsapp' => '+54 353 400']],
            ]))
            ->assertSessionHasErrors(['nombre', 'contactos.0.numero_whatsapp']);
    }

    public function test_edita_un_grupo_y_audita_lo_que_cambio(): void
    {
        $this->actingAs($this->admin)
            ->put(route('grupos.update', $this->policia), [
                'nombre' => 'Policía',
                'identificador_whatsapp' => '1203630001',
                'contactos' => [],
                'tipos_evento' => [$this->robo->id, $this->incendio->id],
            ])
            ->assertRedirect(route('grupos.index'));

        $this->assertCount(2, $this->policia->tiposEvento()->get());
        $this->assertStringContainsString('+Incendio', $this->auditado(AuditoriaAcceso::EVENTO_GRUPO_MOD)->descripcion);
    }

    public function test_no_se_elimina_un_grupo_que_ya_recibio_notificaciones(): void
    {
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        Notificacion::create([
            'evento_id' => Evento::factory()->create([
                'tipo_evento_id' => $this->robo->id,
                'usuario_id' => $supervisor->id,
            ])->id,
            'grupo_interesado_id' => $this->policia->id,
            'canal' => 'log',
            'mensaje' => 'x',
            'estado' => Notificacion::ESTADO_ENVIADA,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('grupos.destroy', $this->policia))
            ->assertSessionHas('error');

        $this->assertModelExists($this->policia);
    }

    public function test_elimina_un_grupo_sin_notificaciones_y_lo_audita(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('grupos.destroy', $this->policia))
            ->assertSessionHas('success');

        $this->assertModelMissing($this->policia);
        $this->assertNotNull($this->auditado(AuditoriaAcceso::EVENTO_GRUPO_BAJA));
    }

    // --- Matriz -----------------------------------------------------------------

    public function test_guarda_la_matriz_y_audita_altas_y_bajas(): void
    {
        $this->actingAs($this->admin)
            ->put(route('grupos.correspondencias'), ['correspondencias' => [
                ['tipo_evento_id' => $this->incendio->id, 'grupo_interesado_id' => $this->policia->id],
            ]])
            ->assertSessionHas('success');

        $this->assertEquals([$this->incendio->id], $this->policia->tiposEvento()->pluck('tipos_evento.id')->all());
        $this->assertSame(
            'Policía: +Incendio, −Robo',
            $this->auditado(AuditoriaAcceso::EVENTO_CORRESPONDENCIAS)->descripcion,
        );
    }

    public function test_sin_cambios_no_audita(): void
    {
        $this->actingAs($this->admin)
            ->put(route('grupos.correspondencias'), ['correspondencias' => [
                ['tipo_evento_id' => $this->robo->id, 'grupo_interesado_id' => $this->policia->id],
            ]])
            ->assertSessionHas('success');

        $this->assertNull($this->auditado(AuditoriaAcceso::EVENTO_CORRESPONDENCIAS));
    }

    public function test_vaciar_la_matriz_deja_los_tipos_sin_destinatario(): void
    {
        $this->actingAs($this->admin)
            ->put(route('grupos.correspondencias'), ['correspondencias' => []])
            ->assertSessionHas('success');

        $this->assertSame(0, $this->policia->tiposEvento()->count());
    }

    public function test_notifica_se_deriva_de_la_matriz(): void
    {
        $this->actingAs($this->admin)
            ->put(route('grupos.correspondencias'), ['correspondencias' => [
                ['tipo_evento_id' => $this->incendio->id, 'grupo_interesado_id' => $this->policia->id],
            ]]);

        $this->assertTrue($this->incendio->fresh()->notifica);
        $this->assertFalse($this->robo->fresh()->notifica);

        $this->actingAs($this->admin)->delete(route('grupos.destroy', $this->policia));

        $this->assertFalse($this->incendio->fresh()->notifica);
    }

    public function test_el_cambio_rige_para_el_proximo_evento(): void
    {
        Queue::fake();
        $this->actingAs($this->admin)
            ->put(route('grupos.correspondencias'), ['correspondencias' => [
                ['tipo_evento_id' => $this->incendio->id, 'grupo_interesado_id' => $this->policia->id],
            ]]);

        $supervisor = User::factory()->create()->assignRole('Supervisor');
        $evento = Evento::factory()->create([
            'tipo_evento_id' => $this->incendio->id,
            'usuario_id' => $supervisor->id,
        ]);

        $notificaciones = app(NotificacionService::class)->notificarEvento($evento);

        $this->assertCount(1, $notificaciones);
        $this->assertSame($this->policia->id, $notificaciones->first()->grupo_interesado_id);
    }
}
