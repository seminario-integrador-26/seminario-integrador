<?php

namespace Tests\Feature\Reportes;

use App\Models\AuditoriaAcceso;
use App\Models\Errata;
use App\Models\Evento;
use App\Models\GrupoEvento;
use App\Models\TipoEvento;
use App\Models\User;
use App\Services\RegistroIntervencionesService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TipoEventoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Registro de Intervenciones mensual (.xlsx) para el Portal de Transparencia,
 * con el formato de intervenciones-del-centro-de-monitoreo-<mes>-<año>.xlsx.
 */
class RegistroIntervencionesTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, TipoEventoSeeder::class]);
        $this->travelTo(Carbon::parse('2026-11-24 10:00:00'));

        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
    }

    private function tipo(string $grupo, string $nombre): int
    {
        return TipoEvento::where('nombre', $nombre)
            ->where('grupo_evento_id', GrupoEvento::where('codigo', $grupo)->value('id'))
            ->value('id');
    }

    private function novedad(string $grupo, string $tipo, string $fechaHora): Evento
    {
        return Evento::factory()->create([
            'usuario_id' => $this->supervisor->id,
            'tipo_evento_id' => $this->tipo($grupo, $tipo),
            'fecha_hora' => $fechaHora,
        ]);
    }

    private function leer(string $contenido): Spreadsheet
    {
        $ruta = tempnam(sys_get_temp_dir(), 'intervenciones');
        file_put_contents($ruta, $contenido);

        try {
            return IOFactory::load($ruta);
        } finally {
            unlink($ruta);
        }
    }

    public function test_cada_fila_de_la_planilla_corresponde_a_un_tipo_del_catalogo(): void
    {
        foreach (RegistroIntervencionesService::FILAS as $fila => [$grupo, $tipo]) {
            $this->assertNotNull($this->tipo($grupo, $tipo), "Fila {$fila}: no existe el tipo {$tipo} ({$grupo}).");
        }
    }

    public function test_el_supervisor_descarga_la_planilla_del_mes_con_el_formato_oficial(): void
    {
        $this->novedad('policia', 'Personas sospechosas', '2026-10-01 08:00:00');
        $this->novedad('policia', 'Personas sospechosas', '2026-10-01 23:59:00');
        $this->novedad('policia', 'Otros', '2026-10-31 03:00:00');
        $this->novedad('guardia_local', 'Otros', '2026-10-15 12:00:00');
        $this->novedad('guardia_local', 'Camiones en zona prohibida', '2026-10-15 12:00:00');
        $this->novedad('internos', 'Oficios judiciales', '2026-10-02 09:00:00');
        // No cuentan: otro mes y eventos internos del libro de guardia.
        $this->novedad('policia', 'Robos', '2026-09-30 23:59:59');
        $this->novedad('policia', 'Robos', '2026-11-01 00:00:00');
        $this->novedad('internos', TipoEvento::APERTURA_TURNO, '2026-10-01 06:00:00');

        $respuesta = $this->actingAs($this->supervisor)
            ->get(route('intervenciones.descargar', ['anio' => 2026, 'mes' => 10]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Content-Disposition', 'attachment; filename="intervenciones-del-centro-de-monitoreo-octubre-2026.xlsx"');

        $hoja = $this->leer($respuesta->getContent())->getActiveSheet();

        $this->assertSame('OCT.', $hoja->getTitle());
        $this->assertSame('OCTUBRE 2026', $hoja->getCell('A5')->getValue());
        $this->assertSame("CENTRO DE CONTROL VIDEOPERATIVO \nREGISTRO DE INTERVENCIONES", $hoja->getCell('A3')->getValue());
        $this->assertSame(['A3:AG4' => 'A3:AG4'], $hoja->getMergeCells());
        $this->assertSame('PERSONAS SOSPECHOSAS', $hoja->getCell('A7')->getValue());
        $this->assertSame('TOTAL DE INTERVENC.', $hoja->getCell('A48')->getValue());
        $this->assertSame(31, $hoja->getCell('AF6')->getValue());

        $this->assertSame(2, $hoja->getCell('B7')->getValue());        // día 1
        $this->assertSame(1, $hoja->getCell('AF20')->getValue());      // Otros (policía), día 31
        $this->assertSame(1, $hoja->getCell('P41')->getValue());       // Otros (guardia local), día 15
        $this->assertSame(1, $hoja->getCell('P23')->getValue());
        $this->assertSame(1, $hoja->getCell('C44')->getValue());
        $this->assertNull($hoja->getCell('C7')->getValue());           // sin novedades: en blanco
        $this->assertNull($hoja->getCell('AF10')->getValue());         // robos de otros meses

        $this->assertSame('=SUM(B7:AF7)', $hoja->getCell('AG7')->getValue());
        $this->assertSame('=B21+B42+B47', $hoja->getCell('B48')->getValue());
        $this->assertEquals(2, $hoja->getCell('AG7')->getCalculatedValue());
        $this->assertEquals(6, $hoja->getCell('AG48')->getCalculatedValue());

        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_EXPORTACION,
            'user_id' => $this->supervisor->id,
        ]);
    }

    public function test_cuenta_el_tipo_vigente_despues_de_la_fe_de_errata(): void
    {
        $evento = $this->novedad('policia', 'Robos', '2026-10-05 10:00:00');
        Errata::create([
            'evento_id' => $evento->id,
            'usuario_id' => $this->supervisor->id,
            'campo_corregido' => 'tipo_evento_id',
            'valor_anterior' => (string) $evento->tipo_evento_id,
            'valor_nuevo' => (string) $this->tipo('policia', 'Hurtos'),
            'motivo' => 'Era un hurto.',
        ]);

        $conteos = app(RegistroIntervencionesService::class)->conteos(2026, 10);

        $this->assertSame([], $conteos[10]);        // ROBOS
        $this->assertSame([5 => 1], $conteos[11]);  // HURTOS
    }

    public function test_en_meses_de_30_dias_la_columna_31_queda_sin_numero(): void
    {
        $respuesta = $this->actingAs($this->supervisor)
            ->get(route('intervenciones.descargar', ['anio' => 2026, 'mes' => 9]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="intervenciones-del-centro-de-monitoreo-septiembre-2026.xlsx"');

        $hoja = $this->leer($respuesta->getContent())->getActiveSheet();

        $this->assertSame('SEPTIEMBRE 2026', $hoja->getCell('A5')->getValue());
        $this->assertSame(30, $hoja->getCell('AE6')->getValue());
        $this->assertNull($hoja->getCell('AF6')->getValue());
        $this->assertNull($hoja->getCell('AF22')->getValue());
        $this->assertNull($hoja->getCell('AF43')->getValue());
    }

    public function test_la_pantalla_muestra_el_mes_anterior_con_sus_totales(): void
    {
        $this->novedad('policia', 'Robos', '2026-10-05 10:00:00');
        $this->novedad('internos', 'Particulares', '2026-10-06 10:00:00');

        $this->actingAs($this->supervisor)
            ->get(route('intervenciones.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Intervenciones/Index')
                ->where('filtros', ['anio' => 2026, 'mes' => 10])
                ->where('resumen.titulo', 'OCTUBRE 2026')
                ->where('resumen.total', 2)
                ->where('resumen.bloques.0.titulo', 'CONCEPTO POLICIAL')
                ->where('resumen.bloques.0.filas.3', ['concepto' => 'ROBOS', 'total' => 1])
                ->where('resumen.bloques.2.subtotal', 1)
                ->where('enCurso', false));
    }

    public function test_no_se_genera_un_mes_que_no_empezo(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('intervenciones.index', ['anio' => 2026, 'mes' => 12]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('resumen', null)
                ->where('error', 'El mes elegido todavía no empezó.'));

        $this->actingAs($this->supervisor)
            ->get(route('intervenciones.descargar', ['anio' => 2026, 'mes' => 12]))
            ->assertRedirect();
    }

    public function test_los_tres_roles_pueden_descargarla(): void
    {
        foreach (['Supervisor', 'Administrativo', 'Administrador de sistema'] as $rol) {
            $usuario = User::factory()->create()->assignRole($rol);

            $this->actingAs($usuario)
                ->get(route('intervenciones.descargar', ['anio' => 2026, 'mes' => 10]))
                ->assertOk();
        }

        $this->actingAs(User::factory()->create())
            ->get(route('intervenciones.index'))
            ->assertForbidden();
    }
}
