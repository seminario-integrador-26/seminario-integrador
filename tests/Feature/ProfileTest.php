<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    /**
     * El correo y el usuario los administra el Administrador de sistema
     * (permiso usuarios.gestionar); los demas roles no se los autogestionan.
     */
    private function administradorDeSistema(): User
    {
        return User::factory()->create()->assignRole('Administrador de sistema');
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_el_administrador_de_sistema_puede_cambiar_su_correo(): void
    {
        $user = $this->administradorDeSistema();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'username' => $user->username,
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->administradorDeSistema();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'username' => $user->username,
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_el_supervisor_no_puede_cambiarse_el_usuario_ni_el_correo(): void
    {
        $user = User::factory()->create([
            'username' => 'supervisor.turno',
            'email' => 'original@example.com',
        ])->assignRole('Supervisor');

        // Aunque mande los campos a mano: sin usuarios.gestionar quedan fuera
        // de validated() y nunca llegan al modelo.
        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Nombre Nuevo',
                'username' => 'usurpador',
                'email' => 'usurpado@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Nombre Nuevo', $user->name);
        $this->assertSame('supervisor.turno', $user->username);
        $this->assertSame('original@example.com', $user->email);
    }

    public function test_el_administrador_de_sistema_puede_cambiar_su_usuario(): void
    {
        $user = $this->administradorDeSistema();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'username' => 'admin.nuevo',
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('admin.nuevo', $user->refresh()->username);
    }

    public function test_el_administrativo_tampoco_puede_cambiarse_el_correo(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com'])
            ->assignRole('Administrativo');

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Otro Nombre',
                'username' => 'usurpador',
                'email' => 'usurpado@example.com',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('original@example.com', $user->refresh()->email);
    }

    public function test_el_perfil_indica_quien_puede_editar_la_identidad(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Supervisor'))
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('puedeEditarIdentidad', false)
            );

        $this->actingAs($this->administradorDeSistema())
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('puedeEditarIdentidad', true)
            );
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
