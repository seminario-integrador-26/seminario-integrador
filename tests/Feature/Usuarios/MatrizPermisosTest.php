<?php

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * La matriz de PermissionSeeder es la fuente de verdad de qué puede cada rol
 * (CLAUDE.md): el Administrativo es SOLO visualización y no gestiona usuarios
 * ni tipos de evento.
 */
class MatrizPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    public function test_solo_el_administrador_de_sistema_gestiona_usuarios(): void
    {
        $this->assertTrue(
            Role::findByName('Administrador de sistema')->hasPermissionTo('usuarios.gestionar'),
        );

        foreach (['Supervisor', 'Administrativo'] as $rol) {
            $this->assertFalse(
                Role::findByName($rol)->hasPermissionTo('usuarios.gestionar'),
                "El rol {$rol} no debe gestionar usuarios.",
            );
            $this->assertFalse(
                Role::findByName($rol)->hasPermissionTo('tipos_evento.gestionar'),
                "El rol {$rol} no debe gestionar tipos de evento.",
            );
        }
    }

    public function test_el_administrativo_no_entra_a_la_gestion_de_usuarios(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Administrativo'))
            ->get(route('usuarios.index'))
            ->assertForbidden();
    }

    /**
     * Volver a correr el seeder tiene que CONVERGER a la matriz: si un rol
     * quedó con un permiso de más, se lo saca.
     */
    public function test_el_seeder_quita_los_permisos_que_sobran(): void
    {
        $administrativo = Role::findByName('Administrativo');
        $administrativo->givePermissionTo('usuarios.gestionar');

        $this->assertTrue($administrativo->fresh()->hasPermissionTo('usuarios.gestionar'));

        $this->seed(PermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse(
            Role::findByName('Administrativo')->hasPermissionTo('usuarios.gestionar'),
        );
    }
}
