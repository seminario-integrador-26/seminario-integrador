<?php

namespace Tests\Feature\Auth;

use App\Models\AuditoriaAcceso;
use App\Models\User;
use App\Services\Auth\BloqueoCuentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US-001: log de auditoría de accesos (usuario, fecha y hora).
 */
class AuditoriaAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_login_exitoso_queda_auditado(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $registro = AuditoriaAcceso::where('evento', AuditoriaAcceso::EVENTO_LOGIN)->first();

        $this->assertNotNull($registro);
        $this->assertSame($user->id, $registro->user_id);
        $this->assertSame($user->email, $registro->email);
        $this->assertNotNull($registro->created_at);
        $this->assertNotNull($registro->ip_address);
    }

    public function test_el_intento_fallido_queda_auditado(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password-incorrecta',
        ]);

        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_LOGIN_FALLIDO,
            'email' => $user->email,
        ]);
    }

    public function test_el_bloqueo_queda_auditado(): void
    {
        $user = User::factory()->create();

        foreach (range(1, BloqueoCuentaService::MAX_INTENTOS) as $_) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'password-incorrecta',
            ]);
        }

        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_BLOQUEO,
            'user_id' => $user->id,
        ]);
    }

    public function test_el_logout_queda_auditado(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout');

        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_LOGOUT,
            'user_id' => $user->id,
        ]);
    }
}
