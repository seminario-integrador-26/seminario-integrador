<?php

namespace Tests\Feature\Auditoria;

use App\Models\AuditoriaAcceso;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * US-027: consulta del log de auditoría con filtros, orden y exportación.
 */
class ConsultaAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));

        $this->admin = User::factory()->create(['name' => 'Admin'])->assignRole('Administrador de sistema');
        $this->ana = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.com']);

        $this->registro($this->ana, AuditoriaAcceso::EVENTO_REGISTRO_EVENTO, 'Evento #12 registrado (Robo).', '2026-10-10 09:00:00');
        $this->registro($this->ana, AuditoriaAcceso::EVENTO_REGISTRO_EVENTO, 'Evento #123 registrado (Robo).', '2026-10-11 09:00:00');
        $this->registro($this->ana, AuditoriaAcceso::EVENTO_LOGIN, null, '2026-10-01 08:00:00');
        $this->registro($this->admin, AuditoriaAcceso::EVENTO_PM_MOD, 'PM PM-M-001 actualizado.', '2026-10-12 10:00:00');
    }

    private function registro(User $user, string $evento, ?string $descripcion, string $fecha): void
    {
        $this->travelTo(Carbon::parse($fecha));
        AuditoriaAcceso::create(['user_id' => $user->id, 'email' => $user->email, 'evento' => $evento, 'descripcion' => $descripcion]);
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    }

    /**
     * @return list<int>
     */
    private function ids(array $filtros): array
    {
        $ids = [];

        $this->actingAs($this->admin)
            ->get(route('usuarios.auditoria', $filtros))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$ids) {
                $ids = collect($page->toArray()['props']['registros']['data'])
                    ->reject(fn ($r) => $r['clave_accion'] === AuditoriaAcceso::EVENTO_AUDITORIA_CONSULTA)
                    ->pluck('descripcion')
                    ->all();
            });

        return $ids;
    }

    public function test_filtra_por_usuario_accion_y_fechas(): void
    {
        $this->assertCount(3, $this->ids(['usuario_id' => $this->ana->id]));
        $this->assertSame(['PM PM-M-001 actualizado.'], $this->ids(['accion' => AuditoriaAcceso::EVENTO_PM_MOD]));
        $this->assertSame(
            ['Evento #123 registrado (Robo).', 'Evento #12 registrado (Robo).'],
            $this->ids(['desde' => '2026-10-10', 'hasta' => '2026-10-11']),
        );
    }

    public function test_el_objeto_numerico_busca_el_id_exacto(): void
    {
        $this->assertSame(['Evento #12 registrado (Robo).'], $this->ids(['objeto' => '12']));
        $this->assertSame(['PM PM-M-001 actualizado.'], $this->ids(['objeto' => 'pm-m-001']));
    }

    public function test_ordena_por_columna(): void
    {
        $this->actingAs($this->admin)
            ->get(route('usuarios.auditoria', ['orden' => 'fecha', 'direccion' => 'asc', 'usuario_id' => $this->ana->id]))
            ->assertInertia(fn (Assert $page) => $page->where('registros.data.0.clave_accion', AuditoriaAcceso::EVENTO_LOGIN));
    }

    public function test_el_acceso_al_log_queda_registrado_una_vez_cada_10_minutos(): void
    {
        $this->actingAs($this->admin)->get(route('usuarios.auditoria'));
        $this->actingAs($this->admin)->get(route('usuarios.auditoria', ['page' => 2]));

        $this->assertSame(1, AuditoriaAcceso::where('evento', AuditoriaAcceso::EVENTO_AUDITORIA_CONSULTA)->count());

        $this->travel(11)->minutes();
        $this->actingAs($this->admin)->get(route('usuarios.auditoria'));

        $this->assertSame(2, AuditoriaAcceso::where('evento', AuditoriaAcceso::EVENTO_AUDITORIA_CONSULTA)->count());
    }

    public function test_exporta_el_log_filtrado_a_excel(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->get(route('usuarios.auditoria.exportar', ['usuario_id' => $this->ana->id, 'accion' => AuditoriaAcceso::EVENTO_REGISTRO_EVENTO]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $archivo = tempnam(sys_get_temp_dir(), 'aud');
        file_put_contents($archivo, $respuesta->getContent());
        $hoja = IOFactory::load($archivo)->getActiveSheet()->toArray();
        unlink($archivo);

        $this->assertSame(['Fecha y hora', 'Usuario', 'Email', 'Rol', 'Acción', 'IP', 'Detalle'], $hoja[0]);
        $this->assertCount(3, $hoja);
        $this->assertSame('Novedad registrada', $hoja[1][4]);

        $this->assertDatabaseHas('auditoria_accesos', ['evento' => AuditoriaAcceso::EVENTO_EXPORTACION, 'user_id' => $this->admin->id]);
    }

    public function test_solo_el_administrador_de_sistema_accede(): void
    {
        foreach (['Supervisor', 'Administrativo'] as $rol) {
            $usuario = User::factory()->create()->assignRole($rol);

            $this->actingAs($usuario)->get(route('usuarios.auditoria'))->assertForbidden();
            $this->actingAs($usuario)->get(route('usuarios.auditoria.exportar'))->assertForbidden();
        }
    }
}
