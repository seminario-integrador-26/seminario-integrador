<?php

namespace Tests\Feature\Api;

use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regla de negocio 8: API pública de solo lectura, versionada (/api/v1),
 * con token personal de Sanctum.
 */
class ApiPublicaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    }

    private function conToken(array $habilidades = ['datos-abiertos:leer']): void
    {
        Sanctum::actingAs(User::factory()->create(), $habilidades);
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson('/api/v1/eventos')->assertUnauthorized();
    }

    public function test_un_token_sin_la_habilidad_no_accede(): void
    {
        $this->conToken(['otra-cosa']);

        $this->getJson('/api/v1/eventos')->assertForbidden();
    }

    public function test_lista_eventos_sin_datos_personales(): void
    {
        $tipo = TipoEvento::factory()->delGrupo('policia')->create(['nombre' => 'Robo']);
        $punto = PuntoMonitoreo::factory()->delTipo('municipal')->create(['codigo' => 'PM-M-001', 'latitud' => -32.41, 'longitud' => -63.24]);
        Evento::factory()->create([
            'tipo_evento_id' => $tipo->id,
            'punto_monitoreo_id' => $punto->id,
            'fecha_hora' => '2026-10-10 21:30:00',
            'descripcion' => 'Juan Pérez, DNI 12.345.678',
        ]);

        $this->conToken();

        $respuesta = $this->getJson('/api/v1/eventos')
            ->assertOk()
            ->assertJsonPath('data.0.fecha', '2026-10-10')
            ->assertJsonPath('data.0.hora', '21:30:00')
            ->assertJsonPath('data.0.categoria', 'policia')
            ->assertJsonPath('data.0.categoria_nombre', 'Policía')
            ->assertJsonPath('data.0.tipo', 'Robo')
            ->assertJsonPath('data.0.punto_monitoreo.codigo', 'PM-M-001')
            ->assertJsonPath('data.0.punto_monitoreo.tipo', 'municipal')
            ->assertJsonPath('data.0.punto_monitoreo.latitud', -32.41)
            ->assertJsonPath('data.0.corregido', false)
            ->assertJsonStructure(['data', 'links', 'meta' => ['total', 'per_page']]);

        $this->assertStringNotContainsString('Pérez', $respuesta->getContent());
        $this->assertArrayNotHasKey('descripcion', $respuesta->json('data.0'));
        $this->assertArrayNotHasKey('usuario_id', $respuesta->json('data.0'));
    }

    public function test_filtra_por_fecha_categoria_y_pm(): void
    {
        $robo = TipoEvento::factory()->delGrupo('policia')->create();
        $ruidos = TipoEvento::factory()->delGrupo('guardia_local')->create();
        $punto = PuntoMonitoreo::factory()->create(['codigo' => 'PM-P-009']);

        Evento::factory()->create(['tipo_evento_id' => $robo->id, 'punto_monitoreo_id' => $punto->id, 'fecha_hora' => '2026-10-05 10:00:00']);
        Evento::factory()->create(['tipo_evento_id' => $ruidos->id, 'fecha_hora' => '2026-10-05 10:00:00']);
        Evento::factory()->create(['tipo_evento_id' => $robo->id, 'fecha_hora' => '2026-09-01 10:00:00']);

        $this->conToken();

        $this->getJson('/api/v1/eventos?desde=2026-10-01&hasta=2026-10-31')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/eventos?categoria=policia')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/eventos?punto=PM-P-009')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/eventos?por_pagina=1')->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3);
        $this->getJson('/api/v1/eventos?por_pagina=500')->assertUnprocessable();
    }

    public function test_no_publica_los_eventos_internos(): void
    {
        $robo = TipoEvento::factory()->delGrupo('policia')->create();
        $apertura = TipoEvento::factory()->sinUbicacion()->delGrupo('internos')->create(['nombre' => 'Apertura de turno']);
        Evento::factory()->create(['tipo_evento_id' => $robo->id]);
        $interno = Evento::factory()->create(['tipo_evento_id' => $apertura->id]);

        $this->conToken();

        $this->getJson('/api/v1/eventos')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/eventos?categoria=internos')->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/eventos/{$interno->id}")->assertNotFound();
    }

    public function test_detalle_de_un_evento(): void
    {
        $evento = Evento::factory()->create();
        $this->conToken();

        $this->getJson("/api/v1/eventos/{$evento->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $evento->id)
            ->assertJsonPath('data.punto_monitoreo', null);

        $this->getJson('/api/v1/eventos/9999')->assertNotFound();
    }

    public function test_estadisticas_mensuales_y_anuales(): void
    {
        $tipo = TipoEvento::factory()->delGrupo('policia')->create();
        Evento::factory()->count(2)->create(['tipo_evento_id' => $tipo->id, 'fecha_hora' => '2026-10-03 10:00:00']);

        $this->conToken();

        $this->getJson('/api/v1/estadisticas/mensual/2026/10')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.periodo.desde', '2026-10-01');

        $this->getJson('/api/v1/estadisticas/anual/2026')
            ->assertOk()
            ->assertJsonCount(12, 'data.evolucion');

        $this->getJson('/api/v1/estadisticas/mensual/2026/13')->assertNotFound();
    }

    public function test_es_de_solo_lectura(): void
    {
        $evento = Evento::factory()->create();
        $this->conToken();

        $this->postJson('/api/v1/eventos', [])->assertMethodNotAllowed();
        $this->deleteJson("/api/v1/eventos/{$evento->id}")->assertMethodNotAllowed();
    }

    public function test_el_comando_emite_un_token_que_funciona(): void
    {
        $usuario = User::factory()->create(['username' => 'datosabiertos']);

        $this->artisan('api:token', ['username' => 'datosabiertos', 'nombre' => 'Portal'])
            ->expectsOutputToContain('Token emitido')
            ->assertSuccessful();

        $token = $usuario->tokens()->sole();
        $this->assertSame(['datos-abiertos:leer'], $token->abilities);

        $this->artisan('api:token', ['username' => 'noexiste', 'nombre' => 'X'])->assertFailed();
    }
}
