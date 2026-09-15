<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\BloqueoCuentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US-001: bloqueo temporal de la cuenta tras 3 intentos fallidos.
 */
class BloqueoCuentaTest extends TestCase
{
    use RefreshDatabase;

    private function intentoFallido(User $user): void
    {
        $this->post('/login', [
            'username' => $user->username,
            'password' => 'password-incorrecta',
        ]);
    }

    public function test_la_cuenta_se_bloquea_al_tercer_intento_fallido(): void
    {
        $user = User::factory()->create();

        foreach (range(1, BloqueoCuentaService::MAX_INTENTOS) as $_) {
            $this->intentoFallido($user);
        }

        $user->refresh();

        $this->assertSame(BloqueoCuentaService::MAX_INTENTOS, $user->intentos_fallidos);
        $this->assertNotNull($user->bloqueado_hasta);
        $this->assertTrue($user->bloqueado_hasta->isFuture());
    }

    public function test_cuenta_bloqueada_rechaza_la_contrasena_correcta(): void
    {
        $user = User::factory()->create();

        foreach (range(1, BloqueoCuentaService::MAX_INTENTOS) as $_) {
            $this->intentoFallido($user);
        }

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('username');

        $this->assertStringContainsString(
            'Cuenta bloqueada temporalmente',
            session('errors')->first('username'),
        );
    }

    public function test_dos_intentos_fallidos_no_bloquean(): void
    {
        $user = User::factory()->create();

        $this->intentoFallido($user);
        $this->intentoFallido($user);

        $user->refresh();

        $this->assertSame(2, $user->intentos_fallidos);
        $this->assertNull($user->bloqueado_hasta);
    }

    public function test_el_bloqueo_se_libera_al_vencer_el_plazo(): void
    {
        $user = User::factory()->create();

        foreach (range(1, BloqueoCuentaService::MAX_INTENTOS) as $_) {
            $this->intentoFallido($user);
        }

        $this->travel(BloqueoCuentaService::MINUTOS_BLOQUEO + 1)->minutes();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_exitoso_resetea_el_contador(): void
    {
        $user = User::factory()->create();

        $this->intentoFallido($user);
        $this->intentoFallido($user);

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $user->refresh();

        $this->assertAuthenticated();
        $this->assertSame(0, $user->intentos_fallidos);
        $this->assertNull($user->bloqueado_hasta);
    }

    public function test_la_sesion_expira_a_los_30_minutos_de_inactividad(): void
    {
        $this->assertSame(30, (int) config('session.lifetime'));
        $this->assertFalse((bool) config('session.expire_on_close'));
    }
}
