<?php

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Gestión de usuarios y política de blanqueo de contraseñas.
 * Login por nombre de usuario; entre Administradores de sistema no se
 * blanquean la clave (recuperan por email).
 */
class GestionUsuariosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    public function test_el_login_es_por_usuario_no_por_email(): void
    {
        $user = User::factory()->create(['username' => 'jperez']);

        // Con el email como usuario, falla.
        $this->post('/login', ['username' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        // Con el nombre de usuario, entra.
        $this->post('/login', ['username' => 'jperez', 'password' => 'password']);
        $this->assertAuthenticated();
    }

    public function test_el_admin_de_sistema_blanquea_la_clave_de_otro_rol(): void
    {
        $admin = User::factory()->create()->assignRole('Administrador de sistema');
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        $hashViejo = $supervisor->password;

        $this->actingAs($admin)
            ->put(route('usuarios.update', $supervisor->id), [
                'name' => $supervisor->name,
                'username' => $supervisor->username,
                'email' => $supervisor->email,
                'password' => 'nueva-clave-123',
                'password_confirmation' => 'nueva-clave-123',
                'rol' => 'Supervisor',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotSame($hashViejo, $supervisor->fresh()->password);
    }

    public function test_no_se_blanquea_la_clave_de_un_admin_de_sistema(): void
    {
        $admin = User::factory()->create()->assignRole('Administrador de sistema');
        $otro = User::factory()->create(['password' => Hash::make('clave-original')])
            ->assignRole('Administrador de sistema');
        $hashViejo = $otro->password;

        $this->actingAs($admin)
            ->put(route('usuarios.update', $otro->id), [
                'name' => $otro->name,
                'username' => $otro->username,
                'email' => $otro->email,
                'password' => 'intento-de-blanqueo',
                'password_confirmation' => 'intento-de-blanqueo',
                'rol' => 'Administrador de sistema',
            ])
            ->assertSessionHas('error');

        // La contraseña no cambió.
        $this->assertSame($hashViejo, $otro->fresh()->password);
    }
}
