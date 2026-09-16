<?php

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Designación rápida de rol desde el listado y gestión de permisos por rol
 * (crear permiso, asignar/quitar), reservadas al Administrador de sistema.
 */
class GestionPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('Administrador de sistema');
    }

    public function test_el_admin_cambia_los_roles_de_otro_usuario_desde_el_listado(): void
    {
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($this->admin())
            ->patch(route('usuarios.roles', $supervisor->id), ['roles' => ['Administrativo']])
            ->assertSessionHasNoErrors();

        $this->assertTrue($supervisor->fresh()->hasRole('Administrativo'));
        $this->assertFalse($supervisor->fresh()->hasRole('Supervisor'));
    }

    public function test_un_usuario_puede_tener_varios_roles(): void
    {
        $usuario = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($this->admin())
            ->patch(route('usuarios.roles', $usuario->id), [
                'roles' => ['Supervisor', 'Administrativo'],
            ])
            ->assertSessionHasNoErrors();

        $usuario = $usuario->fresh();
        $this->assertTrue($usuario->hasRole('Supervisor'));
        $this->assertTrue($usuario->hasRole('Administrativo'));
    }

    public function test_no_se_puede_dejar_a_un_usuario_sin_roles(): void
    {
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($this->admin())
            ->patch(route('usuarios.roles', $supervisor->id), ['roles' => []])
            ->assertSessionHasErrors('roles');

        $this->assertTrue($supervisor->fresh()->hasRole('Supervisor'));
    }

    public function test_el_admin_no_puede_quitarse_a_si_mismo_el_rol_desde_el_listado(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('usuarios.roles', $admin->id), ['roles' => ['Supervisor']])
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->hasRole('Administrador de sistema'));
    }

    public function test_el_admin_crea_un_permiso_nuevo(): void
    {
        $this->actingAs($this->admin())
            ->post(route('roles.permisos.store'), ['name' => 'reportes.imprimir'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Permission::where('name', 'reportes.imprimir')->exists());
    }

    public function test_rechaza_un_permiso_con_formato_invalido(): void
    {
        $this->actingAs($this->admin())
            ->post(route('roles.permisos.store'), ['name' => 'Formato Invalido'])
            ->assertSessionHasErrors('name');
    }

    public function test_el_admin_asigna_y_quita_permisos_a_un_rol(): void
    {
        $rol = Role::where('name', 'Supervisor')->first();
        Permission::create(['name' => 'reportes.imprimir', 'guard_name' => 'web']);

        $this->actingAs($this->admin())
            ->put(route('roles.permisos.update', $rol->id), [
                'permisos' => ['eventos.consultar', 'reportes.imprimir'],
            ])
            ->assertSessionHasNoErrors();

        $rol = $rol->fresh();
        $this->assertTrue($rol->hasPermissionTo('reportes.imprimir'));
        $this->assertTrue($rol->hasPermissionTo('eventos.consultar'));
        // 'eventos.registrar' venía de la matriz y no fue enviado: se quitó.
        $this->assertFalse($rol->hasPermissionTo('eventos.registrar'));
    }

    public function test_el_admin_de_sistema_conserva_usuarios_gestionar_aunque_se_intente_quitar(): void
    {
        $rol = Role::where('name', 'Administrador de sistema')->first();

        // Se envía la lista sin 'usuarios.gestionar': el service lo re-agrega.
        $this->actingAs($this->admin())
            ->put(route('roles.permisos.update', $rol->id), [
                'permisos' => ['eventos.consultar'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($rol->fresh()->hasPermissionTo('usuarios.gestionar'));
    }

    public function test_un_supervisor_no_puede_gestionar_permisos(): void
    {
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($supervisor)
            ->post(route('roles.permisos.store'), ['name' => 'reportes.imprimir'])
            ->assertForbidden();
    }
}
