<?php

namespace Tests\Feature\Reportes;

use App\Models\AuditoriaAcceso;
use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use App\Services\ExportacionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TipoEventoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * US-012 (Libro de guardia en PDF) y US-013 (exportación de eventos a Excel).
 */
class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private User $administrativo;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, TipoEventoSeeder::class]);
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));

        $this->administrativo = User::factory()->create(['name' => 'Laura Administrativa'])->assignRole('Administrativo');
        $this->supervisor = User::factory()->create(['name' => 'Ana Supervisora'])->assignRole('Supervisor');
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function evento(array $atributos = []): Evento
    {
        return Evento::factory()->create([
            'usuario_id' => $this->supervisor->id,
            ...$atributos,
        ]);
    }

    // --- US-012: Libro de guardia en PDF ------------------------------------

    private function interno(string $tipo, string $fechaHora, ?string $descripcion = null): Evento
    {
        return $this->evento([
            'tipo_evento_id' => TipoEvento::interno($tipo),
            'fecha_hora' => $fechaHora,
            'descripcion' => $descripcion,
        ]);
    }

    /**
     * Guardia del 14/10 de 06:00 a 14:00; devuelve la apertura.
     */
    private function guardiaCerrada(): Evento
    {
        $apertura = $this->interno(TipoEvento::APERTURA_TURNO, '2026-10-14 06:00:00', 'Personal presente: Juan y María.');
        $this->interno(TipoEvento::CIERRE_TURNO, '2026-10-14 14:00:00', 'Novedades pendientes: Cámara 12 sin imagen.');

        return $apertura;
    }

    public function test_lista_las_aperturas_con_su_cierre(): void
    {
        $cerrada = $this->guardiaCerrada();
        $enCurso = $this->interno(TipoEvento::APERTURA_TURNO, '2026-10-15 06:00:00');

        $this->actingAs($this->administrativo)
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reportes/Index')
                ->has('turnos.data', 2)
                ->where('turnos.data.0.id', $enCurso->id)
                ->where('turnos.data.0.fin', null)
                ->where('turnos.data.1.id', $cerrada->id)
                ->where('turnos.data.1.inicio', '06:00')
                ->where('turnos.data.1.fin', '14:00')
                ->where('turnos.data.1.eventos', 2)
                ->where('turnos.data.1.supervisor', 'Ana Supervisora'));
    }

    public function test_descarga_el_pdf_de_la_guardia_y_lo_audita(): void
    {
        $apertura = $this->guardiaCerrada();
        $this->evento(['fecha_hora' => '2026-10-14 09:15:00', 'descripcion' => 'Acción en la vía pública con ñandú']);

        $respuesta = $this->actingAs($this->administrativo)
            ->get(route('reportes.guardia.pdf', $apertura->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload("Libro_Guardia_{$apertura->id}_14-10-2026.pdf");

        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_REPORTE_PDF,
            'user_id' => $this->administrativo->id,
        ]);
    }

    public function test_el_pdf_va_de_la_apertura_al_cierre_siguiente_en_orden(): void
    {
        $apertura = $this->guardiaCerrada();
        $tipo = TipoEvento::factory()->delGrupo('policia')->create(['nombre' => 'Robo']);
        $tarde = $this->evento(['tipo_evento_id' => $tipo->id, 'fecha_hora' => '2026-10-14 13:00:00']);
        $temprano = $this->evento(['tipo_evento_id' => $tipo->id, 'fecha_hora' => '2026-10-14 07:00:00']);
        $this->evento(['tipo_evento_id' => $tipo->id, 'fecha_hora' => '2026-10-14 05:00:00']); // antes de la apertura
        $this->evento(['tipo_evento_id' => $tipo->id, 'fecha_hora' => '2026-10-14 15:00:00']); // después del cierre

        $datos = null;
        $exportacion = Mockery::mock(ExportacionService::class);
        $exportacion->shouldReceive('exportar')->once()
            ->withArgs(function (string $formato, array $payload) use (&$datos) {
                $datos = $payload;

                return $formato === 'pdf';
            })
            ->andReturn('%PDF-falso');
        $this->app->instance(ExportacionService::class, $exportacion);

        $this->actingAs($this->administrativo)
            ->get(route('reportes.guardia.pdf', $apertura->id))
            ->assertOk();

        $this->assertSame('reportes.turno', $datos['vista']);
        $ids = array_column($datos['eventos'], 'id');
        $this->assertSame($apertura->id, $ids[0]);
        $this->assertSame([$temprano->id, $tarde->id], array_slice($ids, 1, 2));
        $this->assertCount(4, $ids); // apertura, 2 robos, cierre
        $this->assertContains(['tipo' => 'Robo', 'categoria' => 'Policía', 'cantidad' => 2], $datos['resumen']);
        $this->assertSame('14/10/2026', $datos['turno']['fecha']);
        $this->assertSame('06:00', $datos['turno']['inicio']);
        $this->assertSame('14:00', $datos['turno']['fin']);
        $this->assertSame('Ana Supervisora', $datos['turno']['supervisor']);
        $this->assertSame('Personal presente: Juan y María.', $datos['turno']['apertura']);
        $this->assertSame('Novedades pendientes: Cámara 12 sin imagen.', $datos['turno']['cierre']);
        $this->assertSame('Laura Administrativa', $datos['generado']['por']);
    }

    public function test_no_genera_el_pdf_de_un_turno_sin_cierre(): void
    {
        $abierta = $this->interno(TipoEvento::APERTURA_TURNO, '2026-10-15 06:00:00');

        $this->actingAs($this->administrativo)
            ->from(route('reportes.index'))
            ->get(route('reportes.guardia.pdf', $abierta->id))
            ->assertRedirect(route('reportes.index'))
            ->assertSessionHas('error');
    }

    public function test_un_cierre_despues_de_otra_apertura_no_cierra_la_anterior(): void
    {
        $olvidada = $this->interno(TipoEvento::APERTURA_TURNO, '2026-10-13 06:00:00');
        $this->guardiaCerrada();

        $this->actingAs($this->administrativo)
            ->from(route('reportes.index'))
            ->get(route('reportes.guardia.pdf', $olvidada->id))
            ->assertSessionHas('error');
    }

    public function test_no_genera_el_pdf_desde_un_evento_que_no_es_apertura(): void
    {
        $robo = $this->evento(['fecha_hora' => '2026-10-14 09:00:00']);

        $this->actingAs($this->administrativo)
            ->from(route('reportes.index'))
            ->get(route('reportes.guardia.pdf', $robo->id))
            ->assertSessionHas('error');
    }

    public function test_el_supervisor_no_accede_a_reportes(): void
    {
        $apertura = $this->guardiaCerrada();

        $this->actingAs($this->supervisor)->get(route('reportes.index'))->assertForbidden();
        $this->actingAs($this->supervisor)->get(route('reportes.guardia.pdf', $apertura->id))->assertForbidden();
        $this->actingAs($this->supervisor)->get(route('reportes.eventos.planilla', ['formato' => 'xlsx']))->assertForbidden();
    }

    // --- US-013: exportación a Excel ----------------------------------------

    /**
     * @return list<list<mixed>>
     */
    private function leerXlsx(string $contenido): array
    {
        $archivo = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($archivo, $contenido);

        try {
            return IOFactory::load($archivo)->getActiveSheet()->toArray();
        } finally {
            unlink($archivo);
        }
    }

    public function test_exporta_los_eventos_filtrados_con_fechas_ddmmaaaa_y_observacion(): void
    {
        $robo = TipoEvento::factory()->delGrupo('policia')->create(['nombre' => 'Robo']);
        $informativo = TipoEvento::factory()->sinUbicacion()->delGrupo('internos')->create([
            'nombre' => 'Novedad', 'es_cuantificable' => false,
        ]);
        $punto = PuntoMonitoreo::factory()->delTipo('municipal')->create(['codigo' => 'PM-M-007', 'nombre' => 'Plaza Centenario']);

        $this->evento(['tipo_evento_id' => $robo->id, 'punto_monitoreo_id' => $punto->id, 'fecha_hora' => '2026-10-03 21:05:09', 'timestamp_video' => '21:04:50', 'descripcion' => 'Arrebato de cartera en la esquina de Ñandubay']);
        $this->evento(['tipo_evento_id' => $robo->id, 'fecha_hora' => '2026-10-04 10:00:00']);
        $this->evento(['tipo_evento_id' => $informativo->id, 'fecha_hora' => '2026-10-05 08:00:00']);
        $this->evento(['tipo_evento_id' => $robo->id, 'fecha_hora' => '2026-09-20 10:00:00']); // fuera del filtro

        $respuesta = $this->actingAs($this->administrativo)
            ->get(route('reportes.eventos.planilla', ['formato' => 'xlsx', 'desde' => '2026-10-01', 'hasta' => '2026-10-31', 'orden' => 'fecha_hora', 'direccion' => 'asc']))
            ->assertOk()
            ->assertDownload('Novedades_CMU_01-10-2026_31-10-2026.xlsx')
            ->assertHeader('X-Cantidad-Eventos', '3');

        $hoja = $this->leerXlsx($respuesta->getContent());

        $this->assertSame(
            ['ID', 'Fecha', 'Horario', 'Categoría', 'Tipo', 'PM código', 'PM nombre', 'Tipo PM', 'Barrio', 'Dirección', 'Descripción', 'Registró', 'Observación'],
            $hoja[0],
        );
        $this->assertCount(4, $hoja); // encabezado + 3 eventos
        $this->assertSame(['03/10/2026', '21:05:09', 'Policía', 'Robo', 'PM-M-007', 'Plaza Centenario', 'Municipal', null, null], array_slice($hoja[1], 1, 9));
        $this->assertSame('Arrebato de cartera en la esquina de Ñandubay', $hoja[1][10]); // UTF-8
        $this->assertNull($hoja[1][12]);
        $this->assertSame('Novedad sin ubicación', $hoja[2][12]);
        $this->assertSame('Novedad informativa', $hoja[3][12]);

        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_EXPORTACION,
            'user_id' => $this->administrativo->id,
        ]);
    }

    public function test_exporta_todos_los_registros_sin_el_limite_de_la_paginacion(): void
    {
        Evento::factory()->count(45)->create(['usuario_id' => $this->supervisor->id]);

        $respuesta = $this->actingAs($this->administrativo)
            ->get(route('reportes.eventos.planilla', ['formato' => 'xlsx']))
            ->assertOk();

        $this->assertCount(46, $this->leerXlsx($respuesta->getContent()));
    }

    public function test_exporta_tambien_en_xls_con_el_mismo_contenido(): void
    {
        $robo = TipoEvento::factory()->delGrupo('policia')->create(['nombre' => 'Robo']);
        $this->evento(['tipo_evento_id' => $robo->id, 'fecha_hora' => '2026-10-03 21:05:09', 'descripcion' => 'Arrebato en Ñandubay y Güemes']);

        $respuesta = $this->actingAs($this->administrativo)
            ->get(route('reportes.eventos.planilla', ['formato' => 'xls', 'desde' => '2026-10-01', 'hasta' => '2026-10-31']))
            ->assertOk()
            ->assertDownload('Novedades_CMU_01-10-2026_31-10-2026.xls')
            ->assertHeader('Content-Type', 'application/vnd.ms-excel')
            ->assertHeader('X-Cantidad-Eventos', '1');

        // Formato binario BIFF (OLE2), no el zip de .xlsx.
        $this->assertStringStartsWith("\xD0\xCF\x11\xE0", $respuesta->getContent());

        $hoja = $this->leerXlsx($respuesta->getContent());
        $this->assertSame('Fecha', $hoja[0][1]);
        $this->assertSame('03/10/2026', $hoja[1][1]);
        $this->assertSame('Arrebato en Ñandubay y Güemes', $hoja[1][10]); // Unicode

        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_EXPORTACION,
            'descripcion' => 'Exportación .xls de 1 novedad(es). Filtros: {"desde":"2026-10-01","hasta":"2026-10-31"}',
        ]);
    }

    public function test_no_acepta_otros_formatos(): void
    {
        $this->actingAs($this->administrativo)
            ->get('/reportes/eventos.csv')
            ->assertNotFound();
    }

    public function test_sin_filtro_de_fecha_el_nombre_usa_el_rango_de_los_eventos(): void
    {
        $this->evento(['fecha_hora' => '2026-08-02 10:00:00']);
        $this->evento(['fecha_hora' => '2026-10-09 10:00:00']);

        $this->actingAs($this->administrativo)
            ->get(route('reportes.eventos.planilla', ['formato' => 'xlsx']))
            ->assertDownload('Novedades_CMU_02-08-2026_09-10-2026.xlsx');
    }
}
