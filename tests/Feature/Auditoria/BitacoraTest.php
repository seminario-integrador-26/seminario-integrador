<?php

namespace Tests\Feature\Auditoria;

use App\Models\AuditoriaAcceso;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\Turno;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Bitácora de auditoría: la ve el Administrador de sistema y registra las
 * acciones de los usuarios (usuario, rol, IP, acción).
 */
class BitacoraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    public function test_el_administrador_de_sistema_ve_la_bitacora(): void
    {
        $admin = User::factory()->create()->assignRole('Administrador de sistema');

        $this->actingAs($admin)
            ->get(route('usuarios.auditoria'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Usuarios/Auditoria')
                ->has('registros.data'));
    }

    public function test_el_administrativo_no_accede_a_la_bitacora(): void
    {
        // El Administrativo es solo lectura: no gestiona usuarios ni ve la bitácora.
        $admin = User::factory()->create()->assignRole('Administrativo');

        $this->actingAs($admin)
            ->get(route('usuarios.auditoria'))
            ->assertForbidden();
    }

    public function test_registrar_un_evento_queda_en_la_bitacora_con_rol_e_ip(): void
    {
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        Turno::factory()->create(['supervisor_id' => $supervisor->id]);
        $tipo = TipoEvento::factory()->create(['requiere_ubicacion' => true]);
        $punto = PuntoMonitoreo::factory()->create();

        $this->actingAs($supervisor)
            ->post(route('eventos.store'), [
                'tipo_evento_id' => $tipo->id,
                'fecha' => now()->subDay()->toDateString(),
                'hora' => '10:00:00',
                'punto_monitoreo_id' => $punto->id,
                'timestamp_video' => '10:00:00',
                'descripcion' => 'Prueba',
            ])
            ->assertSessionHasNoErrors();

        $registro = AuditoriaAcceso::where('evento', AuditoriaAcceso::EVENTO_REGISTRO_EVENTO)->first();

        $this->assertNotNull($registro);
        $this->assertSame($supervisor->id, $registro->user_id);
        $this->assertSame('Supervisor', $registro->rol);
        $this->assertNotNull($registro->ip_address);
        $this->assertNotNull($registro->descripcion);
    }

    public function test_crear_un_usuario_queda_en_la_bitacora(): void
    {
        $admin = User::factory()->create()->assignRole('Administrador de sistema');

        $this->actingAs($admin)
            ->post(route('usuarios.store'), [
                'name' => 'Nuevo',
                'username' => 'nuevo',
                'email' => 'nuevo@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'rol' => 'Supervisor',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_ALTA_USUARIO,
            'user_id' => $admin->id,
            'rol' => 'Administrador de sistema',
        ]);
    }
}
